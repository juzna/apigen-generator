<?php

/**
 * Downloads and generates API doc
 *
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class GeneratorPresenter extends BasePresenter {
	protected function startup() {
		parent::startup();
		$this->session->close(); // we ain't want session to block it all

		// do not buffer!
		while(ob_get_level() > 0) ob_end_flush();
		ob_implicit_flush(true);

		echo '<pre>';
	}


	// generate API for all repos
	public function actionGenerateAll() {
		foreach($this->db->query("select * from repo where lastPull is null or lastPull < date_sub(now(), interval 1 hour)") as $repo) {
			echo "Processing repo $repo->url ($repo->id)\n";
			$this->make($repo);
			echo '<hr>';
		}

		$this->sendResponse(new \Nette\Application\Responses\TextResponse("All done!"));
	}

	// generate api for one repo (by id or directory name)
	public function actionGenerate($dir) {
		$repo = $this->db->query("select * from repo where id = ? or dir = ?", $dir, $dir)->fetch();
		if(!$repo) throw new \Nette\Application\BadRequestException("Requested repo doesnt exist");

		$this->make($repo);
		$this->sendResponse(new \Nette\Application\Responses\TextResponse("Done!"));
	}


	/**
	 * make it all
	 * @param \Nette\Database\Row $item
	 */
	protected function make($item) {
		$repoDir = REPOS_DIR . '/' . $item->dir; $repoDirE = escapeshellarg($repoDir);
		$gitDir = "$repoDir/.git";

		// Clone repo
		if(!file_exists($gitDir)) {
			$cloned = true;
			echo "Cloning '$item->url' to '$repoDir'\n";
			$this->git("clone '$item->url' $repoDirE");
			$this->git("--git-dir='$gitDir' submodule init");
			$this->git("--git-dir='$gitDir' submodule update");
		} else $cloned = false;

		// Pull
		$headBefore = $this->getHead($gitDir);
		$branch = $item->branch ?: 'origin/master'; // branch to be checked out
		$this->git("--git-dir='$gitDir' fetch");
		$this->git("--git-dir='$gitDir' reset --hard $branch");
		$this->db->query("update repo set lastPull=now() where id=$item->id");
		$headAfter = $this->getHead($gitDir);
		if(!$this->getParameter('force') && !$cloned && $headBefore == $headAfter) { // no need to generate
			echo "  This repo is upto date ($headBefore, $headAfter)\n";
			return;
		}

		// Generate API
		$rootDir = APP_DIR . '/../';
		$sourceDir = "$repoDir/$item->subdir";
		$docDir = DOC_PROCESSING_DIR . "/$item->dir";
		$docFinalDir = DOC_FINAL_DIR . "/$item->dir";
		$this->exec("php -dmemory_limit=1024M $rootDir/apigen/apigen.php -s " . escapeshellarg($sourceDir) . " -d " . escapeshellarg($docDir) . " --download --debug --colors=no --progressbar=no --title=" . escapeshellarg($item->name));

		// check
		if(!file_exists("$docDir/index.html")) {
			echo "Failed to generate\n";
			$this->db->query("update repo set error=1 where id=$item->id");
			return;
		}

		// Move to final dir
		@mkdir(dirname($docFinalDir));
		$this->exec("rm -rf " . escapeshellarg($docFinalDir)); // remove contemporary doc
		rename($docDir, $docFinalDir);

		// Mark as generated
		$this->db->query("update repo set lastGenerated=now(), error=0 where id=$item->id");
	}


	/**
	 * Get HEAD revision of given repo
	 * @param string $gitDir
	 * @return string
	 */
	private function getHead($gitDir) {
		return trim(file_get_contents("$gitDir/refs/heads/master"));
	}

	private function git($cmd) {
		$this->exec("/usr/bin/git $cmd");
	}

	private function exec($cmd) {
		echo "$cmd\n";
		passthru($cmd);
	}
}
