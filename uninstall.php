<?php
/**
 * Uninstall cleanup for QueerDispatch AI SEO.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('qd_ai_seo_settings', []);
$cleanup_enabled = is_array($settings) && ('1' === (string) ($settings['cleanup_on_uninstall'] ?? '0'));

if (! $cleanup_enabled) {
    return;
}

delete_option('qd_ai_seo_settings');
delete_option('qd_ai_seo_logs');
delete_option('qd_ai_seo_daily_generation_counts');

$meta_keys = [
    'qd_focus_keyphrase',
    'qd_keyphrase_variants',
    'qd_seo_title',
    'qd_meta_description',
    'qd_social_title',
    'qd_social_description',
    'qd_internal_link_suggestions',
    'qd_ai_disclosure',
    'qd_excerpt_suggestion',
    'qd_analysis_notes',
    'qd_headline_variants',
    'qd_article_mode',
    'qd_beat_preset',
    'qd_editorial_status',
    'qd_social_posts',
    'qd_infographic_prompt',
    'qd_featured_image_alt_suggestion',
    'qd_featured_image_caption_suggestion',
    'qd_story_package',
    'qd_generation_history',
];

global $wpdb;
foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key], ['%s']);
}
