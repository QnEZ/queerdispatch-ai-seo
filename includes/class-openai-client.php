<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class OpenAI_Client
{
    private string $api_key;
    private string $model;

    public function __construct(?string $api_key = null, ?string $model = null)
    {
        $this->api_key = $api_key ?: (string) Settings::get_option('api_key', '');
        $this->model   = $model ?: (string) Settings::get_option('model', 'gpt-5-mini');
    }

    public function is_configured(): bool
    {
        return '' !== trim($this->api_key);
    }

    public function generate_seo_package(array $payload): array|WP_Error
    {
        if (! $this->is_configured()) {
            return new WP_Error('qd_ai_seo_missing_api_key', __('OpenAI API key is missing.', 'queerdispatch-ai-seo'), ['status' => 400]);
        }

        $schema = [
            'name'   => 'qd_ai_seo_package',
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => [
                    'focus_keyphrase' => ['type' => 'string'],
                    'keyphrase_variants' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'headline_variants' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'seo_title' => ['type' => 'string'],
                    'meta_description' => ['type' => 'string'],
                    'social_title' => ['type' => 'string'],
                    'social_description' => ['type' => 'string'],
                    'excerpt_suggestion' => ['type' => 'string'],
                    'ai_disclosure' => ['type' => 'string'],
                    'analysis_notes' => ['type' => 'string'],
                    'internal_link_suggestions' => [
                        'type' => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => [
                                'post_id' => ['type' => 'integer'],
                                'title' => ['type' => 'string'],
                                'url' => ['type' => 'string'],
                                'anchor' => ['type' => 'string'],
                                'reason' => ['type' => 'string'],
                            ],
                            'required' => ['post_id', 'title', 'url', 'anchor', 'reason'],
                        ],
                    ],
                ],
                'required'             => [
                    'focus_keyphrase',
                    'keyphrase_variants',
                    'headline_variants',
                    'seo_title',
                    'meta_description',
                    'social_title',
                    'social_description',
                    'excerpt_suggestion',
                    'ai_disclosure',
                    'analysis_notes',
                    'internal_link_suggestions',
                ],
            ],
        ];

        $system_message = implode("\n", [
            'You are an editorial SEO assistant for QueerDispatch, a queer-focused news and activist publication.',
            (string) Settings::get_option('brand_voice', ''),
            'Return only valid JSON matching the provided schema.',
            'Keep SEO title under 65 characters when possible.',
            'Keep meta description under 160 characters when possible.',
            'Use only the provided internal link candidates. Do not invent URLs or facts.',
            'Prefer strong but credible phrasing suitable for a news and advocacy outlet.',
            'Do not fabricate legal claims, dates, or quotes.',
            'headline_variants should be 3 to 5 options, each distinct and plausible.',
        ]);

        $user_message = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $request_body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_message],
                ['role' => 'user', 'content' => $user_message],
            ],
            'temperature' => 0.3,
            'response_format' => [
                'type'        => 'json_schema',
                'json_schema' => $schema,
            ],
        ];

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'timeout' => 45,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'qd_ai_seo_api_error',
                __('The OpenAI API returned an error.', 'queerdispatch-ai-seo'),
                [
                    'status' => $code,
                    'body'   => is_array($body) ? $body : [],
                ]
            );
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        if (! is_string($content) || '' === trim($content)) {
            return new WP_Error('qd_ai_seo_empty_response', __('The AI response was empty.', 'queerdispatch-ai-seo'), ['status' => 502]);
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return new WP_Error('qd_ai_seo_invalid_response', __('The AI response was not valid JSON.', 'queerdispatch-ai-seo'), ['status' => 500]);
        }

        return [
            'focus_keyphrase' => sanitize_text_field((string) ($decoded['focus_keyphrase'] ?? '')),
            'keyphrase_variants' => Meta::sanitize_array($decoded['keyphrase_variants'] ?? [], 'string'),
            'headline_variants' => Meta::sanitize_array($decoded['headline_variants'] ?? [], 'string'),
            'seo_title' => sanitize_text_field((string) ($decoded['seo_title'] ?? '')),
            'meta_description' => sanitize_textarea_field((string) ($decoded['meta_description'] ?? '')),
            'social_title' => sanitize_text_field((string) ($decoded['social_title'] ?? '')),
            'social_description' => sanitize_textarea_field((string) ($decoded['social_description'] ?? '')),
            'excerpt_suggestion' => sanitize_textarea_field((string) ($decoded['excerpt_suggestion'] ?? '')),
            'ai_disclosure' => sanitize_textarea_field((string) ($decoded['ai_disclosure'] ?? '')),
            'analysis_notes' => sanitize_textarea_field((string) ($decoded['analysis_notes'] ?? '')),
            'internal_link_suggestions' => Meta::sanitize_array($decoded['internal_link_suggestions'] ?? [], 'object'),
        ];
    }
}
