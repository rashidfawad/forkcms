<?php

namespace Common;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Frontend\Core\Engine\Model as FrontendModel;

/**
 * This is our extended version of SpoonCookie
 */
class Cookie
{
    /**
     * Stores a value in a cookie, by default the cookie will expire in one day.
     *
     * @param string $key A name for the cookie.
     * @param mixed $value The value to be stored. Keep in mind that they will be serialized.
     * @param int $time The number of seconds that this cookie will be available, 30 days is the default.
     * @param string $path The path on the server in which the cookie will
     *                         be available. Use / for the entire domain, /foo
     *                         if you just want it to be available in /foo.
     * @param string $domain The domain that the cookie is available on. Use
     *                         .example.com to make it available on all
     *                         subdomains of example.com.
     * @param bool $secure Should the cookie be transmitted over a
     *                         HTTPS-connection? If true, make sure you use
     *                         a secure connection, otherwise the cookie won't be set.
     * @param bool $httpOnly Should the cookie only be available through
     *                         HTTP-protocol? If true, the cookie can't be
     *                         accessed by Javascript, ...
     *
     * @return bool If set with success, returns true otherwise false.
     */
    public static function set(
        $key,
        $value,
        $time = 2592000,
        $path = '/',
        $domain = null,
        $secure = null,
        $httpOnly = true
    ): bool {
        // redefine
        $key = (string) $key;
        $value = serialize($value);
        $time = time() + (int) $time;
        $path = (string) $path;
        $httpOnly = (bool) $httpOnly;

        // when the domain isn't passed and the url-object is available we can set the cookies for all subdomains
        if ($domain === null && FrontendModel::requestIsAvailable()) {
            $domain = '.' . FrontendModel::getRequest()->getHost();
        }

        // when the secure-parameter isn't set
        if ($secure === null) {
            /*
             detect if we are using HTTPS, this wil only work in Apache, if you are using nginx you should add the
             code below into your config:
                 ssl on;
                fastcgi_param HTTPS on;

             for lighttpd you should add:
                 setenv.add-environment = ("HTTPS" => "on")
             */
            $secure = (isset($_SERVER['HTTPS']) && mb_strtolower($_SERVER['HTTPS']) === 'on');
        }

        // set cookie
        $cookie = setcookie($key, $value, $time, $path, $domain, $secure, $httpOnly);

        // problem occurred
        return $cookie !== false;
    }

    /**
     * Deletes one or more cookies.
     *
     * This overwrites the spoon cookie method and adds the same functionality
     * as in the set method to automatically set the domain.
     */
    public static function delete(): void
    {
        $domain = null;
        if (FrontendModel::requestIsAvailable()) {
            $domain = '.' . FrontendModel::getRequest()->getHost();
        }

        foreach (func_get_args() as $argument) {
            // multiple arguments are given
            if (is_array($argument)) {
                foreach ($argument as $key) {
                    self::delete($key);
                }

                continue;
            }

            // delete the given cookie
            unset($_COOKIE[(string) $argument]);
            setcookie((string) $argument, null, 1, '/', $domain);
        }
    }

    /**
     * Has the visitor allowed cookies?
     *
     * @return bool
     */
    public static function hasAllowedCookies(): bool
    {
        return (self::exists('cookie_bar_agree') && self::get('cookie_bar_agree'));
    }

    /**
     * Has the cookiebar been hidden by the visitor
     *
     * @return bool
     */
    public static function hasHiddenCookieBar(): bool
    {
        return (self::exists('cookie_bar_hide') && self::get('cookie_bar_hide'));
    }

    public static function exists()
    {
        // loop all arguments
        foreach (func_get_args() as $argument) {
            // array element
            if (is_array($argument)) {
                // loop the keys
                foreach ($argument as $key) {
                    // does NOT exist
                    if (!isset($_COOKIE[(string) $key])) {
                        return false;
                    }
                }
            } // other type(s)
            else {
                // does NOT exist
                if (!isset($_COOKIE[(string) $argument])) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function get($key)
    {
        // redefine key
        $key = (string) $key;

        // cookie doesn't exist
        if (!self::exists($key)) {
            return false;
        }

        // fetch base value
        $value = (get_magic_quotes_gpc()) ? stripslashes($_COOKIE[$key]) : $_COOKIE[$key];

        // unserialize
        $actualValue = @unserialize($value);

        // unserialize failed
        if ($actualValue === false && serialize(false) != $value) {
            throw new SpoonCookieException(
                'The value of the cookie "' . $key . '" could not be retrieved. This might indicate that it has been tampered with OR the cookie was initially not set using SpoonCookie.'
            );
        }
        // everything is fine
    }
}
