<?php


/**
 * on push hooks
 */
class HookPresenter extends BasePresenter
{
	// public IP addresses of GitHub, where hooks are called from
	public static $githubIPs = array(
		'207.97.227.253',
		'50.57.128.197',
		'108.171.174.178',
		'50.57.231.61',
	);



	public function actionDefault($url = '')
	{
		// home made hook
		if ($url) {
			$repo = $this->db->table('repo')->where('url', $url)->limit(1)->fetch();

		} else { // github hook
			$push = \Nette\Utils\Json::decode($this->getHttpRequest()->getPost('payload'), \Nette\Utils\Json::FORCE_ARRAY);
			$url = $push['repository']['url'];
			$urls = $this->getOtherURLs($url);

			$repo = $this->db->table('repo')->where('url', $urls)->limit(1)->fetch();
		}

		if ( ! $repo) throw new \Nette\Application\BadRequestException("Repository not found", 404);


		// log
		$message = implode("\t", array(
			date('Y-m-d H:i:s'),
			@$_SERVER['REMOTE_ADDR'],
			$repo->id,
			$repo->name,
		));
		file_put_contents(APP_DIR . '/../log/hook.log', "$message\n", FILE_APPEND);


		// do not buffer!
		while(ob_get_level() > 0) ob_end_flush();
		ob_implicit_flush(true);

		// Run apigen
		$this->context->generator->make($repo, FALSE);

		// and that's all folks
		$this->terminate();
	}



	/**
	 * Find all possible URLs the user may have inserted when adding the repo; by this URL it can be found in the database
	 *
	 * @param string $url
	 * @return string[]
	 */
	private function getOtherURLs($url)
	{
		if (preg_match('#https?://github.com/([^/]+)/([^/]+)#', $url, $match)) {
			list (, $user, $repo) = $match;

			return array(
				"https://github.com/$user/$repo",
				"https://github.com/$user/$repo.git",
				"http://github.com/$user/$repo",
				"http://github.com/$user/$repo.git",
				"git://github.com/$user/$repo",
				"git://github.com/$user/$repo.git",
			);
		}

		return array($url);
	}

}
