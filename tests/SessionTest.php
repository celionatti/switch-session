<?php

declare(strict_types=1);

namespace Switch\Session\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Http\Response;
use Switch\Http\ServerRequest;
use Switch\Http\Uri;
use Switch\Session\Cookie\Cookie;
use Switch\Session\Cookie\CookieJar;
use Switch\Session\Exception\TokenMismatchException;
use Switch\Session\Handler\ArraySessionHandler;
use Switch\Session\Handler\DatabaseSessionHandler;
use Switch\Session\Handler\FileSessionHandler;
use Switch\Session\Middleware\StartSession;
use Switch\Session\Middleware\VerifyCsrfToken;
use Switch\Session\Session;
use Switch\Session\SessionManager;
use Switch\Session\Store\SessionStore;
use PDO;

require_once __DIR__ . '/../src/helpers.php';

class DummyHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $lastRequest = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;
        return new Response(200, ['Content-Type' => 'text/html'], \Switch\Http\Stream::create('OK'));
    }

    public function getLastRequest(): ?ServerRequestInterface
    {
        return $this->lastRequest;
    }
}

class SessionTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/switch_test_sessions_' . uniqid();
        if (!is_dir($this->tempPath)) {
            @mkdir($this->tempPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempPath)) {
            $files = glob($this->tempPath . '/*') ?: [];
            foreach ($files as $f) {
                @unlink($f);
            }
            @rmdir($this->tempPath);
        }
    }

    public function testCookieCreationAndHeaderSerialization(): void
    {
        $cookie = Cookie::make('session_id', 'abc123xyz', 60, '/', 'example.com', true, true, false, 'Strict', true);

        $this->assertEquals('session_id', $cookie->getName());
        $this->assertEquals('abc123xyz', $cookie->getValue());
        $this->assertEquals('/', $cookie->getPath());
        $this->assertEquals('example.com', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals('Strict', $cookie->getSameSite());
        $this->assertTrue($cookie->isPartitioned());

        $header = $cookie->toHeaderValue();
        $this->assertStringContainsString('session_id=abc123xyz', $header);
        $this->assertStringContainsString('Path=/', $header);
        $this->assertStringContainsString('Domain=example.com', $header);
        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Strict', $header);
        $this->assertStringContainsString('Partitioned', $header);
    }

    public function testCookieForgetAndForever(): void
    {
        $forget = Cookie::forget('old_cookie');
        $this->assertTrue($forget->isExpired());
        $this->assertStringContainsString('Max-Age=0', $forget->toHeaderValue());

        $forever = Cookie::forever('remember_token', 'secret');
        $this->assertFalse($forever->isExpired());
        $this->assertGreaterThan(time() + (4 * 365 * 86400), $forever->getExpires());
    }

    public function testCookieJarQueueAndAttach(): void
    {
        $jar = new CookieJar();
        $cookie = $jar->queue('theme', 'dark', 30);

        $this->assertTrue($jar->hasQueued('theme'));
        $this->assertSame($cookie, $jar->getQueued('theme'));

        $response = new Response(200);
        $response = $jar->attachToResponse($response);

        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertStringContainsString('theme=dark', $response->getHeaderLine('Set-Cookie'));

        $jar->flush();
        $this->assertFalse($jar->hasQueued('theme'));
    }

    public function testArraySessionHandler(): void
    {
        $handler = new ArraySessionHandler(120);
        $handler->write('sess_1', 'foo_data');

        $this->assertEquals('foo_data', $handler->read('sess_1'));
        $this->assertEquals('', $handler->read('non_existent'));

        $handler->destroy('sess_1');
        $this->assertEquals('', $handler->read('sess_1'));
    }

    public function testFileSessionHandler(): void
    {
        $handler = new FileSessionHandler($this->tempPath, 120);
        $handler->write('file_sess_1', 'serialized_payload');

        $this->assertEquals('serialized_payload', $handler->read('file_sess_1'));
        $this->assertEquals('', $handler->read('file_sess_unknown'));

        $handler->destroy('file_sess_1');
        $this->assertEquals('', $handler->read('file_sess_1'));
    }

    public function testDatabaseSessionHandler(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE sessions (id VARCHAR(255) PRIMARY KEY, payload TEXT, last_activity INT, user_id INT, ip_address VARCHAR(45), user_agent TEXT)");

        $handler = new DatabaseSessionHandler($pdo, 'sessions', 120);
        $handler->write('db_sess_1', 'db_data');

        $this->assertEquals('db_data', $handler->read('db_sess_1'));

        // Test update
        $handler->write('db_sess_1', 'updated_db_data');
        $this->assertEquals('updated_db_data', $handler->read('db_sess_1'));

        $handler->destroy('db_sess_1');
        $this->assertEquals('', $handler->read('db_sess_1'));
    }

    public function testSessionStoreCrudAndDotNotation(): void
    {
        $handler = new ArraySessionHandler();
        $store = new SessionStore('switch_session', $handler);
        $store->start();

        $this->assertTrue($store->isStarted());
        $this->assertNotEmpty($store->getId());

        // Basic put and get
        $store->put('username', 'celionatti');
        $this->assertEquals('celionatti', $store->get('username'));
        $this->assertTrue($store->has('username'));
        $this->assertTrue($store->exists('username'));

        // Nested dot notation
        $store->put('app.settings.theme', 'cyberpunk');
        $this->assertEquals('cyberpunk', $store->get('app.settings.theme'));
        $this->assertEquals(['theme' => 'cyberpunk'], $store->get('app.settings'));

        // Increment & Decrement
        $store->put('counter', 5);
        $this->assertEquals(6, $store->increment('counter'));
        $this->assertEquals(4, $store->decrement('counter', 2));

        // Pull
        $pulled = $store->pull('username');
        $this->assertEquals('celionatti', $pulled);
        $this->assertFalse($store->has('username'));

        // Only & Except
        $store->put('a', 1);
        $store->put('b', 2);
        $store->put('c', 3);
        $this->assertEquals(['a' => 1, 'b' => 2], $store->only(['a', 'b']));
        $except = $store->except(['a', 'b', '_token']);
        $this->assertEquals(3, $except['c']);

        // Forget
        $store->forget('a');
        $this->assertFalse($store->has('a'));

        // Flush
        $store->flush();
        $this->assertEmpty($store->all());
    }

    public function testSessionStoreFlashLifecycle(): void
    {
        $handler = new ArraySessionHandler();
        $store = new SessionStore('switch_session', $handler);
        $store->start();

        // 1. Flash message for next request
        $store->flash('status', 'Task completed');
        $this->assertEquals('Task completed', $store->get('status'));

        // 2. Age flash data (simulate request 1 ending -> request 2)
        $store->ageFlashData();
        $this->assertEquals('Task completed', $store->get('status')); // Still accessible in request 2

        // 3. Age flash data again (simulate request 2 ending -> request 3)
        $store->ageFlashData();
        $this->assertNull($store->get('status')); // Expired and removed in request 3
    }

    public function testCsrfTokenLifecycle(): void
    {
        $handler = new ArraySessionHandler();
        $store = new SessionStore('switch_session', $handler);
        $store->start();

        $token = $store->token();
        $this->assertNotEmpty($token);
        $this->assertTrue($store->verifyToken($token));
        $this->assertFalse($store->verifyToken('invalid-token'));

        $newToken = $store->regenerateToken();
        $this->assertNotEquals($token, $newToken);
        $this->assertTrue($store->verifyToken($newToken));
    }

    public function testSessionManagerAndDriverResolution(): void
    {
        $manager = new SessionManager([
            'driver' => 'array',
            'lifetime' => 60,
        ]);

        $store = $manager->driver('array');
        $this->assertInstanceOf(SessionStore::class, $store);
        $this->assertSame($store, $manager->driver('array'));
        $this->assertSame($store, $manager->store());
    }

    public function testStartSessionMiddleware(): void
    {
        $manager = new SessionManager(['driver' => 'array']);
        $middleware = new StartSession($manager);
        $dummy = new DummyHandler();

        $request = new ServerRequest('GET', new Uri('https://example.com/'));
        $response = $middleware->process($request, $dummy);

        $handledRequest = $dummy->getLastRequest();
        $this->assertNotNull($handledRequest);
        $this->assertInstanceOf(SessionStore::class, $handledRequest->getAttribute('session'));

        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertStringContainsString('switch_session=', $response->getHeaderLine('Set-Cookie'));
    }

    public function testVerifyCsrfTokenMiddlewarePassesOnGet(): void
    {
        $middleware = new VerifyCsrfToken();
        $dummy = new DummyHandler();

        $request = new ServerRequest('GET', new Uri('https://example.com/test'));
        $response = $middleware->process($request, $dummy);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testVerifyCsrfTokenMiddlewareFailsOnPostWithoutToken(): void
    {
        $middleware = new VerifyCsrfToken();
        $dummy = new DummyHandler();

        $handler = new ArraySessionHandler();
        $session = new SessionStore('switch_session', $handler);
        $session->start();

        $request = (new ServerRequest('POST', new Uri('https://example.com/submit')))
            ->withAttribute('session', $session);

        $this->expectException(TokenMismatchException::class);
        $middleware->process($request, $dummy);
    }

    public function testVerifyCsrfTokenMiddlewarePassesWithValidToken(): void
    {
        $middleware = new VerifyCsrfToken();
        $dummy = new DummyHandler();

        $handler = new ArraySessionHandler();
        $session = new SessionStore('switch_session', $handler);
        $session->start();
        $validToken = $session->token();

        // 1. Valid Token in Post Body
        $request = (new ServerRequest('POST', new Uri('https://example.com/submit')))
            ->withAttribute('session', $session)
            ->withParsedBody(['_token' => $validToken]);

        $response = $middleware->process($request, $dummy);
        $this->assertEquals(200, $response->getStatusCode());

        // 2. Valid Token in Header
        $headerRequest = (new ServerRequest('POST', new Uri('https://example.com/submit')))
            ->withAttribute('session', $session)
            ->withHeader('X-CSRF-TOKEN', $validToken);

        $headerResponse = $middleware->process($headerRequest, $dummy);
        $this->assertEquals(200, $headerResponse->getStatusCode());
    }

    public function testVerifyCsrfTokenMiddlewareExemptRoutes(): void
    {
        $middleware = new VerifyCsrfToken(['api/*', 'webhook/stripe']);
        $dummy = new DummyHandler();

        // API route passes without token
        $request = new ServerRequest('POST', new Uri('https://example.com/api/v1/users'));
        $response = $middleware->process($request, $dummy);
        $this->assertEquals(200, $response->getStatusCode());

        // Webhook route passes without token
        $request2 = new ServerRequest('POST', new Uri('https://example.com/webhook/stripe'));
        $response2 = $middleware->process($request2, $dummy);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testGlobalHelpers(): void
    {
        $manager = new SessionManager(['driver' => 'array']);
        SessionManager::setInstance($manager);
        $store = $manager->store();
        $store->start();
        Session::setStore($store);

        // session() get/set
        session(['user_id' => 99]);
        $this->assertEquals(99, session('user_id'));
        $this->assertSame($store, session());

        // csrf_token() & csrf_field()
        $this->assertEquals($store->token(), csrf_token());
        $this->assertStringContainsString('type="hidden" name="_token"', csrf_field());
        $this->assertStringContainsString($store->token(), csrf_field());

        // cookie() helper
        $c = cookie('lang', 'en', 60);
        $this->assertEquals('lang', $c->getName());
        $this->assertEquals('en', $c->getValue());
        $this->assertTrue(SessionManager::getInstance()->getCookieJar()->hasQueued('lang'));
    }
}
