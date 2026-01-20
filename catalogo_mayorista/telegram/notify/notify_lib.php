<?php
declare(strict_types=1);
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/config_tg.php';
require_once __DIR__ . '/notify_config.php';

final class NotifyLib
{
    public static bool $DEBUG = false;

    public static function isCli(): bool {
        return PHP_SAPI === 'cli';
    }

    public static function esc($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function db(): PDO {
        return cartDb();
    }

    /** Lee estado incremental desde archivo en el mismo directorio (sin carpeta state). */
    public static function stateRead(string $file, $default = 0) {
        $path = __DIR__ . '/' . ltrim($file, '/');
        if (!is_file($path)) return $default;
        $raw = trim((string)@file_get_contents($path));
        if ($raw === '') return $default;

        if (($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            return is_array($j) ? $j : $default;
        }

        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        return $default;
    }

    public static function stateWrite(string $file, $value): void {
        $path = __DIR__ . '/' . ltrim($file, '/');
        // Validación de escritura
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            // En hosting a veces el dir es escribible pero is_writable falla; intentamos igual.
        }

        if (is_array($value)) {
            @file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            @file_put_contents($path, (string)$value);
        }
    }

    public static function chatIds(string $type): array {
        $cfg = $GLOBALS['NOTIFY_CHAT_IDS'] ?? [];
        $ids = $cfg[$type] ?? [];
        if (!is_array($ids)) return [];
        // Sanitizar
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id !== '') $out[] = $id;
        }
        return $out;
    }

    public static function tgSend(array $chatIds, string $text): void {
        if (self::$DEBUG) {
            echo "\n--- MENSAJE (" . implode(',', $chatIds) . ") ---\n{$text}\n";
            return;
        }
        foreach ($chatIds as $chatId) {
            $resp = sendMessage((string)$chatId, $text, 'HTML', true);
            if (!($resp['ok'] ?? false)) {
                error_log('notify tgSend FAIL: ' . json_encode($resp, JSON_UNESCAPED_UNICODE));
            }
            usleep(120000);
        }
    }

    public static function out(string $s): void {
        if (self::isCli()) { echo $s; return; }
        if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
        echo $s;
    }
}
