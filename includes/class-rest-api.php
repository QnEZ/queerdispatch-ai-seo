<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

final class Rest_API
{
    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(
            'qd-ai-seo/v1',
            '/generate',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'generate'],
                'permission_callback' => [self::class, 'can_generate'],
                'args'                => [
                    'post_id' => ['type' => 'integer', 'required' => true],
                    'content' => ['type' => 'string', 'required' => false],
                    'article_mode' => ['type' => 'string', 'required' => false],
                    'beat_preset' => ['type' => 'string', 'required' => false],
                    'visual_preset' => ['type' => 'string', 'required' => false],
                ],
            ]
        );
    }

    public static function can_generate(WP_REST_Request $request): bool|WP_Error
    {
        $post_id = absint($request->get_param('post_id'));
        if ($post_id < 1) {
            return new WP_Error('qd_ai_seo_invalid_post', __('A valid post ID is required.', 'queerdispatch-ai-seo'), ['status' => 400]);
        }

        if (! Permissions::current_user_can_generate($post_id)) {
            return new WP_Error('qd_ai_seo_cannot_edit', __('You cannot run AI generation for this post.', 'queerdispatch-ai-seo'), ['status' => 403]);
        }

        if (Permissions::user_is_over_daily_limit()) {
            return new WP_Error('qd_ai_seo_daily_limit', __('You have reached the daily AI generation limit for your account.', 'queerdispatch-ai-seo'), ['status' => 429]);
        }

        return true;
    }

    public static function generate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post_id = absint($request->get_param('post_id'));
        $post = get_post($post_id);

        if (! $post instanceof WP_Post) {
            return new WP_Error('qd_ai_seo_post_not_found', __('Post not found.', 'queerdispatch-ai-seo'), ['status' => 404]);
        }

        if (! in_array($post->post_type, Settings::get_enabled_post_types(), true)) {
            return new WP_Error('qd_ai_seo_post_type_disabled', __('This post type is not enabled in QueerDispatch AI SEO settings.', 'queerdispatch-ai-seo'), ['status' => 400]);
        }

        $article_mode = sanitize_key((string) $request->get_param('article_mode'));
        if ('' === $article_mode) {
            $saved_mode = get_post_meta($post_id, Meta::META_KEYS['article_mode'], true);
            $article_mode = is_string($saved_mode) && '' !== $saved_mode ? sanitize_key($saved_mode) : 'news';
        }

        $beat_preset = sanitize_key((string) $request->get_param('beat_preset'));
        if ('' === $beat_preset) {
            $saved_preset = get_post_meta($post_id, Meta::META_KEYS['beat_preset'], true);
            $beat_preset = is_string($saved_preset) && '' !== $saved_preset ? sanitize_key($saved_preset) : 'general';
        }

        $visual_preset = sanitize_key((string) $request->get_param('visual_preset'));
        if ('' === $visual_preset) {
            $saved_visual = get_post_meta($post_id, Meta::META_KEYS['visual_preset'], true);
            $visual_preset = is_string($saved_visual) && '' !== $saved_visual ? sanitize_key($saved_visual) : 'clean_news';
        }

        $payload = self::build_payload($post, (string) ($request->get_param('content') ?: $post->post_content), $article_mode, $beat_preset, $visual_preset);
        $result = (new OpenAI_Client())->generate_seo_package($payload);
        if (is_wp_error($result)) {
            Logger::log('rest_generate_error', ['post_id' => $post_id, 'error' => Logger::normalize_error($result)]);
            return $result;
        }

        $result['article_mode'] = $article_mode;
        $result['beat_preset'] = $beat_preset;
        $result['visual_preset'] = $visual_preset;
        Permissions::increment_generation_count_for_current_user();
        History::record($post_id, $result, $result['_stats'] ?? []);

        return new WP_REST_Response(['data' => $result], 200);
    }

    public static function build_payload(WP_Post $post, ?string $content = null, string $article_mode = 'news', string $beat_preset = 'general', string $visual_preset = 'clean_news'): array
    {
        $post_id = (int) $post->ID;
        $thumbnail_id = (int) get_post_thumbnail_id($post_id);

        return [
            'site_name' => get_bloginfo('name'),
            'site_url'  => home_url('/'),
            'post'      => [
                'id'          => $post_id,
                'type'        => $post->post_type,
                'status'      => $post->post_status,
                'title'       => get_the_title($post_id),
                'slug'        => $post->post_name,
                'excerpt'     => wp_strip_all_tags((string) $post->post_excerpt),
                'content'     => (string) ($content ?? $post->post_content),
                'featured_image_alt' => (string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
                'featured_image_caption' => $thumbnail_id > 0 ? wp_get_attachment_caption($thumbnail_id) : '',
                'categories'  => self::get_post_terms($post_id, 'category'),
                'tags'        => self::get_post_terms($post_id, 'post_tag'),
            ],
            'settings'  => [
                'title_formula'          => Settings::get_option('title_formula', '%headline% | QueerDispatch'),
                'brand_voice'            => Settings::get_option('brand_voice', ''),
                'ai_disclosure_template' => Settings::get_option('ai_disclosure_template', ''),
                'article_mode'           => $article_mode,
                'beat_preset'            => $beat_preset,
                'visual_preset'          => $visual_preset,
                'beat_preset_labels'     => Settings::get_beat_presets(),
                'visual_preset_labels'   => Settings::get_visual_presets(),
                'features'               => [
                    'excerpt'              => (bool) Settings::get_option('enable_excerpt', '1'),
                    'social'               => (bool) Settings::get_option('enable_social', '1'),
                    'internal_links'       => (bool) Settings::get_option('enable_internal_links', '1'),
                    'headline_variants'    => (bool) Settings::get_option('enable_headline_variants', '1'),
                    'social_posts'         => (bool) Settings::get_option('enable_social_posts', '1'),
                    'infographic_prompt'   => (bool) Settings::get_option('enable_infographic_prompt', '1'),
                    'image_metadata'       => (bool) Settings::get_option('enable_image_metadata', '1'),
                    'story_package'        => (bool) Settings::get_option('enable_story_package', '1'),
                ],
            ],
            'internal_link_candidates' => self::get_internal_link_candidates($post_id, (string) ($content ?? $post->post_content)),
        ];
    }

    private static function get_post_terms(int $post_id, string $taxonomy): array
    {
        $terms = get_the_terms($post_id, $taxonomy);
        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_map(static fn($term): string => sanitize_text_field((string) $term->name), $terms));
    }

    private static function get_internal_link_candidates(int $post_id, string $content): array
    {
        if ('1' !== (string) Settings::get_option('enable_internal_links', '1')) {
            return [];
        }

        $words = preg_split('/\s+/', strtolower(wp_strip_all_tags($content))) ?: [];
        $keywords = array_values(array_unique(array_filter($words, static fn(string $word): bool => strlen($word) > 5)));
        $keywords = array_slice($keywords, 0, 10);

        $query = new WP_Query([
            'post_type' => Settings::get_enabled_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => 8,
            'post__not_in' => [$post_id],
            'ignore_sticky_posts' => true,
        ]);

        $results = [];
        foreach ($query->posts as $candidate) {
            $title = get_the_title($candidate->ID);
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains(strtolower($title), $keyword)) {
                    $score += 3;
                }
            }
            $categories = self::get_post_terms((int) $candidate->ID, 'category');
            $tags = self::get_post_terms((int) $candidate->ID, 'post_tag');
            $score += count($categories) + count($tags);

            $results[] = [
                'post_id' => (int) $candidate->ID,
                'title' => $title,
                'url' => get_permalink($candidate->ID),
                'anchor' => $title,
                'reason' => sprintf(__('Related post score: %d', 'queerdispatch-ai-seo'), $score),
                '_score' => $score,
            ];
        }
        wp_reset_postdata();

        usort($results, static fn(array $a, array $b): int => (int) ($b['_score'] ?? 0) <=> (int) ($a['_score'] ?? 0));
        $results = array_slice($results, 0, 5);
        return array_map(static function (array $item): array {
            unset($item['_score']);
            return $item;
        }, $results);
    }
}
