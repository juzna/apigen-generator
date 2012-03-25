<?php

/**
 * Generator - all the hard work
 *
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class Generator extends \Nette\Object {
	/** @var \Nette\Database\Connection */
	private $db;

	/** @var int Item being processed */
	private $itemId;


	public function __construct(\Nette\Database\Connection $db) {
		$this->db = $db;
	}

	/**
	 * make it all
	 * @param \Nette\Database\Row $item
	 * @param bool $force
	 */
	public function make($item, $force) {
		$pwd = getcwd(); // current working directory
		$this->itemId = $item->id;
		$this->db->query("update repo set inProgress=1 where id=$item->id");
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
		chdir($repoDir); $this->git("--git-dir='$gitDir' reset --hard $branch"); chdir($pwd);
		$this->db->query("update repo set lastPull=now() where id=$item->id");
		$headAfter = $this->getHead($gitDir);
		if(!$force && !$cloned && $headBefore == $headAfter) { // no need to generate
			echo "  This repo is upto date ($headBefore, $headAfter)\n";
			$this->db->query("update repo set inProgress=0 where id=$item->id");
			return;
		}

		// Generate API
		$timeStarted = microtime(true);
		$rootDir = realpath(APP_DIR . '/../');
		$sourceDir = "$repoDir/$item->subdir";
		$docDir = DOC_PROCESSING_DIR . "/$item->dir";
		$docFinalDir = DOC_FINAL_DIR . "/$item->dir";
		$cmd = "php -dmemory_limit=1024M $rootDir/apigen/apigen.php -s " . escapeshellarg($sourceDir) . " -d " . escapeshellarg($docDir) . " --charset=auto --download --debug --colors=no --progressbar=yes --title=" . escapeshellarg($item->name) . ' --google-analytics=UA-10607448-4 --google-cse-id=003517389015876838664:fhzsqxwsggg';
		$cmd = "$cmd > /tmp/apigen-generating-$item->id.log 2>&1; cat /tmp/apigen-generating-$item->id.log";
		$generatedWell = $this->exec($cmd, $result);
		$this->db->table('repo')->where('id', $item->id)->update(array(
			'inProgress'     => 0,
			'apigenResultId' => $result->id,
			'apigenTime'     => microtime(true) - $timeStarted,
			'sizeDoc'        => (int) exec("du -sb " . escapeshellarg($docDir)),
		));

		// check
		if(!file_exists("$docDir/index.html") || !$generatedWell) {
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

	/**
	 * Execute external command
	 * @param string $cmd
	 * @param ActiveRow $result Database row store into result table
	 * @return bool  Finished sucessfully?
	 */
	private function exec($cmd, &$result = null) {
		echo "$cmd\n";
		exec($cmd, $output, $retval);

		$data = implode("\n", $output);
		$data = preg_replace('~^.*\x08~m', '', $data);
		$data = str_replace(realpath(APP_DIR . '/..'), '...', $data); // remove local paths from output


		// Store results
		$result = $this->db->table('result')->insert(array(
			'repo_id' => $this->itemId,
			'cmd'     => $cmd,
			'ok'      => $retval == 0,
			'output'  => $data, // remove local paths from output,
		));

		return $retval == 0;
	}
}
