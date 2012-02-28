<?php

use \Nette\Application\UI\Form;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter {
	public function renderDefault()	{
		$this->template->repos = $this->db->table('repo');
	}

	protected function createComponentAddRepoForm() {
		$frm = new Form;
		$frm->addProtection();
		$frm->addText('name', 'Name of the project')->setRequired();
		$frm->addText('url', 'GitHub URL')->setRequired();
		$frm->addText('subdir', 'Subdirectory with PHP code');

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

		$this->db->exec("insert into repo", $info = array(
			'name'   => $frm->values['name'],
			'url'    => $frm->values['url'],
			'dir'    => $match[1],
			'subdir' => $frm->values['subdir'],
			'added'  => new DateTime,
		));

		// send mail
		$msg = new \Nette\Mail\Message();
		$msg->addTo('juzna.cz@gmail.com')->setSubject('ApiGen - new repo added')->setBody(var_export($info, true));
		$msg->send();

		$this->flashMessage("Your project has been added. Downloading and generating documentation may take a minute...");
		if(!$this->isAjax()) $this->redirect('default');
	}
}
