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
        'headline_variants' => 'qd_headline_variants',
    ];

    public static function boot(): void
    {
        add_action('init', [self::class, 'register_meta']);
    }

    public static function register_meta(): void
    {
        foreach (Settings::get_enabled_post_types() as $post_type) {
            foreach ([
                self::META_KEYS['focus_keyphrase'],
                self::META_KEYS['seo_title'],
                self::META_KEYS['meta_description'],
                self::META_KEYS['social_title'],
                self::META_KEYS['social_description'],
                self::META_KEYS['ai_disclosure'],
                self::META_KEYS['excerpt_suggestion'],
                self::META_KEYS['analysis_notes'],
            ] as $meta_key) {
                self::register_string($post_type, $meta_key, 2000);
            }

            self::register_array($post_type, self::META_KEYS['keyphrase_variants'], 'string');
            self::register_array($post_type, self::META_KEYS['headline_variants'], 'string');
            self::register_array($post_type, self::META_KEYS['internal_link_suggestions'], 'object');
        }
    }

    private static function register_string(string $post_type, string $meta_key, int $max_length = 500): void
    {
        register_post_meta(
            $post_type,
            $meta_key,
            [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'sanitize_callback' => static function (mixed $value) use ($max_length): string {
                    $value = is_scalar($value) ? sanitize_textarea_field((string) $value) : '';
                    if (mb_strlen($value) > $max_length) {
                        $value = mb_substr($value, 0, $max_length);
                    }
                    return $value;
                },
                'auth_callback'     => static function (): bool {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    private static function register_array(string $post_type, string $meta_key, string $item_type): void
    {
        $items_schema = 'object' === $item_type
            ? [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'anchor' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ]
            : [
                'type' => 'string',
            ];

        register_post_meta(
            $post_type,
            $meta_key,
            [
                'single'            => true,
                'type'              => 'array',
                'auth_callback'     => static function (): bool {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => static function (mixed $value) use ($item_type): array {
                    return self::sanitize_array($value, $item_type);
                },
                'show_in_rest'      => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => $items_schema,
                    ],
                ],
            ]
        );
    }

    public static function sanitize_array(mixed $value, string $item_type = 'string'): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitized = [];
        foreach ($value as $item) {
            if ('object' === $item_type) {
                if (! is_array($item)) {
                    continue;
                }

                $sanitized[] = [
                    'post_id' => isset($item['post_id']) ? absint($item['post_id']) : 0,
                    'title' => isset($item['title']) ? sanitize_text_field((string) $item['title']) : '',
                    'url' => isset($item['url']) ? esc_url_raw((string) $item['url']) : '',
                    'anchor' => isset($item['anchor']) ? sanitize_text_field((string) $item['anchor']) : '',
                    'reason' => isset($item['reason']) ? sanitize_textarea_field((string) $item['reason']) : '',
                ];
                continue;
            }

            if (! is_scalar($item) && null !== $item) {
                continue;
            }

            $text = sanitize_text_field((string) $item);
            if ('' !== $text) {
                $sanitized[] = $text;
            }
        }

        return array_values($sanitized);
    }
}
