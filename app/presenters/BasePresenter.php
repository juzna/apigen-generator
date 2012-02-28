<?php

/**
 * Base class for all application presenters.
 *
 * @property \SystemContainer $context
 * @property \Nette\Database\Connection $db
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter {
	protected $db;

	protected function startup() {
		parent::startup();
		$this->db = $this->context->database;
	}
}
