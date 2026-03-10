<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

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

        $fields = [
            'api_key' => __('OpenAI API key', 'queerdispatch-ai-seo'),
            'model' => __('Model', 'queerdispatch-ai-seo'),
            'brand_voice' => __('Brand voice', 'queerdispatch-ai-seo'),
            'title_formula' => __('Title formula', 'queerdispatch-ai-seo'),
            'ai_disclosure_template' => __('AI disclosure template', 'queerdispatch-ai-seo'),
            'enabled_post_types' => __('Enabled post types', 'queerdispatch-ai-seo'),
            'allowed_roles' => __('Allowed roles', 'queerdispatch-ai-seo'),
            'per_request_token_cap' => __('Per-request token cap', 'queerdispatch-ai-seo'),
            'daily_generation_limit' => __('Daily generation limit per user', 'queerdispatch-ai-seo'),
            'request_timeout' => __('Request timeout (seconds)', 'queerdispatch-ai-seo'),
        ];

        foreach ($fields as $key => $label) {
            add_settings_field($key, $label, [self::class, 'render_field'], 'qd-ai-seo', 'qd_ai_seo_main', ['key' => $key]);
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
            'enable_logging',
            'cleanup_on_uninstall',
        ] as $key) {
            add_settings_field($key, ucwords(str_replace('_', ' ', $key)), [self::class, 'render_field'], 'qd-ai-seo', 'qd_ai_seo_main', ['key' => $key]);
        }
    }

    public static function sanitize_settings(mixed $input): array
    {
        $defaults = self::defaults();
        $saved = get_option(self::OPTION_KEY, $defaults);
        $input = is_array($input) ? $input : [];

        $api_key = isset($input['api_key']) ? trim((string) $input['api_key']) : '';
        if ('' === $api_key || self::looks_masked($api_key)) {
            $api_key = is_array($saved) ? (string) ($saved['api_key'] ?? '') : '';
        }

        $post_types = isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])
            ? array_values(array_filter(array_map('sanitize_key', $input['enabled_post_types'])))
            : ['post'];
        if ([] === $post_types) {
            $post_types = ['post'];
        }

        $allowed_roles = isset($input['allowed_roles']) && is_array($input['allowed_roles'])
            ? array_values(array_filter(array_map('sanitize_key', $input['allowed_roles'])))
            : ['administrator', 'editor'];
        if ([] === $allowed_roles) {
            $allowed_roles = ['administrator', 'editor'];
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
            'enable_logging',
            'cleanup_on_uninstall',
        ];

        $output = [
            'api_key'                => $api_key,
            'model'                  => isset($input['model']) ? sanitize_text_field((string) $input['model']) : $defaults['model'],
            'brand_voice'            => isset($input['brand_voice']) ? sanitize_textarea_field((string) $input['brand_voice']) : $defaults['brand_voice'],
            'title_formula'          => isset($input['title_formula']) ? sanitize_text_field((string) $input['title_formula']) : $defaults['title_formula'],
            'ai_disclosure_template' => isset($input['ai_disclosure_template']) ? sanitize_textarea_field((string) $input['ai_disclosure_template']) : $defaults['ai_disclosure_template'],
            'enabled_post_types'     => $post_types,
            'allowed_roles'          => $allowed_roles,
            'per_request_token_cap'  => (string) max(2000, absint($input['per_request_token_cap'] ?? $defaults['per_request_token_cap'])),
            'daily_generation_limit' => (string) max(1, absint($input['daily_generation_limit'] ?? $defaults['daily_generation_limit'])),
            'request_timeout'        => (string) min(120, max(10, absint($input['request_timeout'] ?? $defaults['request_timeout']))),
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
            'allowed_roles'          => ['administrator', 'editor'],
            'per_request_token_cap'  => '12000',
            'daily_generation_limit' => '50',
            'request_timeout'        => '45',
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
            'enable_logging'         => '1',
            'cleanup_on_uninstall'   => '0',
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
        return is_array($post_types) && [] !== $post_types ? array_values(array_filter(array_map('sanitize_key', $post_types))) : ['post'];
    }

    public static function get_allowed_roles(): array
    {
        $roles = self::get_option('allowed_roles', ['administrator', 'editor']);
        return is_array($roles) ? array_values(array_filter(array_map('sanitize_key', $roles))) : ['administrator', 'editor'];
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

    public static function get_visual_presets(): array
    {
        return [
            'clean_news' => __('Clean News', 'queerdispatch-ai-seo'),
            'urgent_alert' => __('Urgent Alert', 'queerdispatch-ai-seo'),
            'editorial_heat' => __('Editorial Heat', 'queerdispatch-ai-seo'),
            'rights_explainer' => __('Rights Explainer', 'queerdispatch-ai-seo'),
        ];
    }

    public static function export_settings_json(): string
    {
        $settings = get_option(self::OPTION_KEY, self::defaults());
        if (is_array($settings)) {
            $settings['api_key'] = '';
        }
        return (string) wp_json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function import_settings_json(string $json): bool
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return false;
        }
        $sanitized = self::sanitize_settings($decoded);
        $existing = get_option(self::OPTION_KEY, self::defaults());
        if ('' === trim((string) ($sanitized['api_key'] ?? '')) && is_array($existing)) {
            $sanitized['api_key'] = (string) ($existing['api_key'] ?? '');
        }
        update_option(self::OPTION_KEY, $sanitized, false);
        return true;
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
            case 'per_request_token_cap':
            case 'daily_generation_limit':
            case 'request_timeout':
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
                    echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($post_type->name) . '" ' . checked(in_array($post_type->name, $selected, true), true, false) . ' /> ' . esc_html($post_type->labels->singular_name) . '</label>';
                }
                break;
            case 'allowed_roles':
                global $wp_roles;
                $roles = is_object($wp_roles) ? $wp_roles->roles : [];
                $selected = is_array($value) ? $value : ['administrator', 'editor'];
                foreach ($roles as $role_key => $role_data) {
                    echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr((string) $role_key) . '" ' . checked(in_array((string) $role_key, $selected, true), true, false) . ' /> ' . esc_html((string) ($role_data['name'] ?? $role_key)) . '</label>';
                }
                echo '<p class="description">' . esc_html__('Only these roles can run AI generation tools, unless they are administrators.', 'queerdispatch-ai-seo') . '</p>';
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
            'enable_logging' => __('Store recent request and error logs for diagnostics. Logs intentionally avoid storing full article bodies.', 'queerdispatch-ai-seo'),
            'cleanup_on_uninstall' => __('Delete plugin settings, logs, counters, and stored AI post meta when the plugin is uninstalled.', 'queerdispatch-ai-seo'),
        ];
        return isset($descriptions[$key]) ? '<p class="description">' . esc_html($descriptions[$key]) . '</p>' : '';
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'queerdispatch-ai-seo'));
        }

        $message = '';
        $message_type = 'success';
        $diagnostic_result = null;

        if (isset($_POST['qd_ai_seo_import_settings'])) {
            check_admin_referer('qd_ai_seo_import_settings');
            $ok = self::import_settings_json(wp_unslash((string) ($_POST['qd_ai_seo_settings_json'] ?? '')));
            $message = $ok ? __('Settings imported successfully.', 'queerdispatch-ai-seo') : __('Import failed. Please paste valid JSON.', 'queerdispatch-ai-seo');
            $message_type = $ok ? 'success' : 'error';
        }

        if (isset($_POST['qd_ai_seo_clear_logs'])) {
            check_admin_referer('qd_ai_seo_clear_logs');
            Logger::clear_logs();
            $message = __('Logs cleared.', 'queerdispatch-ai-seo');
        }

        if (isset($_POST['qd_ai_seo_run_diagnostic'])) {
            check_admin_referer('qd_ai_seo_run_diagnostic');
            $diagnostic_result = OpenAI_Client::run_diagnostic();
        }

        echo '<div class="wrap"><h1>' . esc_html__('QueerDispatch AI SEO', 'queerdispatch-ai-seo') . '</h1>';

        if ('' !== $message) {
            echo '<div class="notice notice-' . esc_attr($message_type) . '"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields('qd_ai_seo');
        do_settings_sections('qd-ai-seo');
        submit_button();
        echo '</form>';

        echo '<hr /><h2>' . esc_html__('Connection diagnostic', 'queerdispatch-ai-seo') . '</h2>';
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
                echo '<div class="notice notice-success"><p>' . esc_html(sprintf(__('Diagnostic OK. Model: %1$s. Latency: %2$dms. Response: %3$s', 'queerdispatch-ai-seo'), (string) ($diagnostic_result['model'] ?? ''), (int) ($diagnostic_result['latency_ms'] ?? 0), (string) ($diagnostic_result['content'] ?? ''))) . '</p></div>';
            }
            echo '</div>';
        }

        echo '<hr /><h2>' . esc_html__('Environment', 'queerdispatch-ai-seo') . '</h2>';
        echo '<table class="widefat striped" style="max-width:900px"><tbody>';
        echo '<tr><td>Plugin version</td><td>' . esc_html(QD_AI_SEO_VERSION) . '</td></tr>';
        echo '<tr><td>WordPress</td><td>' . esc_html(get_bloginfo('version')) . '</td></tr>';
        echo '<tr><td>PHP</td><td>' . esc_html(PHP_VERSION) . '</td></tr>';
        echo '<tr><td>Model</td><td>' . esc_html((string) self::get_option('model', 'gpt-5-mini')) . '</td></tr>';
        echo '<tr><td>Per-request token cap</td><td>' . esc_html((string) self::get_option('per_request_token_cap', '12000')) . '</td></tr>';
        echo '<tr><td>Daily generation limit</td><td>' . esc_html((string) self::get_option('daily_generation_limit', '50')) . '</td></tr>';
        echo '</tbody></table>';

        echo '<hr /><h2>' . esc_html__('Import / export settings', 'queerdispatch-ai-seo') . '</h2>';
        echo '<p>' . esc_html__('Export omits the saved API key. Import keeps the existing API key unless you separately paste a new one above.', 'queerdispatch-ai-seo') . '</p>';
        echo '<textarea class="large-text code" rows="12" readonly>' . esc_textarea(self::export_settings_json()) . '</textarea>';
        echo '<form method="post" style="margin-top:12px;">';
        wp_nonce_field('qd_ai_seo_import_settings');
        echo '<textarea class="large-text code" rows="12" name="qd_ai_seo_settings_json" placeholder="' . esc_attr__('Paste exported JSON here', 'queerdispatch-ai-seo') . '"></textarea>';
        echo '<p>';
        submit_button(__('Import settings JSON', 'queerdispatch-ai-seo'), 'secondary', 'qd_ai_seo_import_settings', false);
        echo '</p></form>';

        echo '<hr /><h2>' . esc_html__('Recent logs', 'queerdispatch-ai-seo') . '</h2>';
        echo '<form method="post" style="margin-bottom:12px;">';
        wp_nonce_field('qd_ai_seo_clear_logs');
        submit_button(__('Clear logs', 'queerdispatch-ai-seo'), 'secondary', 'qd_ai_seo_clear_logs', false);
        echo '</form>';
        $logs = Logger::get_logs(20);
        if ([] === $logs) {
            echo '<p>' . esc_html__('No logs yet.', 'queerdispatch-ai-seo') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Time (UTC)</th><th>Event</th><th>User</th><th>Context</th></tr></thead><tbody>';
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html((string) ($log['time'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($log['event'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($log['user_id'] ?? 0)) . '</td>';
                echo '<td><code>' . esc_html(wp_json_encode($log['context'] ?? [])) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    private static function looks_masked(string $value): bool
    {
        return str_contains($value, '•');
    }
}
