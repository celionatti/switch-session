<?php

declare(strict_types=1);

namespace Switch\Session\Flash;

use Switch\Session\Session;
use Switch\Session\Store\SessionStore;

class FlashBag
{
    private const SESSION_KEY = '_flash_messages';

    private ?SessionStore $store;

    public function __construct(?SessionStore $store = null)
    {
        $this->store = $store;
    }

    /**
     * Add a flash message.
     *
     * @param string $type ('success', 'error', 'warning', 'info')
     * @param string $message Flash message text
     * @param string|null $title Optional title
     * @param array<string, mixed> $options Custom options (timeout, raw, dismissible)
     */
    public function add(string $type, string $message, ?string $title = null, array $options = []): FlashMessage
    {
        $flashMessage = new FlashMessage($type, $message, $title, $options);
        $store = $this->getStore();

        // 1. Store in active session flash data
        $existing = (array) $store->get(self::SESSION_KEY, []);
        $existing[] = $flashMessage->toArray();
        $store->flash(self::SESSION_KEY, $existing);

        // 2. Also set legacy key for backwards compatibility
        $store->flash($flashMessage->getType(), $message);

        // 3. If this is a Switch Live SPA request, auto-emit a client toast header
        if (class_exists(\Switch\Live\LiveResponse::class) && \Switch\Live\LiveResponse::isLiveRequest()) {
            \Switch\Live\LiveResponse::toast($message, $flashMessage->getType());
        }

        return $flashMessage;
    }

    /**
     * Add a success flash message.
     */
    public function success(string $message, ?string $title = null, array $options = []): FlashMessage
    {
        return $this->add('success', $message, $title, $options);
    }

    /**
     * Add an error flash message.
     */
    public function error(string $message, ?string $title = null, array $options = []): FlashMessage
    {
        return $this->add('error', $message, $title, $options);
    }

    /**
     * Add a warning flash message.
     */
    public function warning(string $message, ?string $title = null, array $options = []): FlashMessage
    {
        return $this->add('warning', $message, $title, $options);
    }

    /**
     * Add an info flash message.
     */
    public function info(string $message, ?string $title = null, array $options = []): FlashMessage
    {
        return $this->add('info', $message, $title, $options);
    }

    /**
     * Add a flash message for the immediate request cycle only.
     */
    public function now(string $type, string $message, ?string $title = null, array $options = []): FlashMessage
    {
        $flashMessage = new FlashMessage($type, $message, $title, $options);
        $store = $this->getStore();

        $existing = (array) $store->get(self::SESSION_KEY, []);
        $existing[] = $flashMessage->toArray();
        $store->now(self::SESSION_KEY, $existing);
        $store->now($flashMessage->getType(), $message);

        return $flashMessage;
    }

    /**
     * Check if any flash messages exist, or check a specific type.
     */
    public function has(?string $type = null): bool
    {
        $messages = $this->all();

        if ($type === null) {
            return !empty($messages);
        }

        $type = strtolower($type);
        if ($type === 'danger') {
            $type = 'error';
        }

        foreach ($messages as $msg) {
            if ($msg->getType() === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all flash messages for a specific type.
     *
     * @return array<int, FlashMessage>
     */
    public function get(?string $type = null): array
    {
        $all = $this->all();

        if ($type === null) {
            return $all;
        }

        $type = strtolower($type);
        if ($type === 'danger') {
            $type = 'error';
        }

        return array_values(array_filter($all, fn(FlashMessage $m) => $m->getType() === $type));
    }

    /**
     * Get all flash messages currently available.
     *
     * @return array<int, FlashMessage>
     */
    public function all(): array
    {
        $store = $this->getStore();
        $rawMessages = (array) $store->get(self::SESSION_KEY, []);
        $messages = [];

        foreach ($rawMessages as $raw) {
            if (is_array($raw) && isset($raw['type'], $raw['message'])) {
                $messages[] = new FlashMessage(
                    (string) $raw['type'],
                    (string) $raw['message'],
                    isset($raw['title']) ? (string) $raw['title'] : null,
                    (array) ($raw['options'] ?? [])
                );
            }
        }

        // Bridge standard legacy session keys if present (e.g., 'status', 'success', 'error')
        $legacyKeys = [
            'status' => 'info',
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
        ];

        foreach ($legacyKeys as $key => $mappedType) {
            if ($store->has($key) && is_string($store->get($key))) {
                $val = (string) $store->get($key);
                // Check if already captured in rawMessages
                $alreadyCaptured = false;
                foreach ($messages as $m) {
                    if ($m->getMessage() === $val) {
                        $alreadyCaptured = true;
                        break;
                    }
                }
                if (!$alreadyCaptured) {
                    $messages[] = new FlashMessage($mappedType, $val);
                }
            }
        }

        return $messages;
    }

    /**
     * Get the total count of flash messages.
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Clear all flash messages from session.
     */
    public function clear(): void
    {
        $store = $this->getStore();
        $store->forget(self::SESSION_KEY);
        $store->forget(['success', 'error', 'warning', 'info', 'status']);
    }

    private function getStore(): SessionStore
    {
        return $this->store ?? Session::getStore();
    }
}
