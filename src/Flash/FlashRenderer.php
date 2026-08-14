<?php

declare(strict_types=1);

namespace Switch\Session\Flash;

class FlashRenderer
{
    private FlashBag $bag;

    public function __construct(?FlashBag $bag = null)
    {
        $this->bag = $bag ?? new FlashBag();
    }

    /**
     * Render the flash messages to HTML.
     *
     * @param string $mode 'toast' or 'alert'
     * @param array<string, mixed> $options Options: ['position' => 'bottom-right', 'autoHide' => true, 'inlineStyles' => true]
     */
    public function render(string $mode = 'toast', array $options = []): string
    {
        $messages = $this->bag->all();

        if (empty($messages)) {
            return '';
        }

        return $mode === 'alert'
            ? $this->renderAlerts($messages, $options)
            : $this->renderToasts($messages, $options);
    }

    /**
     * Render inline alert cards.
     *
     * @param array<int, FlashMessage> $messages
     * @param array<string, mixed> $options
     */
    private function renderAlerts(array $messages, array $options): string
    {
        $html = '<div class="switch-flash-alerts-container">';

        foreach ($messages as $msg) {
            $type = $msg->getType();
            $escapedMsg = $msg->getEscapedMessage();
            $escapedTitle = $msg->getEscapedTitle();
            $icon = $this->getSvgIcon($type);

            $titleHtml = $escapedTitle !== null ? "<strong class=\"switch-flash-title\">{$escapedTitle}</strong>" : '';
            $dismissBtn = $msg->isDismissible()
                ? '<button type="button" class="switch-flash-close" onclick="this.closest(\'.switch-flash-alert\').remove()" aria-label="Close">&times;</button>'
                : '';

            $html .= <<<ALERT
<div class="switch-flash-alert switch-flash-{$type}" role="alert">
    <div class="switch-flash-icon-box">{$icon}</div>
    <div class="switch-flash-content">
        {$titleHtml}
        <span class="switch-flash-text">{$escapedMsg}</span>
    </div>
    {$dismissBtn}
</div>
ALERT;
        }

        $html .= '</div>';
        $html .= $this->getStyles();

        return $html;
    }

    /**
     * Render floating responsive toast cards with timer progress bar.
     *
     * @param array<int, FlashMessage> $messages
     * @param array<string, mixed> $options
     */
    private function renderToasts(array $messages, array $options): string
    {
        $position = (string) ($options['position'] ?? 'bottom-right');
        $posClass = 'pos-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $position);

        $html = "<div class=\"switch-flash-toast-deck {$posClass}\" id=\"switch-flash-toasts\">";

        foreach ($messages as $msg) {
            $type = $msg->getType();
            $id = $msg->getId();
            $timeout = $msg->getTimeout();
            $escapedMsg = $msg->getEscapedMessage();
            $escapedTitle = $msg->getEscapedTitle();
            $icon = $this->getSvgIcon($type);

            $titleHtml = $escapedTitle !== null ? "<strong class=\"switch-flash-title\">{$escapedTitle}</strong>" : '';
            $dismissBtn = $msg->isDismissible()
                ? "<button type=\"button\" class=\"switch-flash-close\" onclick=\"dismissSwitchFlash('{$id}')\" aria-label=\"Close\">&times;</button>"
                : '';

            $html .= <<<TOAST
<div class="switch-flash-toast switch-flash-{$type}" id="{$id}" data-timeout="{$timeout}">
    <div class="switch-flash-main">
        <div class="switch-flash-icon-box">{$icon}</div>
        <div class="switch-flash-content">
            {$titleHtml}
            <span class="switch-flash-text">{$escapedMsg}</span>
        </div>
        {$dismissBtn}
    </div>
    <div class="switch-flash-progress-track">
        <div class="switch-flash-progress-bar"></div>
    </div>
</div>
TOAST;
        }

        $html .= '</div>';
        $html .= $this->getStyles();
        $html .= $this->getScript();

        return $html;
    }

    private function getSvgIcon(string $type): string
    {
        return match ($type) {
            'success' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'error' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            'warning' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            default => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        };
    }

