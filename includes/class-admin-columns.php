<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

if (! defined('ABSPATH')) {
    exit;
}

final class Admin_Columns
{
    public static function boot(): void
    {
        add_action('current_screen', [self::class, 'register_hooks_for_screen']);
    }

    public static function register_hooks_for_screen(): void
    {
        if ('1' !== (string) Settings::get_option('enable_post_list_columns', '1')) {
            return;
        }

        foreach (Settings::get_enabled_post_types() as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", [self::class, 'add_columns']);
            add_action("manage_{$post_type}_posts_custom_column", [self::class, 'render_column'], 10, 2);
        }
    }

    public static function add_columns(array $columns): array
    {
        $columns['qd_ai_status'] = __('AI SEO', 'queerdispatch-ai-seo');
        $columns['qd_ai_mode'] = __('Article Mode', 'queerdispatch-ai-seo');
        $columns['qd_ai_beat'] = __('Beat', 'queerdispatch-ai-seo');
        $columns['qd_ai_keyphrase'] = __('Focus Keyphrase', 'queerdispatch-ai-seo');
        $columns['qd_ai_editorial'] = __('Editorial Status', 'queerdispatch-ai-seo');
        return $columns;
    }

    public static function render_column(string $column, int $post_id): void
    {
        if ('qd_ai_status' === $column) {
            $has_title = '' !== trim((string) get_post_meta($post_id, Meta::META_KEYS['seo_title'], true));
            $has_meta = '' !== trim((string) get_post_meta($post_id, Meta::META_KEYS['meta_description'], true));
            $has_social = [] !== Meta::sanitize_array(get_post_meta($post_id, Meta::META_KEYS['social_posts'], true), 'social_object');
            $has_story = '' !== trim((string) get_post_meta($post_id, Meta::META_KEYS['story_package'], true));
            $status = ($has_title && $has_meta) ? __('Ready', 'queerdispatch-ai-seo') : __('Needs review', 'queerdispatch-ai-seo');
            if ($has_social && $has_story && $has_title && $has_meta) {
                $status = __('Ready + Package', 'queerdispatch-ai-seo');
            } elseif ($has_social && $has_title && $has_meta) {
                $status = __('Ready + Social', 'queerdispatch-ai-seo');
            }
            echo esc_html($status);
            return;
        }

        if ('qd_ai_mode' === $column) {
            $mode = (string) get_post_meta($post_id, Meta::META_KEYS['article_mode'], true);
            echo esc_html(ucwords(str_replace('_', ' ', '' !== $mode ? $mode : 'news')));
            return;
        }

        if ('qd_ai_beat' === $column) {
            $beat = (string) get_post_meta($post_id, Meta::META_KEYS['beat_preset'], true);
            $labels = Settings::get_beat_presets();
            echo esc_html($labels[$beat] ?? $labels['general']);
            return;
        }

        if ('qd_ai_editorial' === $column) {
            $status = (string) get_post_meta($post_id, Meta::META_KEYS['editorial_status'], true);
            $labels = Settings::get_editorial_statuses();
            echo esc_html($labels[$status] ?? $labels['drafted']);
            return;
        }

        if ('qd_ai_keyphrase' === $column) {
            echo esc_html((string) get_post_meta($post_id, Meta::META_KEYS['focus_keyphrase'], true));
        }
    }
}
