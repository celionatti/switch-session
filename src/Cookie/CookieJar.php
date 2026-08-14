<?php

declare(strict_types=1);

namespace Switch\Session\Cookie;

use Psr\Http\Message\ResponseInterface;

class CookieJar
{
    /**
     * @var array<string, Cookie> Queued cookies to be sent with response
     */
    private array $queued = [];

    /**
     * Queue a cookie for sending.
     */
    public function queue(Cookie|string $cookie, mixed ...$args): Cookie
    {
        if (is_string($cookie)) {
            $cookie = Cookie::make($cookie, ...$args);
        }

        $this->queued[$cookie->getName()] = $cookie;
        return $cookie;
    }

    /**
     * Queue a cookie to be deleted.
     */
    public function expire(string $name, string $path = '/', ?string $domain = null): Cookie
    {
        return $this->queue(Cookie::forget($name, $path, $domain));
    }

    /**
     * Check if a cookie is queued.
     */
    public function hasQueued(string $name): bool
    {
        return isset($this->queued[$name]);
    }

    /**
     * Get a queued cookie.
     */
    public function getQueued(string $name): ?Cookie
    {
        return $this->queued[$name] ?? null;
    }

    /**
     * Remove a cookie from the queue.
     */
    public function unqueue(string $name): void
    {
        unset($this->queued[$name]);
    }

    /**
     * Get all queued cookies.
     *
     * @return array<string, Cookie>
     */
    public function getQueuedCookies(): array
    {
        return $this->queued;
    }

    /**
     * Flush all queued cookies.
     */
    public function flush(): void
    {
        $this->queued = [];
    }

    /**
     * Attach all queued cookies to an outgoing PSR-7 Response.
     */
    public function attachToResponse(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->queued as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue());
        }

        return $response;
    }
}
