<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class Settings
{
    private const OPTION_KEY = 'qd_ai_seo_settings';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'register_settings_page']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_settings_page(): void
    {
        add_options_page(
            __('QueerDispatch AI SEO', 'queerdispatch-ai-seo'),
            __('QueerDispatch AI SEO', 'queerdispatch-ai-seo'),
            'manage_options',
            'qd-ai-seo',
            [self::class, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting(
            'qd_ai_seo',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default'           => self::defaults(),
                'show_in_rest'      => false,
            ]
        );

        add_settings_section(
            'qd_ai_seo_main',
            __('AI configuration', 'queerdispatch-ai-seo'),
            static function (): void {
                echo '<p>' . esc_html__('Store your OpenAI API key here, choose supported post types, and tune the QueerDispatch editorial voice. Editors still review the output before publishing.', 'queerdispatch-ai-seo') . '</p>';
            },
            'qd-ai-seo'
        );

        foreach ([
            'api_key' => __('OpenAI API key', 'queerdispatch-ai-seo'),
            'model' => __('Model', 'queerdispatch-ai-seo'),
            'brand_voice' => __('Brand voice', 'queerdispatch-ai-seo'),
            'title_formula' => __('Title formula', 'queerdispatch-ai-seo'),
            'ai_disclosure_template' => __('AI disclosure template', 'queerdispatch-ai-seo'),
            'enabled_post_types' => __('Enabled post types', 'queerdispatch-ai-seo'),
        ] as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [self::class, 'render_field'],
                'qd-ai-seo',
                'qd_ai_seo_main',
                ['key' => $key]
            );
        }

        foreach ([
            'enable_excerpt',
            'enable_social',
            'enable_internal_links',
            'enable_frontend_meta',
            'enable_headline_variants',
            'enable_plugin_integration',
            'enable_social_posts',
            'enable_infographic_prompt',
            'enable_image_metadata',
            'enable_story_package',
            'auto_insert_disclosure_block',
            'enable_post_list_columns',
        ] as $key) {
            add_settings_field(
                $key,
                ucwords(str_replace('_', ' ', $key)),
                [self::class, 'render_field'],
                'qd-ai-seo',
                'qd_ai_seo_main',
                ['key' => $key]
            );
        }
    }

    public static function sanitize_settings(mixed $input): array
    {
        $defaults = self::defaults();
        $input = is_array($input) ? $input : [];
        $existing = get_option(self::OPTION_KEY, $defaults);
        $raw_api_key = isset($input['api_key']) ? trim((string) $input['api_key']) : '';
        $api_key = $existing['api_key'] ?? '';

        if ('' !== $raw_api_key && 0 !== strpos($raw_api_key, '••••')) {
            $api_key = sanitize_text_field($raw_api_key);
        }

        $post_types = isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])
            ? array_values(array_filter(array_map('sanitize_key', $input['enabled_post_types'])))
            : $defaults['enabled_post_types'];

        if ([] === $post_types) {
            $post_types = ['post'];
        }

        $checkboxes = [
            'enable_excerpt',
            'enable_social',
            'enable_internal_links',
            'enable_frontend_meta',
            'enable_headline_variants',
            'enable_plugin_integration',
            'enable_social_posts',
            'enable_infographic_prompt',
            'enable_image_metadata',
            'enable_story_package',
            'auto_insert_disclosure_block',
            'enable_post_list_columns',
        ];

        $output = [
            'api_key'                => $api_key,
            'model'                  => isset($input['model']) ? sanitize_text_field((string) $input['model']) : $defaults['model'],
            'brand_voice'            => isset($input['brand_voice']) ? sanitize_textarea_field((string) $input['brand_voice']) : $defaults['brand_voice'],
            'title_formula'          => isset($input['title_formula']) ? sanitize_text_field((string) $input['title_formula']) : $defaults['title_formula'],
            'ai_disclosure_template' => isset($input['ai_disclosure_template']) ? sanitize_textarea_field((string) $input['ai_disclosure_template']) : $defaults['ai_disclosure_template'],
            'enabled_post_types'     => $post_types,
        ];

        foreach ($checkboxes as $key) {
            $output[$key] = empty($input[$key]) ? '0' : '1';
        }

        return $output;
    }

    public static function defaults(): array
    {
        return [
            'api_key'                => '',
            'model'                  => 'gpt-5-mini',
            'brand_voice'            => 'QueerDispatch voice: sharp, activist, credible, emotionally resonant, but fact-aware and not libelous. Avoid sensationalism that weakens trust. Prefer direct language, strong verbs, concise summaries, and search-friendly clarity.',
            'title_formula'          => '%headline% | QueerDispatch',
            'ai_disclosure_template' => 'AI disclosure: ChatGPT assisted with SEO drafting, headline options, metadata, social copy, and/or editing support. A human editor reviewed the final published version.',
            'enabled_post_types'     => ['post'],
            'enable_excerpt'         => '1',
            'enable_social'          => '1',
            'enable_internal_links'  => '1',
            'enable_frontend_meta'   => '0',
            'enable_headline_variants' => '1',
            'enable_plugin_integration' => '1',
            'enable_social_posts'    => '1',
            'enable_infographic_prompt' => '1',
            'enable_image_metadata'  => '1',
            'enable_story_package'   => '1',
            'auto_insert_disclosure_block' => '0',
            'enable_post_list_columns' => '1',
        ];
    }

    public static function get_option(string $key, mixed $default = null): mixed
    {
        $settings = get_option(self::OPTION_KEY, self::defaults());

        return $settings[$key] ?? $default;
    }

    public static function get_enabled_post_types(): array
    {
        $post_types = self::get_option('enabled_post_types', ['post']);
        if (! is_array($post_types) || [] === $post_types) {
            return ['post'];
        }

        return array_values(array_filter(array_map('sanitize_key', $post_types)));
    }

    public static function get_beat_presets(): array
    {
        return [
            'general' => __('General QueerDispatch', 'queerdispatch-ai-seo'),
            'policy_watch' => __('Policy Watch', 'queerdispatch-ai-seo'),
            'state_alert' => __('State Alert', 'queerdispatch-ai-seo'),
            'media_watch' => __('Media Watch', 'queerdispatch-ai-seo'),
            'community_voice' => __('Community Voice', 'queerdispatch-ai-seo'),
            'rights_explainer' => __('Rights Explainer', 'queerdispatch-ai-seo'),
        ];
    }

    public static function get_editorial_statuses(): array
    {
        return [
            'drafted' => __('Drafted', 'queerdispatch-ai-seo'),
            'reviewed' => __('Reviewed', 'queerdispatch-ai-seo'),
            'approved' => __('Approved', 'queerdispatch-ai-seo'),
        ];
    }

    public static function mask_api_key(string $value): string
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return '';
        }

        $length = strlen($trimmed);
        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return substr($trimmed, 0, 3) . str_repeat('•', max(0, $length - 7)) . substr($trimmed, -4);
    }

    public static function render_field(array $args): void
    {
        $key = isset($args['key']) ? (string) $args['key'] : '';
        $name = self::OPTION_KEY . '[' . $key . ']';
        $value = self::get_option($key, self::defaults()[$key] ?? '');

        switch ($key) {
            case 'api_key':
                echo '<input type="password" class="regular-text" autocomplete="off" name="' . esc_attr($name) . '" value="' . esc_attr(self::mask_api_key((string) $value)) . '" placeholder="sk-..." />';
                echo '<p class="description">' . esc_html__('The saved key is masked here. Paste a new key to replace it. Leave unchanged to keep the current one.', 'queerdispatch-ai-seo') . '</p>';
                break;

            case 'model':
            case 'title_formula':
                echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
                break;

            case 'brand_voice':
            case 'ai_disclosure_template':
                echo '<textarea class="large-text" rows="5" name="' . esc_attr($name) . '">' . esc_textarea((string) $value) . '</textarea>';
                break;

            case 'enabled_post_types':
                $post_types = get_post_types(['show_ui' => true], 'objects');
                $selected = is_array($value) ? $value : ['post'];
                foreach ($post_types as $post_type) {
                    echo '<label style="display:block;margin-bottom:6px;">';
                    echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($post_type->name) . '" ' . checked(in_array($post_type->name, $selected, true), true, false) . ' /> ';
                    echo esc_html($post_type->labels->singular_name);
                    echo '</label>';
                }
                echo '<p class="description">' . esc_html__('Only enabled post types will show the editor sidebar and store AI SEO meta.', 'queerdispatch-ai-seo') . '</p>';
                break;

            default:
                echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked('1', (string) $value, false) . ' /> ' . esc_html__('Enabled', 'queerdispatch-ai-seo') . '</label>';
                echo self::field_description($key);
                break;
        }
    }

    private static function field_description(string $key): string
    {
        $descriptions = [
            'enable_plugin_integration' => __('When supported SEO plugins are active, copy generated fields into their meta keys on post save.', 'queerdispatch-ai-seo'),
            'enable_frontend_meta' => __('Output title, description, Open Graph, and X/Twitter tags directly from this plugin when no other SEO plugin is handling them.', 'queerdispatch-ai-seo'),
            'enable_social_posts' => __('Generate ready-to-post copy for Facebook, Bluesky, and X.', 'queerdispatch-ai-seo'),
            'enable_infographic_prompt' => __('Generate a branded image prompt for featured images or social graphics.', 'queerdispatch-ai-seo'),
            'enable_image_metadata' => __('Generate suggested featured image alt text and caption text from article context.', 'queerdispatch-ai-seo'),
            'enable_story_package' => __('Generate a copy-ready package with article SEO, social copy, and visual prompt sections.', 'queerdispatch-ai-seo'),
            'auto_insert_disclosure_block' => __('Append an AI disclosure paragraph block to supported post types when a disclosure exists and the post is saved.', 'queerdispatch-ai-seo'),
            'enable_post_list_columns' => __('Show AI workflow columns in the post list table.', 'queerdispatch-ai-seo'),
        ];

        if (! isset($descriptions[$key])) {
            return '';
        }

        return '<p class="description">' . esc_html($descriptions[$key]) . '</p>';
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'queerdispatch-ai-seo'));
        }

        $diagnostic_result = null;
        if (isset($_POST['qd_ai_seo_run_diagnostic'])) {
            check_admin_referer('qd_ai_seo_run_diagnostic');
            $diagnostic_result = OpenAI_Client::run_diagnostic();
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('QueerDispatch AI SEO', 'queerdispatch-ai-seo') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('qd_ai_seo');
        do_settings_sections('qd-ai-seo');
        submit_button();
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Connection diagnostic', 'queerdispatch-ai-seo') . '</h2>';
        echo '<p>' . esc_html__('Run a lightweight API check using the currently saved model and API key.', 'queerdispatch-ai-seo') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('qd_ai_seo_run_diagnostic');
        submit_button(__('Run OpenAI diagnostic', 'queerdispatch-ai-seo'), 'secondary', 'qd_ai_seo_run_diagnostic', false);
        echo '</form>';

        if (null !== $diagnostic_result) {
            echo '<div style="margin-top:16px;">';
            if (is_wp_error($diagnostic_result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($diagnostic_result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>';
                echo esc_html(sprintf(__('Diagnostic OK. Model: %1$s. Latency: %2$dms. Response: %3$s', 'queerdispatch-ai-seo'), (string) ($diagnostic_result['model'] ?? ''), (int) ($diagnostic_result['latency_ms'] ?? 0), (string) ($diagnostic_result['content'] ?? '')));
                echo '</p></div>';
            }
            echo '</div>';
        }

        echo '</div>';
    }
}
