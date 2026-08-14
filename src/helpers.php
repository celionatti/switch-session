<?php

declare(strict_types=1);

use Switch\Session\Cookie\Cookie;
use Switch\Session\Session;
use Switch\Session\SessionManager;
use Switch\Session\Store\SessionStore;

if (!function_exists('session')) {
    /**
     * Get / set session values or retrieve the SessionStore instance.
     *
     * @param string|array<string, mixed>|null $key
     * @param mixed $default
     * @return mixed|SessionStore
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        $store = Session::getStore();

        if ($key === null) {
            return $store;
        }

        if (is_array($key)) {
            $store->put($key);
            return null;
        }

        return $store->get($key, $default);
    }
}

if (!function_exists('cookie')) {
    /**
     * Create a new Cookie instance or queue a cookie into the global CookieJar.
     *
     * @param string $name
     * @param string $value
     * @param int $minutes
     * @param array<string, mixed> $options
     * @return Cookie
     */
    function cookie(string $name, string $value = '', int $minutes = 0, array $options = []): Cookie
    {
        $cookie = Cookie::make(
            $name,
            $value,
            $minutes,
            $options['path'] ?? '/',
            $options['domain'] ?? null,
            $options['secure'] ?? false,
            $options['http_only'] ?? true,
            $options['raw'] ?? false,
            $options['same_site'] ?? 'Lax',
            $options['partitioned'] ?? false
        );

        if ($minutes !== 0 || !empty($value)) {
            SessionManager::getInstance()->getCookieJar()->queue($cookie);
        }

        return $cookie;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the active CSRF token string.
     */
    function csrf_token(): string
    {
        return Session::token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden HTML input field containing the CSRF token.
     */
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('flash')) {
    /**
     * Flash a message to the session or get the FlashBag instance.
     *
     * @param string|null $type ('success', 'error', 'warning', 'info')
     * @param string|null $message
     * @param string|null $title
     * @param array<string, mixed> $options
     * @return \Switch\Session\Flash\FlashBag|\Switch\Session\Flash\FlashMessage
     */
    function flash(?string $type = null, ?string $message = null, ?string $title = null, array $options = []): mixed
    {
        static $bag = null;
        $bag ??= new \Switch\Session\Flash\FlashBag();

        if ($type === null) {
            return $bag;
        }

        if ($message === null) {
            return $bag->get($type);
        }

        return $bag->add($type, $message, $title, $options);
    }
}

if (!function_exists('flash_render')) {
    /**
     * Render the flash messages to HTML (as toasts or alerts).
     *
     * @param string $mode 'toast' or 'alert'
     * @param array<string, mixed> $options
     * @return string
     */
    function flash_render(string $mode = 'toast', array $options = []): string
    {
        $renderer = new \Switch\Session\Flash\FlashRenderer();
        return $renderer->render($mode, $options);
    }
}

if (!function_exists('flash_messages')) {
    /**
     * Alias for flash_render.
     *
     * @param string $mode 'toast' or 'alert'
     * @param array<string, mixed> $options
     * @return string
     */
    function flash_messages(string $mode = 'toast', array $options = []): string
    {
        return flash_render($mode, $options);
    }
}

