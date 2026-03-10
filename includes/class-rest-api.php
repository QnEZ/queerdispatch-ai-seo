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
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'content' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
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

        $client = new OpenAI_Client();
        $payload = [
            'site_name' => get_bloginfo('name'),
            'site_url'  => home_url('/'),
            'post'      => [
                'id'          => $post_id,
                'type'        => $post->post_type,
                'status'      => $post->post_status,
                'title'       => get_the_title($post_id),
                'slug'        => $post->post_name,
                'excerpt'     => wp_strip_all_tags((string) $post->post_excerpt),
                'content'     => (string) ($request->get_param('content') ?: $post->post_content),
                'featured_image_alt' => get_post_meta((int) get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true),
            ],
            'settings'  => [
                'title_formula'          => Settings::get_option('title_formula', '%headline% | QueerDispatch'),
                'brand_voice'            => Settings::get_option('brand_voice', ''),
                'ai_disclosure_template' => Settings::get_option('ai_disclosure_template', ''),
                'features'               => [
                    'excerpt'           => (bool) Settings::get_option('enable_excerpt', '1'),
                    'social'            => (bool) Settings::get_option('enable_social', '1'),
                    'internal_links'    => (bool) Settings::get_option('enable_internal_links', '1'),
                    'headline_variants' => (bool) Settings::get_option('enable_headline_variants', '1'),
                ],
            ],
            'internal_link_candidates' => self::get_internal_link_candidates($post_id),
        ];

        $result = $client->generate_seo_package($payload);
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['data' => $result], 200);
    }

    private static function get_internal_link_candidates(int $post_id): array
    {
        $query = new WP_Query([
            'post_type'           => Settings::get_enabled_post_types(),
            'post_status'         => 'publish',
            'posts_per_page'      => 24,
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

            $items[] = [
                'post_id' => (int) $candidate->ID,
                'title'   => get_the_title($candidate),
                'url'     => $url,
                'excerpt' => wp_trim_words(wp_strip_all_tags((string) $candidate->post_content), 30),
                'date'    => get_the_date('c', $candidate),
            ];
        }

        return $items;
    }
}
