<?php

declare(strict_types=1);

namespace Switch\Session\Handler;

use SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    private string $path;
    private int $minutes;

    public function __construct(string $path, int $minutes = 120)
    {
        $this->path = rtrim($path, '/\\');
        $this->minutes = $minutes;

        if (!is_dir($this->path)) {
            @mkdir($this->path, 0777, true);
        }
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
        $file = $this->getFilePath($id);

        if (!file_exists($file)) {
            return '';
        }

        $mtime = filemtime($file);
        if ($mtime !== false && (time() - $mtime) > ($this->minutes * 60)) {
            $this->destroy($id);
            return '';
        }

        $data = file_get_contents($file);
        return $data !== false ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $file = $this->getFilePath($id);
        $result = file_put_contents($file, $data, LOCK_EX);
        return $result !== false;
    }

    public function destroy(string $id): bool
    {
        $file = $this->getFilePath($id);
        if (file_exists($file)) {
            @unlink($file);
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $deleted = 0;
        $now = time();
        $files = glob($this->path . '/*') ?: [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $mtime = filemtime($file);
                if ($mtime !== false && ($now - $mtime) > $max_lifetime) {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    private function getFilePath(string $id): string
    {
        // Sanitize ID to prevent directory traversal
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        return $this->path . '/' . $safeId;
    }
}
