<?php
/**
 * A hierarchical users and rights system plugin for Pico.
 *
 * @author	Nicolas Liautaud
 * @link	http://nliautaud.fr
 * @link    http://pico.dev7studios.com
 * @license http://opensource.org/licenses/MIT
 */
class _Pico_Users
{
	private $user;
	private $users;
	private $rights;
	private $base_url;

	// Pico hooks ---------------

	/**
	 * Store settings and define the current user.
	 */
	public function config_loaded(&$settings)
	{
		$this->base_url = $settings['base_url'];
		$this->users = @$settings['users'];
		$this->rights = @$settings['rights'];

		$this->user = '';
		$this->check_login();
	}
	/**
	 * If the requested url is unauthorized for the current user
	 * display page "403" and send 403 headers.
	 */
	public function request_url(&$url)
	{
		$page_url = rtrim($url, '/');
		if (!$this->is_authorized($this->base_url . '/' . $page_url)) {
			$url = '403';
			header('HTTP/1.1 403 Forbidden');
		}
	}
	/**
	 * Filter the list of pages according to rights of current user.
	 */
	public function get_pages(&$pages, &$current_page, &$prev_page, &$next_page)
	{
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
	 * Register a basic login form and user path in Twig variables.
	 */
	public function before_render(&$twig_vars, &$twig)
	{
		$twig_vars['login_form'] = $this->html_form();
		$twig_vars['user'] = $this->user;
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
		return sha1('pico'
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
		$users = $this->search_users($name, sha1($pass));
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

		return basename($this->user) . ' (' . dirname($this->user) . ')
		<form method="post" action="" >
			<input type="submit" value="logout" />
		</form>';
	}

	/**
	 * Return a list of users and passwords from the configuration file,
	 * corresponding to the given user name.
	 * @param  string $name  the user name, like "username"
	 * @param  string $pass  the user password hash (sha1)
	 * @return array        the list of results in pairs "path/group/username" => "hash"
	 */
	function search_users( $name, $pass = null, $users = null , $path = '' )
	{
		if (!$users) $users = $this->users;
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
			if ($this->is_parent_path($this->base_url.'/'.$auth_path, $url)
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