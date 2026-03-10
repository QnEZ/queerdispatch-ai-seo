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

        Settings::boot();
        Meta::boot();
        Rest_API::boot();
        SEO_Output::boot();
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

        wp_enqueue_script('qd-ai-seo-editor');
        wp_localize_script(
            'qd-ai-seo-editor',
            'qdAiSeo',
            [
                'restUrl'             => esc_url_raw(rest_url('qd-ai-seo/v1/')),
                'nonce'               => wp_create_nonce('wp_rest'),
                'defaultModel'        => Settings::get_option('model', 'gpt-5-mini'),
                'postTypes'           => Settings::get_enabled_post_types(),
                'metaKeys'            => Meta::META_KEYS,
                'featureFlags'        => [
                    'autoExcerpt'     => (bool) Settings::get_option('enable_excerpt', '1'),
                    'socialFields'    => (bool) Settings::get_option('enable_social', '1'),
                    'internalLinks'   => (bool) Settings::get_option('enable_internal_links', '1'),
                    'frontEndMeta'    => (bool) Settings::get_option('enable_frontend_meta', '1'),
                ],
                'brandVoice'          => Settings::get_option('brand_voice', ''),
                'disclosureTemplate'  => Settings::get_option('ai_disclosure_template', ''),
                'strings'             => [
                    'title'          => __('QueerDispatch AI SEO', 'queerdispatch-ai-seo'),
                    'generate'       => __('Generate SEO package', 'queerdispatch-ai-seo'),
                    'refresh'        => __('Regenerate', 'queerdispatch-ai-seo'),
                    'save'           => __('Save to post meta', 'queerdispatch-ai-seo'),
                    'working'        => __('Generating…', 'queerdispatch-ai-seo'),
                    'error'          => __('Something went wrong while talking to the AI service.', 'queerdispatch-ai-seo'),
                ],
            ]
        );
    }
}
