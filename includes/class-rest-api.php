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
                'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
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

    public static function generate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post_id = (int) $request->get_param('post_id');
        $post = get_post($post_id);

        if (! $post instanceof WP_Post) {
            return new WP_Error('qd_ai_seo_post_not_found', __('Post not found.', 'queerdispatch-ai-seo'), ['status' => 404]);
        }

        if (! current_user_can('edit_post', $post_id)) {
            return new WP_Error('qd_ai_seo_cannot_edit', __('You cannot edit this post.', 'queerdispatch-ai-seo'), ['status' => 403]);
        }

        $client = new OpenAI_Client();
        $payload = [
            'site_name' => get_bloginfo('name'),
            'post'      => [
                'id'      => $post_id,
                'type'    => $post->post_type,
                'title'   => get_the_title($post_id),
                'slug'    => $post->post_name,
                'excerpt' => wp_strip_all_tags((string) $post->post_excerpt),
                'content' => (string) ($request->get_param('content') ?: $post->post_content),
            ],
            'settings'  => [
                'title_formula'         => Settings::get_option('title_formula', '%headline% | QueerDispatch'),
                'ai_disclosure_template'=> Settings::get_option('ai_disclosure_template', ''),
                'features'              => [
                    'excerpt'       => (bool) Settings::get_option('enable_excerpt', '1'),
                    'social'        => (bool) Settings::get_option('enable_social', '1'),
                    'internal_links'=> (bool) Settings::get_option('enable_internal_links', '1'),
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
            'posts_per_page'      => 20,
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
            $items[] = [
                'post_id' => (int) $candidate->ID,
                'title'   => get_the_title($candidate),
                'url'     => get_permalink($candidate),
                'excerpt' => wp_trim_words(wp_strip_all_tags((string) $candidate->post_content), 30),
            ];
        }

        return $items;
    }
}
