<?php

/**
 * Downloads and generates API doc
 *
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class GeneratorPresenter extends BasePresenter {
	// generate API for all repos
	public function actionGenerateAll() {
		foreach($this->db->table('repo')->findAll() as $repo) {
			echo "Processing repo $repo->url ($repo->id)\n";
			$this->make($repo);
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

	// make it all
	protected function make(\Nette\Database\Row $item) {
		$repoDir = REPOS_DIR . '/' . $item->dir;
		$gitDir = "$repoDir/.git";

		// Clone repo
		if(!file_exists($gitDir)) {
			echo "Cloning '$item->url' to '$repoDir'\n";
			$this->git("clone '$item->url' '$repoDir'");
			$this->git("--git-dir='$gitDir' submodule init");
			$this->git("--git-dir='$gitDir' submodule update");
		}

		// Pull
		$this->git("--git-dir='$gitDir' fetch");
		$this->git("--git-dir='$gitDir' reset --hard origin/master");
		$this->db->query("update repo set lastPull=now() where id=$item->id");

		// Generate API
		$rootDir = APP_DIR . '/../';
		$this->exec("php $rootDir/apigen/apigen.php -s $repoDir/$item->subdir -d " . DOC_PROCESSING_DIR . "/$item->dir");

		// Move to final dir
		@mkdir(DOC_FINAL_DIR . '/' . basename($item->dir));
		rename(DOC_PROCESSING_DIR . "/$item->dir", DOC_FINAL_DIR . "/$item->dir");

		// Mark as generated
		$this->db->query("update repo set lastGenerated=now() where id=$item->id");
	}

	private function git($cmd) {
		$this->exec("/usr/bin/git $cmd");
	}

	private function exec($cmd) {
		echo "$cmd<br>";
		passthru($cmd);
	}
}
