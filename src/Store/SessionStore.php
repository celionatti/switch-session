<?php

declare(strict_types=1);

namespace Switch\Session\Store;

use SessionHandlerInterface;

class SessionStore
{
    private string $id = '';
    private string $name = 'switch_session';
    private SessionHandlerInterface $handler;
    private array $attributes = [];
    private bool $started = false;

    public function __construct(string $name, SessionHandlerInterface $handler, ?string $id = null)
    {
        $this->name = $name;
        $this->handler = $handler;
        $this->setId($id ?? $this->generateSessionId());
    }

    /**
     * Start the session and load saved data.
     */
    public function start(): bool
    {
        $this->loadData();
        if (!$this->has('_token')) {
            $this->regenerateToken();
        }

        return $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        if ($this->isValidId($id)) {
            $this->id = $id;
        } else {
            $this->id = $this->generateSessionId();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getHandler(): SessionHandlerInterface
    {
        return $this->handler;
    }

    /**
     * Get an item from the session using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // Support dot notation: 'user.profile.name'
        if (str_contains($key, '.')) {
            $current = $this->attributes;
            foreach (explode('.', $key) as $segment) {
                if (is_array($current) && array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                } else {
                    return $default;
                }
            }
            return $current;
        }

        return $default;
    }

    /**
     * Put a key / value pair or array of pairs into the session.
     */
    public function put(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->put($k, $v);
            }
            return;
        }

        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = &$this->attributes;
            foreach ($keys as $i => $segment) {
                if ($i === count($keys) - 1) {
                    $current[$segment] = $value;
                } else {
                    if (!isset($current[$segment]) || !is_array($current[$segment])) {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }
            }
            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Check if a key exists and is not null.
     */
    public function has(string $key): bool
    {
        $val = $this->get($key);
        return $val !== null;
    }

    /**
     * Check if a key exists even if value is null.
     */
    public function exists(string $key): bool
    {
        return $this->get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    /**
     * Get all session data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Get a subset of the session items.
     *
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            if ($this->exists($key)) {
                $results[$key] = $this->get($key);
            }
        }
        return $results;
    }

    /**
     * Get all session items except for a specified array of items.
     *
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $results = $this->attributes;
        foreach ($keys as $key) {
            unset($results[$key]);
        }
        return $results;
    }

    /**
     * Remove one or many items from the session.
     */
    public function forget(string|array $keys): void
    {
        $keys = (array) $keys;
        foreach ($keys as $key) {
            if (str_contains($key, '.')) {
                $segments = explode('.', $key);
                $current = &$this->attributes;
                $lastKey = array_pop($segments);
                foreach ($segments as $segment) {
                    if (!isset($current[$segment]) || !is_array($current[$segment])) {
                        continue 2;
                    }
                    $current = &$current[$segment];
                }
                unset($current[$lastKey]);
            } else {
                unset($this->attributes[$key]);
            }
        }
    }

    /**
     * Remove an item from the session, returning its value.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * Remove all items from the session.
     */
    public function flush(): void
    {
        $this->attributes = [];
    }

    /**
     * Increment the value of an item in the session.
     */
    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->put($key, $value);
        return $value;
    }

    /**
     * Decrement the value of an item in the session.
     */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, $amount * -1);
    }

    // -------------------------------------------------------------------------
    // Flash Messages Management
    // -------------------------------------------------------------------------

    /**
     * Flash a key / value pair to the session for the next request.
     */
    public function flash(string $key, mixed $value = true): void
    {
        $this->put($key, $value);
        $this->pushToList('_flash.new', $key);
        $this->removeFromList('_flash.old', $key);
    }

    /**
     * Flash an item for immediate use within the current request only.
     */
    public function now(string $key, mixed $value = true): void
    {
        $this->put($key, $value);
        $this->pushToList('_flash.old', $key);
    }

    /**
     * Reflash all current flash data for another request.
     */
    public function reflash(): void
    {
        $this->mergeIntoList('_flash.new', (array) $this->get('_flash.old', []));
        $this->put('_flash.old', []);
    }

    /**
     * Keep specific flash data for another request.
     */
    public function keep(array|string $keys = null): void
    {
        $keys = $keys === null ? (array) $this->get('_flash.old', []) : (array) $keys;

        $this->mergeIntoList('_flash.new', $keys);
        $this->removeFromList('_flash.old', $keys);
    }

    /**
     * Age the flash data: old flash is purged, new flash becomes old.
     */
    public function ageFlashData(): void
    {
        $old = (array) $this->get('_flash.old', []);
        $this->forget($old);

        $this->put('_flash.old', (array) $this->get('_flash.new', []));
        $this->put('_flash.new', []);
    }

    // -------------------------------------------------------------------------
    // CSRF Token Management
    // -------------------------------------------------------------------------

    /**
     * Get the current CSRF token.
     */
    public function token(): string
    {
        return (string) $this->get('_token', '');
    }

    /**
     * Regenerate the CSRF token.
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(20));
        $this->put('_token', $token);
        return $token;
    }

    /**
     * Verify a supplied CSRF token against the stored session token.
     */
    public function verifyToken(?string $token): bool
    {
        $stored = $this->token();
        return !empty($stored) && is_string($token) && hash_equals($stored, $token);
    }

    // -------------------------------------------------------------------------
    // Session Lifecycle & Persistence
    // -------------------------------------------------------------------------

    /**
     * Generate a new session ID and optionally destroy old data.
     */
    public function regenerate(bool $destroy = false): bool
    {
        if ($destroy) {
            $this->handler->destroy($this->id);
            $this->attributes = [];
        }

        $this->id = $this->generateSessionId();
        $this->regenerateToken();

        return true;
    }

    /**
     * Save the session data to the storage handler.
     */
    public function save(): void
    {
        $this->ageFlashData();
        $serialized = serialize($this->attributes);
        $this->handler->write($this->id, $serialized);
        $this->started = false;
    }

    /**
     * Load session data from the handler.
     */
    private function loadData(): void
    {
        $data = $this->handler->read($this->id);

        if (!empty($data)) {
            $unserialized = @unserialize($data);
            if (is_array($unserialized)) {
                $this->attributes = $unserialized;
                return;
            }
        }

        $this->attributes = [];
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(20));
    }

    private function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{20,128}$/', $id);
    }

    private function pushToList(string $listKey, string $value): void
    {
        $list = (array) $this->get($listKey, []);
        if (!in_array($value, $list, true)) {
            $list[] = $value;
        }
        $this->put($listKey, $list);
    }

    private function mergeIntoList(string $listKey, array $values): void
    {
        $list = (array) $this->get($listKey, []);
        foreach ($values as $val) {
            if (!in_array($val, $list, true)) {
                $list[] = $val;
            }
        }
        $this->put($listKey, $list);
    }

    private function removeFromList(string $listKey, array|string $values): void
    {
        $values = (array) $values;
        $list = (array) $this->get($listKey, []);
        $list = array_values(array_diff($list, $values));
        $this->put($listKey, $list);
    }
}
