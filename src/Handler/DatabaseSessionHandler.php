<?php

declare(strict_types=1);

namespace Switch\Session\Handler;

use PDO;
use SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private string $table;
    private int $minutes;

    public function __construct(PDO $pdo, string $table = 'sessions', int $minutes = 120)
    {
        $this->pdo = $pdo;
        $this->table = $table;
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
        $stmt = $this->pdo->prepare("SELECT payload, last_activity FROM `{$this->table}` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return '';
        }

        if (time() - (int) $row['last_activity'] > ($this->minutes * 60)) {
            $this->destroy($id);
            return '';
        }

        return (string) $row['payload'];
    }

    public function write(string $id, string $data): bool
    {
        $now = time();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

        // Try update first
        $stmt = $this->pdo->prepare("UPDATE `{$this->table}` SET payload = :payload, last_activity = :last_activity, ip_address = :ip_address, user_agent = :user_agent WHERE id = :id");
        $stmt->execute([
            ':id' => $id,
            ':payload' => $data,
            ':last_activity' => $now,
            ':ip_address' => $ip,
            ':user_agent' => $userAgent,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        // If not found, insert
        $insertStmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (id, payload, last_activity, ip_address, user_agent) VALUES (:id, :payload, :last_activity, :ip_address, :user_agent)");
        return $insertStmt->execute([
            ':id' => $id,
            ':payload' => $data,
            ':last_activity' => $now,
            ':ip_address' => $ip,
            ':user_agent' => $userAgent,
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE last_activity < :threshold");
        $stmt->execute([':threshold' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
