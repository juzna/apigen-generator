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

	protected function createTemplate($class = NULL) {
		$template = parent::createTemplate($class);
		$template->registerHelper('timeAgoInWords', 'Helpers::ago');
		$template->registerHelper('timeAgoInWordsEx', 'Helpers::agoEx');
		$template->registerHelper('interval', 'Helpers::interval');
		return $template;
	}
}
