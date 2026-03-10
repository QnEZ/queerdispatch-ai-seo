<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class Logger
{
    private const OPTION_KEY = 'qd_ai_seo_logs';
    private const MAX_ENTRIES = 100;

    public static function log(string $event, array $context = []): void
    {
        if ('1' !== (string) Settings::get_option('enable_logging', '1')) {
            return;
        }

        $logs = get_option(self::OPTION_KEY, []);
        if (! is_array($logs)) {
            $logs = [];
        }

        $entry = [
            'time' => current_time('mysql', true),
            'event' => sanitize_key($event),
            'user_id' => get_current_user_id(),
            'context' => self::sanitize_context($context),
        ];

        array_unshift($logs, $entry);
        $logs = array_slice($logs, 0, self::MAX_ENTRIES);
        update_option(self::OPTION_KEY, $logs, false);
    }

    public static function get_logs(int $limit = 20): array
    {
        $logs = get_option(self::OPTION_KEY, []);
        if (! is_array($logs)) {
            return [];
        }

        return array_slice($logs, 0, max(1, $limit));
    }

    public static function clear_logs(): void
    {
        delete_option(self::OPTION_KEY);
    }

    public static function normalize_error(WP_Error $error): array
    {
        return [
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data' => $error->get_error_data(),
        ];
    }

    private static function sanitize_context(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $safe_key = sanitize_key((string) $key);
            if (is_scalar($value) || null === $value) {
                $sanitized[$safe_key] = is_string($value) ? sanitize_textarea_field($value) : $value;
                continue;
            }

            if (is_array($value)) {
                $sanitized[$safe_key] = wp_json_encode($value);
                continue;
            }

            $sanitized[$safe_key] = sanitize_text_field(wp_json_encode($value));
        }

        return $sanitized;
    }
}
