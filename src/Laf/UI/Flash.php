<?php

declare(strict_types=1);

namespace Laf\UI;

use Laf\UI\Component\Alert;
use Laf\Util\Util;

/**
 * One-shot messages shown on the next page ("Record saved"): kept in the session
 * when one is active (else for the current request only), drained once by the page
 * that draws them.
 *
 *   Flash::success('Record saved');          // after a write, before the redirect
 *   Flash::error('Record not found');
 *   foreach (Flash::drain() as $alert) { $page->addComponent($alert); }   // on the next page
 *
 * Messages are escaped when drawn; Flash::html() takes markup. The legacy keys
 * $_SESSION['flash_message'] / ['flash_type'] written by older pages are drained too.
 */
final class Flash
{
    public const SESSION_KEY = 'laf_flash';

    /** @var array<int, array{message: string, type: string, html: bool}> fallback without a session */
    private static array $local = [];

    public static function success(string $message): void
    {
        self::add($message, Alert::Type_Success);
    }

    public static function error(string $message): void
    {
        self::add($message, Alert::Type_Danger);
    }

    public static function warning(string $message): void
    {
        self::add($message, Alert::Type_Warning);
    }

    public static function info(string $message): void
    {
        self::add($message, Alert::Type_Info);
    }

    /** A message holding markup, drawn unescaped: only for text the application builds itself */
    public static function html(string $markup, string $type = Alert::Type_Success): void
    {
        self::add($markup, $type, true);
    }

    /**
     * @param string $type one of the Alert::Type_* styles
     */
    public static function add(string $message, string $type = Alert::Type_Success, bool $html = false): void
    {
        $entry = ['message' => $message, 'type' => $type, 'html' => $html];
        if (self::sessionActive()) {
            $queue = $_SESSION[self::SESSION_KEY] ?? [];
            $queue = is_array($queue) ? $queue : [];
            $queue[] = $entry;
            $_SESSION[self::SESSION_KEY] = $queue;
            return;
        }
        self::$local[] = $entry;
    }

    public static function has(): bool
    {
        return self::peek() !== [];
    }

    /**
     * The queued messages, oldest first, without removing them
     * @return array<int, array{message: string, type: string, html: bool}>
     */
    public static function peek(): array
    {
        $entries = self::$local;
        if (self::sessionActive()) {
            $queue = $_SESSION[self::SESSION_KEY] ?? [];
            if (is_array($queue)) {
                foreach ($queue as $entry) {
                    if (is_array($entry) && isset($entry['message'])) {
                        $entries[] = [
                            'message' => (string)$entry['message'],
                            'type' => (string)($entry['type'] ?? Alert::Type_Success),
                            'html' => (bool)($entry['html'] ?? false),
                        ];
                    }
                }
            }
            if (isset($_SESSION['flash_message']) && is_scalar($_SESSION['flash_message'])) {
                $type = $_SESSION['flash_type'] ?? Alert::Type_Success;
                $entries[] = ['message' => (string)$_SESSION['flash_message'], 'type' => is_string($type) ? $type : Alert::Type_Success, 'html' => false];
            }
        }
        return $entries;
    }

    /**
     * The queued messages, oldest first, and the queue emptied
     * @return array<int, array{message: string, type: string, html: bool}>
     */
    public static function pull(): array
    {
        $entries = self::peek();
        self::clear();
        return $entries;
    }

    /**
     * The queued messages as dismissible alerts, ready for addComponent(), and the queue emptied
     * @return Alert[]
     */
    public static function drain(): array
    {
        $alerts = [];
        foreach (self::pull() as $entry) {
            $alert = new Alert('', $entry['html'] ? $entry['message'] : Util::e($entry['message']), $entry['type']);
            $alert->setDismissible(true);
            $alerts[] = $alert;
        }
        return $alerts;
    }

    public static function clear(): void
    {
        self::$local = [];
        if (self::sessionActive()) {
            unset($_SESSION[self::SESSION_KEY], $_SESSION['flash_message'], $_SESSION['flash_type']);
        }
    }

    private static function sessionActive(): bool
    {
        // tests simulate the session with a plain $_SESSION array in the CLI
        return session_status() === PHP_SESSION_ACTIVE || (PHP_SAPI === 'cli' && isset($_SESSION));
    }
}
