<?php

declare(strict_types=1);

namespace Switch\Session\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Session\Cookie\Cookie;
use Switch\Session\Exception\TokenMismatchException;
use Switch\Session\Session;
use Switch\Session\Store\SessionStore;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected array $except = [];

    /**
     * @param array<int, string> $except
     */
    public function __construct(array $except = [])
    {
        $this->except = array_merge($this->except, $except);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            $response = $handler->handle($request);
            return $this->addCookieToResponse($request, $response);
        }

        if ($this->tokensMatch($request)) {
            $response = $handler->handle($request);
            return $this->addCookieToResponse($request, $response);
        }

        throw new TokenMismatchException();
    }

    /**
     * Check if request method is a read-only HTTP verb (GET, HEAD, OPTIONS).
     */
    protected function isReading(ServerRequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['HEAD', 'GET', 'OPTIONS'], true);
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray(ServerRequestInterface $request): bool
    {
        $uri = trim($request->getUri()->getPath(), '/');

        foreach ($this->except as $except) {
            $except = trim($except, '/');

            if ($except === $uri) {
                return true;
            }

            if (str_ends_with($except, '*')) {
                $prefix = rtrim($except, '*');
                if (str_starts_with($uri, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch(ServerRequestInterface $request): bool
    {
        /** @var SessionStore|null $session */
        $session = $request->getAttribute('session') ?? Session::getStore();

        if ($session === null) {
            return false;
        }

        $token = $this->getTokenFromRequest($request);

        return $session->verifyToken($token);
    }

    /**
     * Get the CSRF token from the request.
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['_token']) && is_string($body['_token'])) {
            return $body['_token'];
        }

        if ($request->hasHeader('X-CSRF-TOKEN')) {
            return $request->getHeaderLine('X-CSRF-TOKEN');
        }

        if ($request->hasHeader('X-XSRF-TOKEN')) {
            return $request->getHeaderLine('X-XSRF-TOKEN');
        }

        return null;
    }

    /**
     * Add the CSRF token to the response cookies for client JS.
     */
    protected function addCookieToResponse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var SessionStore|null $session */
        $session = $request->getAttribute('session') ?? Session::getStore();

        if ($session !== null && $session->isStarted()) {
            $cookie = new Cookie(
                'XSRF-TOKEN',
                $session->token(),
                120,
                '/',
                null,
                false,
                false, // httpOnly = false so JS can read XSRF-TOKEN
                false,
                'Lax'
            );

            return $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue());
        }

        return $response;
    }
}
