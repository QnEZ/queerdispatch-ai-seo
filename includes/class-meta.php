<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

if (! defined('ABSPATH')) {
    exit;
}

final class Meta
{
    public const META_KEYS = [
        'focus_keyphrase' => 'qd_focus_keyphrase',
        'keyphrase_variants' => 'qd_keyphrase_variants',
        'seo_title' => 'qd_seo_title',
        'meta_description' => 'qd_meta_description',
        'social_title' => 'qd_social_title',
        'social_description' => 'qd_social_description',
        'internal_link_suggestions' => 'qd_internal_link_suggestions',
        'ai_disclosure' => 'qd_ai_disclosure',
        'excerpt_suggestion' => 'qd_excerpt_suggestion',
        'analysis_notes' => 'qd_analysis_notes',
    ];

    public static function boot(): void
    {
        add_action('init', [self::class, 'register_meta']);
    }

    public static function register_meta(): void
    {
        foreach (Settings::get_enabled_post_types() as $post_type) {
            self::register_string($post_type, self::META_KEYS['focus_keyphrase']);
            self::register_string($post_type, self::META_KEYS['seo_title']);
            self::register_string($post_type, self::META_KEYS['meta_description']);
            self::register_string($post_type, self::META_KEYS['social_title']);
            self::register_string($post_type, self::META_KEYS['social_description']);
            self::register_string($post_type, self::META_KEYS['ai_disclosure']);
            self::register_string($post_type, self::META_KEYS['excerpt_suggestion']);
            self::register_string($post_type, self::META_KEYS['analysis_notes']);
            self::register_array($post_type, self::META_KEYS['keyphrase_variants']);
            self::register_array($post_type, self::META_KEYS['internal_link_suggestions']);
        }
    }

    private static function register_string(string $post_type, string $meta_key): void
    {
        register_post_meta(
            $post_type,
            $meta_key,
            [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private static function register_array(string $post_type, string $meta_key): void
    {
        register_post_meta(
            $post_type,
            $meta_key,
            [
                'single'            => true,
                'type'              => 'array',
                'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
                'sanitize_callback' => [self::class, 'sanitize_array'],
                'show_in_rest'      => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ]
        );
    }

    public static function sanitize_array(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static function ($item) {
            if (is_scalar($item) || null === $item) {
                return sanitize_text_field((string) $item);
            }

            if (is_array($item)) {
                $sanitized = [];
                foreach ($item as $key => $sub_item) {
                    $sanitized[sanitize_key((string) $key)] = is_scalar($sub_item) || null === $sub_item
                        ? sanitize_text_field((string) $sub_item)
                        : wp_json_encode($sub_item);
                }
                return $sanitized;
            }

            return [];
        }, $value));
    }
}
