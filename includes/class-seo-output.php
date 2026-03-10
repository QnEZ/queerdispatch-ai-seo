<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

if (! defined('ABSPATH')) {
    exit;
}

final class SEO_Output
{
    public static function boot(): void
    {
        add_action('wp_head', [self::class, 'render_meta_tags'], 1);
        add_filter('pre_get_document_title', [self::class, 'filter_document_title']);
    }

    public static function has_conflicting_seo_plugin(): bool
    {
        return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION');
    }

    public static function filter_document_title(string $title): string
    {
        if (! self::should_output()) {
            return $title;
        }

        $post_id = get_queried_object_id();
        if ($post_id < 1) {
            return $title;
        }

        $custom_title = (string) get_post_meta($post_id, Meta::META_KEYS['seo_title'], true);

        return '' !== trim($custom_title) ? $custom_title : $title;
    }

    public static function render_meta_tags(): void
    {
        if (! self::should_output()) {
            return;
        }

        $post_id = get_queried_object_id();
        if ($post_id < 1) {
            return;
        }

        $seo_title = (string) get_post_meta($post_id, Meta::META_KEYS['seo_title'], true);
        $meta_description = (string) get_post_meta($post_id, Meta::META_KEYS['meta_description'], true);
        $social_title = (string) get_post_meta($post_id, Meta::META_KEYS['social_title'], true);
        $social_description = (string) get_post_meta($post_id, Meta::META_KEYS['social_description'], true);
        $title = '' !== trim($social_title) ? $social_title : ('' !== trim($seo_title) ? $seo_title : get_the_title($post_id));
        $description = '' !== trim($social_description) ? $social_description : $meta_description;
        $url = get_permalink($post_id);
        $image = get_the_post_thumbnail_url($post_id, 'full');

        if ('' !== trim($meta_description)) {
            echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
        }

        if ('' !== trim($title)) {
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        }

        if ('' !== trim($description)) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        }

        if (is_string($url) && '' !== $url) {
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        }

        if (is_string($image) && '' !== $image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }

        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }

    private static function should_output(): bool
    {
        if (self::has_conflicting_seo_plugin()) {
            return false;
        }

        return is_singular(Settings::get_enabled_post_types()) && '1' === (string) Settings::get_option('enable_frontend_meta', '0');
    }
}
