<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Bulk_Actions
{
    private const ACTION = 'qd_generate_ai_seo';

    public static function boot(): void
    {
        foreach (Settings::get_enabled_post_types() as $post_type) {
            add_filter("bulk_actions-edit-{$post_type}", [self::class, 'register_bulk_action']);
            add_filter("handle_bulk_actions-edit-{$post_type}", [self::class, 'handle_bulk_action'], 10, 3);
        }

        add_action('admin_notices', [self::class, 'admin_notice']);
    }

    public static function register_bulk_action(array $actions): array
    {
        $actions[self::ACTION] = __('Generate AI SEO package', 'queerdispatch-ai-seo');
        return $actions;
    }

    public static function handle_bulk_action(string $redirect_to, string $action, array $post_ids): string
    {
        if (self::ACTION !== $action) {
            return $redirect_to;
        }

        $success = 0;
        $failed = 0;
        $processed = 0;

        foreach (array_slice(array_map('absint', $post_ids), 0, 20) as $post_id) {
            $post = get_post($post_id);
            if (! $post instanceof WP_Post || ! current_user_can('edit_post', $post_id)) {
                $failed++;
                continue;
            }

            $mode = (string) get_post_meta($post_id, Meta::META_KEYS['article_mode'], true);
            if ('' === $mode) {
                $mode = 'news';
            }

            $result = (new OpenAI_Client())->generate_seo_package(Rest_API::build_payload($post, null, $mode));
            if (is_wp_error($result)) {
                $failed++;
                continue;
            }

            self::persist_result($post_id, $result, $mode);
            $success++;
            $processed++;
        }

        return add_query_arg([
            'qd_bulk_generated' => $success,
            'qd_bulk_failed' => $failed,
            'qd_bulk_processed' => $processed,
        ], $redirect_to);
    }

    private static function persist_result(int $post_id, array $result, string $mode): void
    {
        update_post_meta($post_id, Meta::META_KEYS['focus_keyphrase'], $result['focus_keyphrase'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['keyphrase_variants'], Meta::sanitize_array($result['keyphrase_variants'] ?? [], 'string'));
        update_post_meta($post_id, Meta::META_KEYS['headline_variants'], Meta::sanitize_array($result['headline_variants'] ?? [], 'string'));
        update_post_meta($post_id, Meta::META_KEYS['seo_title'], $result['seo_title'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['meta_description'], $result['meta_description'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['social_title'], $result['social_title'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['social_description'], $result['social_description'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['excerpt_suggestion'], $result['excerpt_suggestion'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['ai_disclosure'], $result['ai_disclosure'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['analysis_notes'], $result['analysis_notes'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['article_mode'], sanitize_key($mode));
        update_post_meta($post_id, Meta::META_KEYS['infographic_prompt'], $result['infographic_prompt'] ?? '');
        update_post_meta($post_id, Meta::META_KEYS['social_posts'], Meta::sanitize_array($result['social_posts'] ?? [], 'social_object'));
        update_post_meta($post_id, Meta::META_KEYS['internal_link_suggestions'], Meta::sanitize_array($result['internal_link_suggestions'] ?? [], 'link_object'));
        Integrations::sync_generated_meta($post_id);
    }

    public static function admin_notice(): void
    {
        if (! isset($_REQUEST['qd_bulk_generated'])) {
            return;
        }

        $generated = absint((string) ($_REQUEST['qd_bulk_generated'] ?? 0));
        $failed = absint((string) ($_REQUEST['qd_bulk_failed'] ?? 0));
        $processed = absint((string) ($_REQUEST['qd_bulk_processed'] ?? 0));

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html(sprintf(__('QueerDispatch AI SEO generated %1$d package(s). Failed: %2$d. Processed: %3$d.', 'queerdispatch-ai-seo'), $generated, $failed, $processed));
        echo '</p></div>';
    }
}
