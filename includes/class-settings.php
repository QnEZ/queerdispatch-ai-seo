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

        foreach (['enable_excerpt', 'enable_social', 'enable_internal_links', 'enable_frontend_meta', 'enable_headline_variants'] as $key) {
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

        return [
            'api_key'                => $api_key,
            'model'                  => isset($input['model']) ? sanitize_text_field((string) $input['model']) : $defaults['model'],
            'brand_voice'            => isset($input['brand_voice']) ? sanitize_textarea_field((string) $input['brand_voice']) : $defaults['brand_voice'],
            'title_formula'          => isset($input['title_formula']) ? sanitize_text_field((string) $input['title_formula']) : $defaults['title_formula'],
            'ai_disclosure_template' => isset($input['ai_disclosure_template']) ? sanitize_textarea_field((string) $input['ai_disclosure_template']) : $defaults['ai_disclosure_template'],
            'enabled_post_types'     => $post_types,
            'enable_excerpt'         => empty($input['enable_excerpt']) ? '0' : '1',
            'enable_social'          => empty($input['enable_social']) ? '0' : '1',
            'enable_internal_links'  => empty($input['enable_internal_links']) ? '0' : '1',
            'enable_frontend_meta'   => empty($input['enable_frontend_meta']) ? '0' : '1',
            'enable_headline_variants' => empty($input['enable_headline_variants']) ? '0' : '1',
        ];
    }

    public static function defaults(): array
    {
        return [
            'api_key'                => '',
            'model'                  => 'gpt-5-mini',
            'brand_voice'            => 'QueerDispatch voice: sharp, activist, credible, emotionally resonant, but fact-aware and not libelous. Avoid sensationalism that weakens trust. Prefer direct language, strong verbs, concise summaries, and search-friendly clarity.',
            'title_formula'          => '%headline% | QueerDispatch',
            'ai_disclosure_template' => 'AI disclosure: ChatGPT assisted with SEO drafting, headline options, metadata, and/or editing support. A human editor reviewed the final published version.',
            'enabled_post_types'     => ['post'],
            'enable_excerpt'         => '1',
            'enable_social'          => '1',
            'enable_internal_links'  => '1',
            'enable_frontend_meta'   => '0',
            'enable_headline_variants' => '1',
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

            case 'enable_excerpt':
            case 'enable_social':
            case 'enable_internal_links':
            case 'enable_frontend_meta':
            case 'enable_headline_variants':
                echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked('1', (string) $value, false) . ' /> ' . esc_html__('Enabled', 'queerdispatch-ai-seo') . '</label>';
                break;
        }
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('QueerDispatch AI SEO', 'queerdispatch-ai-seo'); ?></h1>
            <p><?php echo esc_html__('Use this plugin as an editorial generation layer. Pair it with a traditional SEO plugin if you want XML sitemaps, canonicals, or deeper technical SEO.', 'queerdispatch-ai-seo'); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields('qd_ai_seo');
                do_settings_sections('qd-ai-seo');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
