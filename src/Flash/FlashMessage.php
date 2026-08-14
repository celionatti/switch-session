<?php

declare(strict_types=1);

namespace Switch\Session\Flash;

use JsonSerializable;

class FlashMessage implements JsonSerializable
{
    private string $id;
    private string $type;
    private string $message;
    private ?string $title;
    private int $timeout;
    private bool $dismissible;
    private bool $isRaw;
    private array $options;

    /**
     * @param string $type ('success', 'error', 'warning', 'info')
     * @param string $message Flash message body
     * @param string|null $title Optional title
     * @param array<string, mixed> $options Custom options (timeout, raw, dismissible, etc.)
     */
    public function __construct(
        string $type,
        string $message,
        ?string $title = null,
        array $options = []
    ) {
        $this->id = uniqid('flash_', true);
        $this->type = strtolower($type);
        if ($this->type === 'danger') {
            $this->type = 'error';
        }

        $this->message = $message;
        $this->title = $title;
        $this->timeout = (int) ($options['timeout'] ?? 4500);
        $this->dismissible = (bool) ($options['dismissible'] ?? true);
        $this->isRaw = (bool) ($options['raw'] ?? false);
        $this->options = $options;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getEscapedMessage(): string
    {
        return $this->isRaw ? $this->message : htmlspecialchars($this->message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getEscapedTitle(): ?string
    {
        if ($this->title === null) {
            return null;
        }

        return $this->isRaw ? $this->title : htmlspecialchars($this->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function isDismissible(): bool
    {
        return $this->dismissible;
    }

    public function isRaw(): bool
    {
        return $this->isRaw;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'message' => $this->message,
            'title' => $this->title,
            'timeout' => $this->timeout,
            'dismissible' => $this->dismissible,
            'raw' => $this->isRaw,
            'options' => $this->options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
