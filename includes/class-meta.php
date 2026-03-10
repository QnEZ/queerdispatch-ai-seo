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
        'article_mode' => 'qd_article_mode',
        'beat_preset' => 'qd_beat_preset',
        'editorial_status' => 'qd_editorial_status',
        'visual_preset' => 'qd_visual_preset',
        'social_posts' => 'qd_social_posts',
        'infographic_prompt' => 'qd_infographic_prompt',
        'featured_image_alt_suggestion' => 'qd_featured_image_alt_suggestion',
        'featured_image_caption_suggestion' => 'qd_featured_image_caption_suggestion',
        'featured_image_brief' => 'qd_featured_image_brief',
        'social_card_copy_pack' => 'qd_social_card_copy_pack',
        'story_package' => 'qd_story_package',
        'generation_history' => 'qd_generation_history',
        'visual_prompt_variants' => 'qd_visual_prompt_variants',
        'overlay_text_suggestions' => 'qd_overlay_text_suggestions',
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
                self::META_KEYS['article_mode'],
                self::META_KEYS['beat_preset'],
                self::META_KEYS['editorial_status'],
                self::META_KEYS['visual_preset'],
                self::META_KEYS['infographic_prompt'],
                self::META_KEYS['featured_image_alt_suggestion'],
                self::META_KEYS['featured_image_caption_suggestion'],
                self::META_KEYS['featured_image_brief'],
                self::META_KEYS['social_card_copy_pack'],
                self::META_KEYS['story_package'],
            ] as $meta_key) {
                self::register_string($post_type, $meta_key, 8000);
            }

            self::register_array($post_type, self::META_KEYS['keyphrase_variants'], 'string');
            self::register_array($post_type, self::META_KEYS['headline_variants'], 'string');
            self::register_array($post_type, self::META_KEYS['overlay_text_suggestions'], 'string');
            self::register_array($post_type, self::META_KEYS['internal_link_suggestions'], 'link_object');
            self::register_array($post_type, self::META_KEYS['social_posts'], 'social_object');
            self::register_array($post_type, self::META_KEYS['generation_history'], 'history_object');
            self::register_array($post_type, self::META_KEYS['visual_prompt_variants'], 'visual_prompt_object');
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
        $items_schema = match ($item_type) {
            'link_object' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'anchor' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'social_object' => [
                'type' => 'object',
                'properties' => [
                    'network' => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            'history_object' => [
                'type' => 'object',
                'properties' => [
                    'time' => ['type' => 'string'],
                    'user_id' => ['type' => 'integer'],
                    'seo_title' => ['type' => 'string'],
                    'focus_keyphrase' => ['type' => 'string'],
                    'article_mode' => ['type' => 'string'],
                    'beat_preset' => ['type' => 'string'],
                    'visual_preset' => ['type' => 'string'],
                    'latency_ms' => ['type' => 'integer'],
                    'estimated_prompt_tokens' => ['type' => 'integer'],
                    'estimated_completion_tokens' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'visual_prompt_object' => [
                'type' => 'object',
                'properties' => [
                    'format' => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                    'prompt' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ],
            default => ['type' => 'string'],
        };

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
            if ('link_object' === $item_type) {
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

            if ('social_object' === $item_type) {
                if (! is_array($item)) {
                    continue;
                }

                $network = isset($item['network']) ? sanitize_key((string) $item['network']) : '';
                $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                $body = isset($item['body']) ? sanitize_textarea_field((string) $item['body']) : '';

                if ('' === $network && '' === $body) {
                    continue;
                }

                $sanitized[] = [
                    'network' => $network,
                    'label' => '' !== $label ? $label : ucfirst(str_replace('_', ' ', $network)),
                    'body' => $body,
                ];
                continue;
            }

            if ('history_object' === $item_type) {
                if (! is_array($item)) {
                    continue;
                }

                $sanitized[] = [
                    'time' => sanitize_text_field((string) ($item['time'] ?? '')),
                    'user_id' => absint($item['user_id'] ?? 0),
                    'seo_title' => sanitize_text_field((string) ($item['seo_title'] ?? '')),
                    'focus_keyphrase' => sanitize_text_field((string) ($item['focus_keyphrase'] ?? '')),
                    'article_mode' => sanitize_key((string) ($item['article_mode'] ?? 'news')),
                    'beat_preset' => sanitize_key((string) ($item['beat_preset'] ?? 'general')),
                    'visual_preset' => sanitize_key((string) ($item['visual_preset'] ?? 'clean_news')),
                    'latency_ms' => absint($item['latency_ms'] ?? 0),
                    'estimated_prompt_tokens' => absint($item['estimated_prompt_tokens'] ?? 0),
                    'estimated_completion_tokens' => absint($item['estimated_completion_tokens'] ?? 0),
                ];
                continue;
            }

            if ('visual_prompt_object' === $item_type) {
                if (! is_array($item)) {
                    continue;
                }

                $format = sanitize_key((string) ($item['format'] ?? ''));
                $label = sanitize_text_field((string) ($item['label'] ?? ''));
                $prompt = sanitize_textarea_field((string) ($item['prompt'] ?? ''));
                if ('' === $format && '' === $prompt) {
                    continue;
                }
                $sanitized[] = [
                    'format' => $format,
                    'label' => $label,
                    'prompt' => $prompt,
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
