<?php

declare(strict_types=1);

namespace Switch\Session;

use InvalidArgumentException;
use SessionHandlerInterface;
use Switch\Session\Cookie\CookieJar;
use Switch\Session\Handler\ArraySessionHandler;
use Switch\Session\Handler\CookieSessionHandler;
use Switch\Session\Handler\DatabaseSessionHandler;
use Switch\Session\Handler\FileSessionHandler;
use Switch\Session\Store\SessionStore;
use PDO;

class SessionManager
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var array<string, SessionStore> Instantiated session stores
     */
    private array $stores = [];

    private CookieJar $cookieJar;

    public function __construct(array $config = [])
    {
        $defaultPath = sys_get_temp_dir() . '/switch_sessions';

        $this->config = array_merge([
            'driver' => 'file',
            'lifetime' => 120, // minutes
            'files' => $defaultPath,
            'connection' => null,
            'table' => 'sessions',
            'cookie' => 'switch_session',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Lax',
        ], $config);

        $this->cookieJar = new CookieJar();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get a session store instance by driver name.
     */
    public function driver(?string $driver = null): SessionStore
    {
        $driver ??= $this->getDefaultDriver();

        if (isset($this->stores[$driver])) {
            return $this->stores[$driver];
        }

        return $this->stores[$driver] = $this->createDriver($driver);
    }

    /**
     * Get the active default session store.
     */
    public function store(): SessionStore
    {
        return $this->driver();
    }

    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function getDefaultDriver(): string
    {
        return (string) ($this->config['driver'] ?? 'file');
    }

    /**
     * Build a custom session store with a given handler.
     */
    public function buildSession(SessionHandlerInterface $handler, ?string $id = null): SessionStore
    {
        return new SessionStore($this->getSessionCookieName(), $handler, $id);
    }

    public function getSessionCookieName(): string
    {
        return (string) ($this->config['cookie'] ?? 'switch_session');
    }

    private function createDriver(string $driver): SessionStore
    {
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Unsupported session driver [{$driver}].");
    }

    private function createFileDriver(): SessionStore
    {
        $path = (string) $this->config['files'];
        $minutes = (int) $this->config['lifetime'];

        return $this->buildSession(new FileSessionHandler($path, $minutes));
    }

    private function createArrayDriver(): SessionStore
    {
        $minutes = (int) $this->config['lifetime'];

        return $this->buildSession(new ArraySessionHandler($minutes));
    }

    private function createCookieDriver(): SessionStore
    {
        $minutes = (int) $this->config['lifetime'];

        return $this->buildSession(new CookieSessionHandler($this->cookieJar, $minutes));
    }

    private function createDatabaseDriver(): SessionStore
    {
        $pdo = $this->resolvePdo();
        $table = (string) ($this->config['table'] ?? 'sessions');
        $minutes = (int) $this->config['lifetime'];

        return $this->buildSession(new DatabaseSessionHandler($pdo, $table, $minutes));
    }

    private function resolvePdo(): PDO
    {
        $conn = $this->config['connection'] ?? null;

        if ($conn instanceof PDO) {
            return $conn;
        }

        if (is_object($conn) && method_exists($conn, 'getPdo')) {
            return $conn->getPdo();
        }

        if (class_exists(\Switch\Database\DB::class)) {
            return \Switch\Database\DB::getPdo();
        }

        if (class_exists(\Switch\Database\ORM\Model::class) && \Switch\Database\ORM\Model::hasConnection()) {
            return \Switch\Database\ORM\Model::getConnection()->getPdo();
        }

        throw new InvalidArgumentException('Database session driver requires an active PDO connection in config[\'connection\'].');
    }

    /**
     * Dynamically proxy method calls to the default session store.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->$method(...$parameters);
    }
}
