<?php

use \Nette\Application\UI\Form;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter {
	protected function startup() {
		parent::startup();
		$this->session->start();
	}

	// list of repos
	public function renderDefault()	{
		$this->template->repos = $this->db->table('repo');
	}

	// result of doc generating
	public function renderResult($repoId) {
		if(!$repo = $this->db->table('repo')->get((int) $repoId)) throw new \Nette\Application\BadRequestException("Repo not found");

		$this->template->repo = $repo;
		$this->template->result = $this->db->table('result')->get($repo->apigenResultId);
	}


	protected function createComponentAddRepoForm() {
		$frm = new Form;
		$frm->addProtection();
		$frm->addText('name', 'Name of the project')->setRequired();
		$frm->addText('url', 'GitHub URL')->setRequired();
		$frm->addText('subdir', 'PHP code subdirectory');

		$frm->addSubmit('add', 'Generate API');

		$frm->onSuccess[] = callback($this, 'addRepo');

		return $frm;
	}

	public function addRepo(Form $frm) {
		if(!preg_match('~(?:https|git)://github.com/((?U:([^/]+)/([^/]+)))(?:\.git)?$~Ai', $frm->values['url'], $match)) {
			$frm->addError('Not a valid URL to GitHub repo');
			return;
		}
		if(preg_match('~(^|/)..(/|$)~', $frm->values['subdir'])) {
			$frm->addError("Invalid subdirectory given");
			return;
		}

		// Check for duplicate repo
		if($this->db->fetchColumn("select count(*) from repo where url = ? or dir = ?", $frm->values['url'], $match[1])) {
			$frm->addError("This repository already exists. Email me if you need to make any changes in it.");
			return;
		}

		$repo = $this->db->table('repo')->insert(array(
			'name'   => $frm->values['name'],
			'url'    => $frm->values['url'],
			'dir'    => $match[1],
			'subdir' => $frm->values['subdir'],
			'added'  => new DateTime,
		));

		// send mail
		$msg = new \Nette\Mail\Message();
		$msg->addTo('juzna.cz@gmail.com')->setSubject('ApiGen - new repo added')->setBody(var_export($repo->toArray(), true));
		$msg->send();

		// immediately execute generating script
		{
			$cmd = 'php ' . WWW_DIR . '/index.php generator:generate --dir=' . $repo->id;
			$cmd = "nohup $cmd > /tmp/apigen-repo-$repo->id.log 2>&1 &";
			exec($cmd);
		}

		$this->flashMessage("Your project has been added. Downloading and generating documentation may take a minute...");
		if(!$this->isAjax()) $this->redirect('default');
	}
}
