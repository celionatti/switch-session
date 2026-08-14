<?php

declare(strict_types=1);

namespace Switch\Session;

use Switch\Session\Store\SessionStore;

/**
 * Static facade for Session management.
 */
class Session
{
    private static ?SessionStore $store = null;

    public static function setStore(SessionStore $store): void
    {
        self::$store = $store;
    }

    public static function getStore(): SessionStore
    {
        return self::$store ??= SessionManager::getInstance()->store();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getStore()->get($key, $default);
    }

    public static function put(string|array $key, mixed $value = null): void
    {
        self::getStore()->put($key, $value);
    }

    public static function has(string $key): bool
    {
        return self::getStore()->has($key);
    }

    public static function exists(string $key): bool
    {
        return self::getStore()->exists($key);
    }

    public static function forget(string|array $keys): void
    {
        self::getStore()->forget($keys);
    }

    public static function flush(): void
    {
        self::getStore()->flush();
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::getStore()->pull($key, $default);
    }

    public static function flash(string $key, mixed $value = true): void
    {
        self::getStore()->flash($key, $value);
    }

    public static function now(string $key, mixed $value = true): void
    {
        self::getStore()->now($key, $value);
    }

    public static function reflash(): void
    {
        self::getStore()->reflash();
    }

    public static function keep(array|string $keys = null): void
    {
        self::getStore()->keep($keys);
    }

    public static function token(): string
    {
        return self::getStore()->token();
    }

    public static function regenerate(bool $destroy = false): bool
    {
        return self::getStore()->regenerate($destroy);
    }

    public static function id(): string
    {
        return self::getStore()->getId();
    }

    public static function all(): array
    {
        return self::getStore()->all();
    }
}
