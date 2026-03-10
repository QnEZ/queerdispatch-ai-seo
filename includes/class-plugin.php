<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

if (! defined('ABSPATH')) {
    exit;
}

require_once QD_AI_SEO_PATH . 'includes/class-settings.php';
require_once QD_AI_SEO_PATH . 'includes/class-meta.php';
require_once QD_AI_SEO_PATH . 'includes/class-openai-client.php';
require_once QD_AI_SEO_PATH . 'includes/class-rest-api.php';
require_once QD_AI_SEO_PATH . 'includes/class-seo-output.php';
require_once QD_AI_SEO_PATH . 'includes/class-integrations.php';
require_once QD_AI_SEO_PATH . 'includes/class-content-tools.php';
require_once QD_AI_SEO_PATH . 'includes/class-admin-columns.php';
require_once QD_AI_SEO_PATH . 'includes/class-bulk-actions.php';

final class Plugin
{
    private static ?self $instance = null;

    public static function boot(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('admin_notices', [$this, 'maybe_show_conflict_notice']);

        Settings::boot();
        Meta::boot();
        Rest_API::boot();
        SEO_Output::boot();
        Integrations::boot();
        Content_Tools::boot();
        Admin_Columns::boot();
        Bulk_Actions::boot();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('queerdispatch-ai-seo', false, dirname(plugin_basename(QD_AI_SEO_FILE)) . '/languages');
    }

    public function register_assets(): void
    {
        wp_register_script(
            'qd-ai-seo-editor',
            QD_AI_SEO_URL . 'assets/editor.js',
            [
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-api-fetch',
                'wp-compose',
                'wp-i18n',
            ],
            QD_AI_SEO_VERSION,
            true
        );
    }

    public function enqueue_editor_assets(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (null === $screen || ! method_exists($screen, 'is_block_editor') || ! $screen->is_block_editor()) {
            return;
        }

        wp_enqueue_script('qd-ai-seo-editor');
        wp_localize_script(
            'qd-ai-seo-editor',
            'qdAiSeo',
            [
                'restUrl'       => esc_url_raw(rest_url('qd-ai-seo/v1/')),
                'nonce'         => wp_create_nonce('wp_rest'),
                'defaultModel'  => Settings::get_option('model', 'gpt-5-mini'),
                'postTypes'     => Settings::get_enabled_post_types(),
                'metaKeys'      => Meta::META_KEYS,
                'featureFlags'  => [
                    'autoExcerpt'          => (bool) Settings::get_option('enable_excerpt', '1'),
                    'socialFields'         => (bool) Settings::get_option('enable_social', '1'),
                    'internalLinks'        => (bool) Settings::get_option('enable_internal_links', '1'),
                    'frontEndMeta'         => (bool) Settings::get_option('enable_frontend_meta', '1'),
                    'headlineVariants'     => (bool) Settings::get_option('enable_headline_variants', '1'),
                    'pluginIntegration'    => (bool) Settings::get_option('enable_plugin_integration', '1'),
                    'socialPosts'          => (bool) Settings::get_option('enable_social_posts', '1'),
                    'infographicPrompt'    => (bool) Settings::get_option('enable_infographic_prompt', '1'),
                    'autoDisclosureInsert' => (bool) Settings::get_option('auto_insert_disclosure_block', '0'),
                    'imageMetadata'       => (bool) Settings::get_option('enable_image_metadata', '1'),
                    'storyPackage'        => (bool) Settings::get_option('enable_story_package', '1'),
                ],
                'integrations'  => Integrations::get_active_integrations(),
                'beatPresets'    => Settings::get_beat_presets(),
                'editorialStatuses' => Settings::get_editorial_statuses(),
                'strings'       => [
                    'title'              => __('QueerDispatch AI SEO', 'queerdispatch-ai-seo'),
                    'generate'           => __('Generate SEO package', 'queerdispatch-ai-seo'),
                    'refresh'            => __('Regenerate', 'queerdispatch-ai-seo'),
                    'save'               => __('Save to post meta', 'queerdispatch-ai-seo'),
                    'working'            => __('Generating…', 'queerdispatch-ai-seo'),
                    'error'              => __('Something went wrong while talking to the AI service.', 'queerdispatch-ai-seo'),
                    'saved'              => __('Saved generated fields into post meta.', 'queerdispatch-ai-seo'),
                    'selectType'         => __('This post type is not enabled in QueerDispatch AI SEO settings.', 'queerdispatch-ai-seo'),
                ],
            ]
        );
    }

    public function maybe_show_conflict_notice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (null === $screen || 'settings_page_qd-ai-seo' !== $screen->id) {
            return;
        }

        if (! SEO_Output::has_conflicting_seo_plugin()) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Another SEO plugin appears to be active. Front-end meta output from QueerDispatch AI SEO should usually remain disabled to avoid duplicate tags. You can still use this plugin for editorial generation and post meta.', 'queerdispatch-ai-seo');
        echo '</p></div>';
    }
}
