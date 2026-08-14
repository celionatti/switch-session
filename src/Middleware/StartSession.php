<?php

declare(strict_types=1);

namespace Switch\Session\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Session\Cookie\Cookie;
use Switch\Session\Session;
use Switch\Session\SessionManager;
use Switch\Session\Store\SessionStore;

class StartSession implements MiddlewareInterface
{
    private SessionManager $manager;

    public function __construct(?SessionManager $manager = null)
    {
        $this->manager = $manager ?? SessionManager::getInstance();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieName = $this->manager->getSessionCookieName();
        $cookies = $request->getCookieParams();
        $sessionId = $cookies[$cookieName] ?? ($_COOKIE[$cookieName] ?? null);

        /** @var SessionStore $session */
        $session = $this->manager->store();

        if (is_string($sessionId) && !empty($sessionId)) {
            $session->setId($sessionId);
        }

        $session->start();
        Session::setStore($session);

        $request = $request->withAttribute('session', $session);

        $response = $handler->handle($request);

        $session->save();

        // 1. Attach session cookie to response
        $cookie = new Cookie(
            $cookieName,
            $session->getId(),
            (int) $this->manager->getConfig('lifetime', 120),
            (string) $this->manager->getConfig('path', '/'),
            $this->manager->getConfig('domain'),
            (bool) $this->manager->getConfig('secure', false),
            (bool) $this->manager->getConfig('http_only', true),
            false,
            $this->manager->getConfig('same_site', 'Lax')
        );

        $response = $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue());

        // 2. Attach any additional queued cookies from CookieJar
        $response = $this->manager->getCookieJar()->attachToResponse($response);
        $this->manager->getCookieJar()->flush();

        return $response;
    }
}
