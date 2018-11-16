<?php
/**
 * password_hash PHP<5.5 compatibility
 * @link https://github.com/ircmaxell/password_compat
 */
require_once('password.php');
/**
 * A hierarchical users and rights system plugin for Pico 2.
 *
 * @author  Nicolas Liautaud
 * @link    https://github.com/nliautaud/pico-users
 * @link    http://picocms.org
 * @license http://opensource.org/licenses/MIT The MIT License
 */
class PicoUsers extends AbstractPicoPlugin
{
    const API_VERSION = 2;

    private $user;
    private $users;
    private $rights;
    private $base_url;

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
        $this->user = '';
        $this->checkLogin();
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
        if (!$this->hasRight($url, true)) {
            $url = '403';
            header('HTTP/1.1 403 Forbidden');
        }
    }
    /**
     * Hide 403 and unauthorized pages.
     *
     * Triggered after Pico has discovered all known pages
     *
     * @see DummyPlugin::onPagesLoading()
     * @see DummyPlugin::onPagesLoaded()
     * @param array[] &$pages list of all known pages
     * @return void
     */
    public function onPagesDiscovered(array &$pages)
    {
        foreach ($pages as $id => $page) {
            if ($id == '403' || !$this->hasRight($page['url'], true)) {
                unset($pages[$id]);
            }
        }
    }
    /**
     * Add various twig variables.
     *
     * Triggered before Pico renders the page
     *
     * @see DummyPlugin::onPageRendered()
     * @param string &$templateName  file name of the template
     * @param array  &$twigVariables template variables
     * @return void
     */
    public function onPageRendering(&$templateName, array &$twigVariables)
    {
        $twigVariables['login_form'] = $this->htmlLoginForm();
        if ($this->user) {
            $twigVariables['user'] = $this->user;
            $twigVariables['username'] = basename($this->user);
            $twigVariables['usergroup'] = dirname($this->user);
        }
    }
    /**
     * Add {{ user_has_right('rule') }} twig function.
     *
     * Triggered when Pico registers the twig template engine
     *
     * @see Pico::getTwig()
     * @param Twig_Environment &$twig Twig instance
     * @return void
     */
    public function onTwigRegistered(Twig_Environment &$twig)
    {
        $twig->addFunction(new Twig_SimpleFunction('user_has_right', array($this, 'hasRight')));
    }


    // CORE ---------------

    /*
     * Check logout/login actions and session login.
     */
    private function checkLogin()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $fp = $this->fingerprint();

        // logout action
        if (isset($_POST['logout'])) {
            unset($_SESSION[$fp]);
            return;
        }

        // login action
        if (isset($_POST['login'])
        && isset($_POST['pass'])) {
            $users = $this->searchUsers($_POST['login'], $_POST['pass']);
            if (!$users) {
                return;
            }
            $this->logUser($users[0], $fp);
            return;
        }

        // session login (already logged)
        if (!isset($_SESSION[$fp])) {
            return;
        }

        $path = $_SESSION[$fp]['path'];
        $hash = $_SESSION[$fp]['hash'];
        $user = $this->getUser($path);

        if ($user['hash'] === $hash) {
            $this->logUser($user, $fp);
        } else {
            unset($_SESSION[$fp]);
        }
    }
    /**
     * Return session fingerprint hash.
     * @return string
     */
    private function fingerprint()
    {
        return hash('sha256', 'pico'
                .$_SERVER['HTTP_USER_AGENT']
                .$_SERVER['REMOTE_ADDR']
                .$_SERVER['SCRIPT_NAME']
                .session_id());
    }
    /**
     * Register the given user infos.
     * @param string $user the user infos
     * @param string $fp session fingerprint hash
     */
    private function logUser($user, $fp)
    {
        $this->user = $user['path'];
        $_SESSION[$fp] = $user;
    }
    /*
     * Return a simple login / logout form.
     */
    private function htmlLoginForm()
    {
        if (!$this->user) {
            return '
            <form method="post" action="">
                <input type="text" name="login" />
                <input type="password" name="pass" />
                <input type="submit" value="login" />
            </form>';
        }
        $userGroup = dirname($this->user);
        return basename($this->user) . ($userGroup != '.' ? " ($userGroup)":'') . '
        <form method="post" action="" >
            <input type="submit" name="logout" value="logout" />
        </form>';
    }

    /**
     * Return a list of users and passwords from the configuration file,
     * corresponding to the given user name.
     * @param  string $name  the user name, like "username"
     * @param  string $pass  the user pass
     * @return array  the list of results in pairs "path/group/username" => "hash"
     */
    private function searchUsers($name, $pass, $users = null, $path = '')
    {
        if ($users === null) {
            $users = $this->users;
        }
        if ($path) {
            $path .= '/';
        }
        $results = array();
        foreach ($users as $username => $userdata) {
            if (is_array($userdata)) {
                $results = array_merge(
                    $results,
                    $this->searchUsers($name, $pass, $userdata, $path.$username)
                );
                continue;
            }
            if ($name !== null && $name !== $username) {
                continue;
            }
            if (!password_verify($pass, $userdata)) {
                continue;
            }
            $results[] = array(
                'path' => $path.$username,
                'hash' => $userdata);
        }
        return $results;
    }
     /**
      * Return a given user data.
      * @param  string $name  the user path, like "foo/bar"
      * @return array  the user data
      */
    private function getUser($path)
    {
        $parts = explode('/', $path);
        $curr = $this->users;
        foreach ($parts as $part) {
            if (!isset($curr[$part])) {
                return false;
            }
            $curr = $curr[$part];
        }
        return array(
            'path' => $path,
            'hash' => $curr
        );
    }
    /**
     * Return if the user has the given right.
     * @param  string  $rule
     * @param  string  $default The default status if no corresponding rule is found.
     * @return boolean
     */
    public function hasRight($rule, $default = false)
    {
        $rule = ltrim($rule, '/');
        $rule = preg_replace('(\/{2,})', '/', $rule);
        if (!$this->rights) {
            return $default;
        }
        foreach ($this->rights as $auth_rule => $auth_user) {
            if ($this->isParentPath($auth_rule, $rule)) {
                $isCurrentUser = $this->isParentPath($auth_user, $this->user);
                if ($default == true && !$isCurrentUser) {
                    return false;
                }
                if ($default == false && $isCurrentUser) {
                    return true;
                }
            }
        }
        return $default;
    }
    /**
     * Return if a path is parent of another.
     * some/path is parent of some/path/child
     * some/path is not parent of some/another/path
     * @param  string  $parent the parent (shorter) path
     * @param  string  $child  the child (longer) path
     * @return boolean
     */
    private static function isParentPath($parent, $child)
    {
        if (!$parent || !$child) {
            return false;
        }
        if ($parent == $child) {
            return true;
        }
        if (strpos($child, $parent) === 0) {
            if (substr($parent, -1) == '/') {
                return true;
            } elseif ($child[strlen($parent)] == '/') {
                return true;
            }
        }
        return false;
    }
}
