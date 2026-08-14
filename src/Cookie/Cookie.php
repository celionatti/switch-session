<?php

declare(strict_types=1);

namespace Switch\Session\Cookie;

use DateTimeInterface;

class Cookie
{
    private string $name;
    private string $value;
    private int $expires = 0;
    private string $path = '/';
    private ?string $domain = null;
    private bool $secure = false;
    private bool $httpOnly = true;
    private ?string $sameSite = 'Lax';
    private bool $partitioned = false;
    private bool $raw = false;

    public function __construct(
        string $name,
        string $value = '',
        int|DateTimeInterface $expire = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = 'Lax',
        bool $partitioned = false
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->path = empty($path) ? '/' : $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->raw = $raw;
        $this->sameSite = $sameSite !== null ? ucfirst(strtolower($sameSite)) : null;
        $this->partitioned = $partitioned;

        if ($expire instanceof DateTimeInterface) {
            $this->expires = $expire->getTimestamp();
        } elseif ($expire > 0 && $expire < 31536000) {
            // Treat relative minutes or small seconds as future timestamp if <= 1 year
            $this->expires = time() + ($expire * 60);
        } else {
            $this->expires = $expire;
        }
    }

    /**
     * Create a new Cookie instance.
     */
    public static function make(
        string $name,
        string $value = '',
        int|DateTimeInterface $expire = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = 'Lax',
        bool $partitioned = false
    ): self {
        return new self($name, $value, $expire, $path, $domain, $secure, $httpOnly, $raw, $sameSite, $partitioned);
    }

    /**
     * Create a cookie that lasts for 5 years ("forever").
     */
    public static function forever(
        string $name,
        string $value = '',
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = 'Lax'
    ): self {
        return new self($name, $value, time() + (5 * 365 * 24 * 60 * 60), $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    /**
     * Create an expired cookie to delete a client cookie.
     */
    public static function forget(
        string $name,
        string $path = '/',
        ?string $domain = null
    ): self {
        return new self($name, '', time() - 86400, $path, $domain);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }

    public function isPartitioned(): bool
    {
        return $this->partitioned;
    }

    public function isRaw(): bool
    {
        return $this->raw;
    }

    public function isExpired(): bool
    {
        return $this->expires > 0 && $this->expires < time();
    }

    /**
     * Convert the cookie to a Set-Cookie header string.
     */
    public function toHeaderValue(): string
    {
        $value = $this->raw ? $this->value : rawurlencode($this->value);
        $parts = ["{$this->name}={$value}"];

        if ($this->expires !== 0) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $this->expires);
            $maxAge = max(0, $this->expires - time());
            $parts[] = "Max-Age={$maxAge}";
        }

        if (!empty($this->path)) {
            $parts[] = "Path={$this->path}";
        }

        if (!empty($this->domain)) {
            $parts[] = "Domain={$this->domain}";
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if (!empty($this->sameSite)) {
            $parts[] = "SameSite={$this->sameSite}";
        }

        if ($this->partitioned) {
            $parts[] = 'Partitioned';
        }

        return implode('; ', $parts);
    }

    public function __toString(): string
    {
        return $this->toHeaderValue();
    }
}