    private function getStyles(): string
    {
        static $stylesRendered = false;
        if ($stylesRendered) {
            return '';
        }
        $stylesRendered = true;

        return <<<'CSS'
<style>
/* Switch Flash Notification Engine */
.switch-flash-toast-deck {
    position: fixed;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 420px;
    width: 100%;
    pointer-events: none;
    box-sizing: border-box;
}

.switch-flash-toast-deck.pos-bottom-right { bottom: 24px; right: 24px; }
.switch-flash-toast-deck.pos-top-right { top: 24px; right: 24px; }
.switch-flash-toast-deck.pos-top-center { top: 24px; left: 50%; transform: translateX(-50%); }
.switch-flash-toast-deck.pos-bottom-center { bottom: 24px; left: 50%; transform: translateX(-50%); }
.switch-flash-toast-deck.pos-bottom-left { bottom: 24px; left: 24px; }
.switch-flash-toast-deck.pos-top-left { top: 24px; left: 24px; }

.switch-flash-toast {
    position: relative;
    pointer-events: auto;
    background: rgba(18, 21, 31, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    overflow: hidden;
    opacity: 0;
    transform: translateY(16px) scale(0.96);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.switch-flash-toast.show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.switch-flash-toast.hide {
    opacity: 0;
    transform: translateY(12px) scale(0.95);
}

.switch-flash-main {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
}

.switch-flash-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    flex-shrink: 0;
    margin-top: 1px;
}

.switch-flash-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.switch-flash-title {
    font-size: 0.86rem;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.01em;
}

.switch-flash-text {
    font-size: 0.83rem;
    line-height: 1.45;
    color: #cbd5e1;
    word-break: break-word;
}

.switch-flash-close {
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all 0.15s ease;
}

.switch-flash-close:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}

/* Progress bar */
.switch-flash-progress-track {
    width: 100%;
    height: 3px;
    background: rgba(255, 255, 255, 0.05);
}

.switch-flash-progress-bar {
    height: 100%;
    width: 100%;
    transform-origin: left;
    transition: width linear;
}

/* Semantic Color Themes */
.switch-flash-success .switch-flash-icon-box { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.switch-flash-success .switch-flash-progress-bar { background: #10b981; }
.switch-flash-success { border-color: rgba(16, 185, 129, 0.35); }

.switch-flash-error .switch-flash-icon-box { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.switch-flash-error .switch-flash-progress-bar { background: #ef4444; }
.switch-flash-error { border-color: rgba(239, 68, 68, 0.35); }

.switch-flash-warning .switch-flash-icon-box { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.switch-flash-warning .switch-flash-progress-bar { background: #f59e0b; }
.switch-flash-warning { border-color: rgba(245, 158, 11, 0.35); }

.switch-flash-info .switch-flash-icon-box { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
.switch-flash-info .switch-flash-progress-bar { background: #06b6d4; }
.switch-flash-info { border-color: rgba(6, 182, 212, 0.35); }

/* Inline Alerts */
.switch-flash-alerts-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 16px 0;
    width: 100%;
}

.switch-flash-alert {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 18px;
    border-radius: 10px;
    background: #11141d;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Mobile Responsive */
@media (max-width: 640px) {
    .switch-flash-toast-deck {
        left: 16px !important;
        right: 16px !important;
        bottom: max(16px, env(safe-area-inset-bottom)) !important;
        top: auto !important;
        transform: none !important;
        max-width: none;
        width: auto;
    }

    .switch-flash-toast {
        border-radius: 10px;
    }

    .switch-flash-main {
        padding: 12px 14px;
    }
}
</style>
CSS;
    }

    private function getScript(): string
    {
        static $scriptRendered = false;
        if ($scriptRendered) {
            return '';
        }
        $scriptRendered = true;

        return <<<'JS'
<script>
(function() {
    function initSwitchFlashes() {
        document.querySelectorAll('.switch-flash-toast').forEach(function(toast) {
            if (toast._switchInit) return;
            toast._switchInit = true;

            setTimeout(function() { toast.classList.add('show'); }, 50);

            var timeout = parseInt(toast.getAttribute('data-timeout') || '4500', 10);
            if (timeout > 0) {
                var bar = toast.querySelector('.switch-flash-progress-bar');
                if (bar) {
                    bar.style.transitionDuration = timeout + 'ms';
                    bar.style.width = '0%';
                }

                var timer = setTimeout(function() {
                    dismissSwitchFlash(toast.id);
                }, timeout);

                toast.addEventListener('mouseenter', function() {
                    clearTimeout(timer);
                    if (bar) bar.style.transitionDuration = '0ms';
                });

                toast.addEventListener('mouseleave', function() {
                    timer = setTimeout(function() {
                        dismissSwitchFlash(toast.id);
                    }, 2000);
                    if (bar) {
                        bar.style.transitionDuration = '2000ms';
                        bar.style.width = '0%';
                    }
                });
            }
        });
    }

    window.dismissSwitchFlash = function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('show');
        el.classList.add('hide');
        setTimeout(function() { el.remove(); }, 350);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwitchFlashes);
    } else {
        initSwitchFlashes();
    }
})();
</script>
JS;
    }
}
