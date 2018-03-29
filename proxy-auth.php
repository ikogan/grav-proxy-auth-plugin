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
            'onPageInitialized' => ['checkAuthentication', 1],
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

    public function checkAuthentication() {
        $user = $this -> grav['user'];

        if($user -> authenticated) {
            return;
        }

        $user = $this -> extractFromHeaders();

        if(!empty($user)) {
            $this -> grav['debugger'] -> addMessage($user, 'debug', false); 
            $this -> authenticate($user);
        } else {
            $this -> debug('No user authenticated by proxy.');    
        }
    }

    public function extractFromHeaders() {
        $username = self::getHeader($this -> config -> get('plugins.proxy-auth.headers.username', 'X-Remote-User'));

        if(empty($username)) {
            return NULL;
        }

        $groupSeparator = $this -> config -> get('plugins.proxy-auth.groupSeparator' , ',');
        $required = $this -> config -> get('plugins.proxy-auth.required.groups', NULL);

        $groups = explode($groupSeparator, self::getHeader($this -> config -> get('plugins.proxy-auth.headers.groups', 'X-Remote-Groups')));
            
        if(!empty($required)) {
            $this -> debug('User requires groups ' . implode(',', $required));

            if(empty($groups) || empty(array_intersect($groups, $required))) {
                $this -> Debug('User not authorized.');
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
}