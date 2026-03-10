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

        if (! current_user_can('edit_post', $post_id)) {
            return new WP_Error('qd_ai_seo_cannot_edit', __('You cannot edit this post.', 'queerdispatch-ai-seo'), ['status' => 403]);
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

        $result = (new OpenAI_Client())->generate_seo_package(self::build_payload($post, (string) ($request->get_param('content') ?: $post->post_content), $article_mode, $beat_preset));
        if (is_wp_error($result)) {
            return $result;
        }

        $result['article_mode'] = $article_mode;
        $result['beat_preset'] = $beat_preset;

        return new WP_REST_Response(['data' => $result], 200);
    }

    public static function build_payload(WP_Post $post, ?string $content = null, string $article_mode = 'news', string $beat_preset = 'general'): array
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
                'beat_preset_labels'     => Settings::get_beat_presets(),
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
            'internal_link_candidates' => self::get_internal_link_candidates($post_id),
        ];
    }

    private static function get_post_terms(int $post_id, string $taxonomy): array
    {
        $terms = get_the_terms($post_id, $taxonomy);
        if (! is_array($terms)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($term): string {
            return isset($term->name) ? sanitize_text_field((string) $term->name) : '';
        }, $terms)));
    }

    private static function get_internal_link_candidates(int $post_id): array
    {
        $current_title = (string) get_the_title($post_id);
        $current_categories = self::get_post_terms($post_id, 'category');
        $current_tags = self::get_post_terms($post_id, 'post_tag');

        $query = new WP_Query([
            'post_type'           => Settings::get_enabled_post_types(),
            'post_status'         => 'publish',
            'posts_per_page'      => 60,
            'post__not_in'        => [$post_id],
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);

        if (! $query->have_posts()) {
            return [];
        }

        $items = [];
        foreach ($query->posts as $candidate) {
            if (! $candidate instanceof WP_Post) {
                continue;
            }

            $url = get_permalink($candidate);
            if (! is_string($url) || '' === $url) {
                continue;
            }

            $candidate_categories = self::get_post_terms((int) $candidate->ID, 'category');
            $candidate_tags = self::get_post_terms((int) $candidate->ID, 'post_tag');
            $score = self::score_candidate(
                $current_title,
                (string) get_the_title($candidate),
                $current_categories,
                $candidate_categories,
                $current_tags,
                $candidate_tags,
                (string) $candidate->post_date_gmt
            );

            $items[] = [
                'post_id' => (int) $candidate->ID,
                'title'   => get_the_title($candidate),
                'url'     => $url,
                'excerpt' => wp_trim_words(wp_strip_all_tags((string) $candidate->post_content), 30),
                'date'    => get_the_date('c', $candidate),
                'categories' => $candidate_categories,
                'tags' => $candidate_tags,
                'score'   => $score,
            ];
        }

        usort($items, static fn(array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($items, 0, 24);
    }

    private static function score_candidate(
        string $current_title,
        string $candidate_title,
        array $current_categories,
        array $candidate_categories,
        array $current_tags,
        array $candidate_tags,
        string $candidate_date_gmt
    ): int {
        $score = 0;

        $shared_categories = count(array_intersect(array_map('strtolower', $current_categories), array_map('strtolower', $candidate_categories)));
        $shared_tags = count(array_intersect(array_map('strtolower', $current_tags), array_map('strtolower', $candidate_tags)));
        $score += $shared_categories * 20;
        $score += $shared_tags * 12;
        $score += self::title_similarity_score($current_title, $candidate_title);

        $timestamp = strtotime($candidate_date_gmt);
        if (false !== $timestamp) {
            $age_days = max(0, (time() - $timestamp) / DAY_IN_SECONDS);
            $score += max(0, 20 - (int) floor($age_days / 14));
        }

        return $score;
    }

    private static function title_similarity_score(string $left, string $right): int
    {
        $left_words = self::normalize_keywords($left);
        $right_words = self::normalize_keywords($right);
        if ([] === $left_words || [] === $right_words) {
            return 0;
        }

        return count(array_intersect($left_words, $right_words)) * 8;
    }

    private static function normalize_keywords(string $text): array
    {
        $text = strtolower(wp_strip_all_tags($text));
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?: '';
        $parts = preg_split('/\s+/', $text) ?: [];
        $parts = array_filter($parts, static function (string $word): bool {
            return strlen($word) > 3 && ! in_array($word, ['with', 'from', 'that', 'this', 'have', 'will', 'about', 'into'], true);
        });

        return array_values(array_unique($parts));
    }
}
