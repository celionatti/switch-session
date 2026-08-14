<?php

declare(strict_types=1);

namespace Switch\Session\Handler;

use SessionHandlerInterface;
use Switch\Session\Cookie\CookieJar;

class CookieSessionHandler implements SessionHandlerInterface
{
    private CookieJar $cookieJar;
    private int $minutes;

    public function __construct(CookieJar $cookieJar, int $minutes = 120)
    {
        $this->cookieJar = $cookieJar;
        $this->minutes = $minutes;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $cookie = $_COOKIE[$id] ?? null;
        if (!is_string($cookie) || empty($cookie)) {
            return '';
        }

        $decoded = base64_decode($cookie, true);
        return $decoded !== false ? $decoded : '';
    }

    public function write(string $id, string $data): bool
    {
        $this->cookieJar->queue($id, base64_encode($data), $this->minutes);
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->cookieJar->expire($id);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0; // Cookies are expired client-side by browser
    }
}
