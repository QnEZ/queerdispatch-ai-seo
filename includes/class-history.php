<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

if (! defined('ABSPATH')) {
    exit;
}

final class History
{
    private const MAX_ENTRIES = 10;

    public static function record(int $post_id, array $result, array $stats = []): void
    {
        $history = get_post_meta($post_id, Meta::META_KEYS['generation_history'], true);
        if (! is_array($history)) {
            $history = [];
        }

        $history_entry = [
            'time' => current_time('mysql', true),
            'user_id' => get_current_user_id(),
            'seo_title' => sanitize_text_field((string) ($result['seo_title'] ?? '')),
            'focus_keyphrase' => sanitize_text_field((string) ($result['focus_keyphrase'] ?? '')),
            'article_mode' => sanitize_key((string) ($result['article_mode'] ?? 'news')),
            'beat_preset' => sanitize_key((string) ($result['beat_preset'] ?? 'general')),
            'latency_ms' => absint($stats['latency_ms'] ?? 0),
            'estimated_prompt_tokens' => absint($stats['estimated_prompt_tokens'] ?? 0),
            'estimated_completion_tokens' => absint($stats['estimated_completion_tokens'] ?? 0),
        ];

        array_unshift($history, $history_entry);
        $history = array_slice($history, 0, self::MAX_ENTRIES);
        update_post_meta($post_id, Meta::META_KEYS['generation_history'], $history);
    }
}
