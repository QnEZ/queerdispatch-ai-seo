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
            'Use only the provided internal link candidates. Do not invent URLs.',
            'Do not fabricate facts or legal claims.',
        ]);

        $user_message = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'timeout' => 45,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system_message],
                        ['role' => 'user', 'content' => $user_message],
                    ],
                    'temperature' => 0.4,
                    'response_format' => [
                        'type'        => 'json_schema',
                        'json_schema' => $schema,
                    ],
                ]),
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
                    'body'   => $body,
                ]
            );
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            return new WP_Error('qd_ai_seo_invalid_response', __('The AI response was not valid JSON.', 'queerdispatch-ai-seo'), ['status' => 500]);
        }

        return $decoded;
    }
}
