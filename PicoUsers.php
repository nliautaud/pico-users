<?php
/**
 * A hierarchical users and rights system plugin for Pico.
 *
 * @author	Nicolas Liautaud
 * @link	https://github.com/nliautaud/pico-users
 * @link    http://picocms.org
 * @license http://opensource.org/licenses/MIT The MIT License
 * @version 0.2
 */
final class PicoUsers extends AbstractPicoPlugin
{
	private $user;
	private $users;
	private $rights;
	private $base_url;
	private $hash_type;

    /**
     * This plugin is enabled by default
     *
     * @see AbstractPicoPlugin::$enabled
     * @var boolean
     */
	 protected $enabled = true;

    /**
     * Triggered after Pico has read its configuration
     *
     * @see    Pico::getConfig()
     * @param  array &$config array of config variables
     * @return void
     */
	 public function onConfigLoaded(array &$config)
	 {
		$this->base_url = rtrim($config['base_url'], '/') . '/';
		$this->users = @$config['users'];
		$this->rights = @$config['rights'];
		if (isset($config['hash_type']) && in_array($config['hash_type'], hash_algos())) {
			$this->hash_type = $config['hash_type'];
		} else {
			$this->hash_type = "sha1";
		}

		$this->user = '';
		$this->check_login();
	}
    /**
     * Triggered after Pico has evaluated the request URL
     *
     * @see    Pico::getRequestUrl()
     * @param  string &$url part of the URL describing the requested contents
     * @return void
     */
	 public function onRequestUrl(&$url)
	 {
		$page_url = rtrim($url, '/');
		if (!$this->is_authorized($this->base_url . $page_url)) {
			$url = '403';
			header('HTTP/1.1 403 Forbidden');
		}
	}
    /**
     * Triggered after Pico has read all known pages
     *
     * See {@link DummyPlugin::onSinglePageLoaded()} for details about the
     * structure of the page data.
     *
     * @see    Pico::getPages()
     * @see    Pico::getCurrentPage()
     * @see    Pico::getPreviousPage()
     * @see    Pico::getNextPage()
     * @param  array[]    &$pages        data of all known pages
     * @param  array|null &$currentPage  data of the page being served
     * @param  array|null &$previousPage data of the previous page
     * @param  array|null &$nextPage     data of the next page
     * @return void
     */
    public function onPagesLoaded(
        array &$pages,
        array &$currentPage = null,
        array &$previousPage = null,
        array &$nextPage = null
    ) {
		// get sorted list of urls, for :
		// TODO prev_page & next_page as prev and next allowed pages
		$pages_urls = array();
		foreach ($pages as $p) {
			$pages_urls[] = $p['url'];
		}
		asort($pages_urls);

		foreach ($pages_urls as $page_id => $page_url ) {
			if (!$this->is_authorized(rtrim($page_url, '/'))) {
				unset($pages[$page_id]);
			}
		}
	}
    /**
     * Triggered before Pico renders the page
     *
     * @see    Pico::getTwig()
     * @see    DummyPlugin::onPageRendered()
     * @param  Twig_Environment &$twig          twig template engine
     * @param  array            &$twigVariables template variables
     * @param  string           &$templateName  file name of the template
     * @return void
     */
    public function onPageRendering(Twig_Environment &$twig, array &$twigVariables, &$templateName)
    {
		$twigVariables['login_form'] = $this->html_form();
		if ($this->user) {
			$twigVariables['user'] = $this->user;
			$twigVariables['username'] = basename($this->user);
			$twigVariables['usergroup'] = dirname($this->user);
		}
	}


	// CORE ---------------

	/*
	 * Check logout/login actions and session login.
	 */
	function check_login()
	{
		if (session_status() == PHP_SESSION_NONE) {
		    session_start();
		}
		$fp = $this->fingerprint();
		$post = $_POST; // to sanitize ?

		// logout action
		if (isset($post['logout'])) {
			unset($_SESSION[$fp]);
			return;
		}

		// login action
		if (isset($post['login'])
		&& isset($post['password'])) {
			return $this->login($post['login'], $post['password'], $fp);
		}

		// session login (already logged)

		if (!isset($_SESSION[$fp])) return;

		$name = $_SESSION[$fp]['name'];
		$pass = $_SESSION[$fp]['password'];

		$logged = $this->login($name, $pass, $fp);
		if ($logged) return true;

		unset($_SESSION[$fp]);
		return;
	}
	/**
	 * Return session fingerprint hash.
	 * @return string
	 */
	function fingerprint()
	{
		return hash($this->hash_type, 'pico'
				.$_SERVER['HTTP_USER_AGENT']
				.$_SERVER['REMOTE_ADDR']
				.$_SERVER['SCRIPT_NAME']
				.session_id());
	}
	/**
	 * Try to login with the given name and password.
	 * @param string $name the login name
	 * @param string $pass the login password
	 * @param string $fp session fingerprint hash
	 * @return boolean operation result
	 */
	function login($name, $pass, $fp)
	{
		$users = $this->search_users($name, hash($this->hash_type, $pass));
		if (!$users) return false;
		// register
		$this->user = $users[0];
		$_SESSION[$fp]['name'] = $name;
		$_SESSION[$fp]['password'] = $pass;
		return true;
	}
	/*
	 * Return a simple login / logout form.
	 */
	function html_form()
	{
		if (!$this->user) return '
		<form method="post" action="">
			<input type="text" name="login" />
			<input type="password" name="password" />
			<input type="submit" value="login" />
		</form>';

		$userGroup = dirname($this->user);
		return basename($this->user) . ($userGroup != '.' ? "($userGroup)":'') . '
		<form method="post" action="" >
			<input type="submit" name="logout" value="logout" />
		</form>';
	}

	/**
	 * Return a list of users and passwords from the configuration file,
	 * corresponding to the given user name.
	 * @param  string $name  the user name, like "username"
	 * @param  string $pass  the user password hash (hash)
	 * @return array        the list of results in pairs "path/group/username" => "hash"
	 */
	function search_users( $name, $pass = null, $users = null , $path = '' )
	{
		if ($users === null) $users = $this->users;
		if ($path) $path .= '/';
		$results = array();
		foreach ($users as $key => $val)
		{
			if (is_array($val)) {
				$results = array_merge(
					$results,
					$this->search_users($name, $pass, $val, $path.$key)
				);
				continue;
			}
			if (($name === null || $name === $key )
			 && ($pass === null || $pass === $val )) {
				$results[] = $path.$name;
			}
		}
		return $results;
	}

	/**
	 * Return if the user is allowed to see the given page url.
	 * @param  string  $url a page url
	 * @return boolean
	 */
	private function is_authorized($url)
	{
		if (!$this->rights) return true;
		foreach ($this->rights as $auth_path => $auth_user )
		{
			// url is concerned by this rule and user is not (unauthorized)
			if ($this->is_parent_path($this->base_url . $auth_path, $url)
			&& !$this->is_parent_path($auth_user, $this->user) )
			{
				return false;
			}
		}
		return true;
	}
	/**
	 * Return if a path is parent of another.
	 * 	some/path is parent of some/path/child
	 *  some/path is not parent of some/another/path
	 * @param  string  $parent the parent (shorter) path
	 * @param  string  $child  the child (longer) path
	 * @return boolean
	 */
	private static function is_parent_path($parent, $child)
	{
		if (!$parent || !$child) return false;
		if (	$parent == $child) return true;

		if (strpos($child, $parent) === 0) {
			if (substr($parent,-1) == '/') return true;
			elseif ($child[strlen($parent)] == '/') return true;
		}
		return false;
	}
}
?>
