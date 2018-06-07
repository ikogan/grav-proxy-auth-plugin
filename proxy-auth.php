<?php
namespace Grav\Plugin;

use Grav\Common\Config;
use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\User\User;

use Grav\Plugin\Login;

use Grav\Common\Debugger;

class ProxyAuthplugin extends Plugin {
    public static function getSubscribedEvents() {
        return [
            'onTwigSiteVariables'       => ['twigSiteVariables', 0],
            'onPageInitialized'         => ['checkAuthentication', 1],
            'onUserLogout'              => ['userLogout', 1]
        ];
    }

    public static function getHeader($key) {
        if(empty($key)) {
            return NULL;
        }

        return $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))];
    }

    public function debug($message) {
        $this -> grav['debugger'] -> addMessage($message, 'debug');
    }

    public function getLoginUrl() {
        $url = $this -> config -> get('plugins.proxy-auth.url.login', NULL);

        if(!empty($url)) {
            $url = str_replace('${CURRENT_URL}', $this -> grav['uri'], $url);
        }

        return $url;
    }

    public function getLogoutUrl($redirectUrl = NULL) {
        $url = $this -> config -> get('plugins.proxy-auth.url.logout', NULL);

        if(!empty($url)) {
            $url = str_replace('${CURRENT_URL}', $this -> grav['uri'], $url);
        }

        return $url;
    }

    public function twigSiteVariables() {
        $this -> grav['twig'] -> login_url = $this -> getLoginUrl();
    }

    public function checkAuthentication() {
        $user = $this -> grav['user'];

        if($user -> authenticated) {
            return;
        }

        $user = $this -> extractFromHeaders();

        if(!empty($user)) {
            $this -> grav['debugger'] -> addMessage($user, 'debug', false);
            $this -> authenticate($user);
        } else if($this -> grav['page'] -> header('access')) {
            $loginUrl = $this -> getLoginUrl();

            if(!empty($loginUrl)) {
                $this -> debug("No user authenticated but we're in admin so forcing authentication through " . $loginUrl);
                $this -> grav -> redirectLangSafe($loginUrl);
            }
        } else {
            $this -> debug('No user authenticated by proxy.');
        }
    }

    public function extractUsernameFromHeaders() {
        return self::getHeader($this -> config -> get('plugins.proxy-auth.headers.username', 'X-Remote-User'));
    }

    public function extractFromHeaders() {
        $username = $this -> extractUsernameFromHeaders();

        if(empty($username)) {
            return NULL;
        }

        $groupSeparator = $this -> config -> get('plugins.proxy-auth.groupSeparator' , ',');
        $required = $this -> config -> get('plugins.proxy-auth.required.groups', NULL);

        $groups = explode($groupSeparator, self::getHeader($this -> config -> get('plugins.proxy-auth.headers.groups', 'X-Remote-Groups')));

        if(!empty($required)) {
            $this -> debug('User requires groups ' . implode(',', $required));

            if(empty($groups) || empty(array_intersect($groups, $required))) {
                $this -> debug('User not authorized.');
                return NULL;
            }
        }

        $user = array(
            'username' => $username,
            'language' => 'en',
            'authenticated' => true,
            'access' => array('site' => array('login' => 'true'))
        );

        $displayName = self::getHeader($this -> config -> get('plugins.proxy-auth.headers.displayName', 'X-Remote-Display-Name'));
        $email = self::getHeader($this -> config -> get('plugins.proxy-auth.headers.email', 'X-Remote-Email'));

        if(!empty($displayName)) {
            $user['fullname'] = $displayName;
        }

        if(!empty($email)) {
            $user['email'] = $email;
        }

        if(!empty($groups)) {
            $user['groups'] = $groups;
        }

        return new User($user);
    }

    public function authenticate($user) {
        $this -> grav['session'] -> user = $user;
        unset($this -> grav['user']);
        $this -> grav['user'] = $user;
    }

    public function userLogout() {
        $logoutUrl = $this -> getLogoutUrl();

        if(!empty($logoutUrl) && !empty($this -> extractUsernameFromHeaders())) {
            $this -> grav -> redirectLangSafe($logoutUrl);
            $this -> grav -> shutdown();
        }
    }
}
