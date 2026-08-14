<?php

declare(strict_types=1);

namespace Switch\Session\Handler;

use SessionHandlerInterface;

class ArraySessionHandler implements SessionHandlerInterface
{
    /**
     * @var array<string, array{data: string, time: int}>
     */
    private array $storage = [];
    private int $minutes;

    public function __construct(int $minutes = 120)
    {
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
        if (!isset($this->storage[$id])) {
            return '';
        }

        $entry = $this->storage[$id];
        if (time() - $entry['time'] > ($this->minutes * 60)) {
            unset($this->storage[$id]);
            return '';
        }

        return $entry['data'];
    }

    public function write(string $id, string $data): bool
    {
        $this->storage[$id] = [
            'data' => $data,
            'time' => time(),
        ];
        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->storage[$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $deleted = 0;
        $now = time();

        foreach ($this->storage as $id => $entry) {
            if ($now - $entry['time'] > $max_lifetime) {
                unset($this->storage[$id]);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Helper for tests to inspect internal store.
     *
     * @return array<string, array{data: string, time: int}>
     */
    public function all(): array
    {
        return $this->storage;
    }
}
