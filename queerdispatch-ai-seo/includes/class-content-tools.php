<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Content_Tools
{
    public static function boot(): void
    {
        add_action('save_post', [self::class, 'maybe_insert_disclosure_block'], 30, 2);
    }

    public static function maybe_insert_disclosure_block(int $post_id, WP_Post $post): void
    {
        if ('1' !== (string) Settings::get_option('auto_insert_disclosure_block', '0')) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (! in_array($post->post_type, Settings::get_enabled_post_types(), true)) {
            return;
        }

        $disclosure = trim((string) get_post_meta($post_id, Meta::META_KEYS['ai_disclosure'], true));
        if ('' === $disclosure) {
            return;
        }

        $content = (string) $post->post_content;
        if (str_contains($content, 'qd-ai-disclosure') || str_contains($content, $disclosure)) {
            return;
        }

        $block = "\n\n<!-- wp:paragraph {\"className\":\"qd-ai-disclosure\"} --><p class=\"qd-ai-disclosure\">" . esc_html($disclosure) . "</p><!-- /wp:paragraph -->";

        remove_action('save_post', [self::class, 'maybe_insert_disclosure_block'], 30);
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content . $block,
        ]);
        add_action('save_post', [self::class, 'maybe_insert_disclosure_block'], 30, 2);
    }
}
