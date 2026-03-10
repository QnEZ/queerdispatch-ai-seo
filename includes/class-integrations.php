<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Integrations
{
    public static function boot(): void
    {
        add_action('save_post', [self::class, 'sync_supported_plugin_meta'], 20, 2);
    }

    public static function sync_supported_plugin_meta(int $post_id, WP_Post $post): void
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (! in_array($post->post_type, Settings::get_enabled_post_types(), true)) {
            return;
        }

        if ('1' !== (string) Settings::get_option('enable_plugin_integration', '1')) {
            return;
        }

        $seo_title = (string) get_post_meta($post_id, Meta::META_KEYS['seo_title'], true);
        $meta_description = (string) get_post_meta($post_id, Meta::META_KEYS['meta_description'], true);
        $social_title = (string) get_post_meta($post_id, Meta::META_KEYS['social_title'], true);
        $social_description = (string) get_post_meta($post_id, Meta::META_KEYS['social_description'], true);
        $focus_keyphrase = (string) get_post_meta($post_id, Meta::META_KEYS['focus_keyphrase'], true);

        if ('' === $seo_title && '' === $meta_description && '' === $social_title && '' === $social_description && '' === $focus_keyphrase) {
            return;
        }

        if (defined('WPSEO_VERSION')) {
            self::sync_yoast($post_id, [
                'focus_keyphrase' => $focus_keyphrase,
                'seo_title' => $seo_title,
                'meta_description' => $meta_description,
                'social_title' => $social_title,
                'social_description' => $social_description,
            ]);
        }

        if (defined('RANK_MATH_VERSION')) {
            self::sync_rank_math($post_id, [
                'focus_keyphrase' => $focus_keyphrase,
                'seo_title' => $seo_title,
                'meta_description' => $meta_description,
                'social_title' => $social_title,
                'social_description' => $social_description,
            ]);
        }
    }

    private static function sync_yoast(int $post_id, array $data): void
    {
        self::maybe_update_meta($post_id, '_yoast_wpseo_focuskw', $data['focus_keyphrase'] ?? '');
        self::maybe_update_meta($post_id, '_yoast_wpseo_title', $data['seo_title'] ?? '');
        self::maybe_update_meta($post_id, '_yoast_wpseo_metadesc', $data['meta_description'] ?? '');
        self::maybe_update_meta($post_id, '_yoast_wpseo_opengraph-title', $data['social_title'] ?? '');
        self::maybe_update_meta($post_id, '_yoast_wpseo_opengraph-description', $data['social_description'] ?? '');
        self::maybe_update_meta($post_id, '_yoast_wpseo_twitter-title', $data['social_title'] ?? '');
        self::maybe_update_meta($post_id, '_yoast_wpseo_twitter-description', $data['social_description'] ?? '');
    }

    private static function sync_rank_math(int $post_id, array $data): void
    {
        self::maybe_update_meta($post_id, 'rank_math_focus_keyword', $data['focus_keyphrase'] ?? '');
        self::maybe_update_meta($post_id, 'rank_math_title', $data['seo_title'] ?? '');
        self::maybe_update_meta($post_id, 'rank_math_description', $data['meta_description'] ?? '');
        self::maybe_update_meta($post_id, 'rank_math_facebook_title', $data['social_title'] ?? '');
        self::maybe_update_meta($post_id, 'rank_math_facebook_description', $data['social_description'] ?? '');
        self::maybe_update_meta($post_id, 'rank_math_twitter_title', $data['social_title'] ?? '');
        self::maybe_update_meta($post_id, 'rank_math_twitter_description', $data['social_description'] ?? '');
    }

    private static function maybe_update_meta(int $post_id, string $meta_key, string $value): void
    {
        $clean = sanitize_text_field($value);
        if ('' === $clean) {
            return;
        }

        update_post_meta($post_id, $meta_key, $clean);
    }

    public static function get_active_integrations(): array
    {
        return [
            'yoast' => defined('WPSEO_VERSION'),
            'rank_math' => defined('RANK_MATH_VERSION'),
        ];
    }
}
