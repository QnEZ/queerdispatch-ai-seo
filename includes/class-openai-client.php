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

    public function __construct()
    {
        $this->api_key = trim((string) Settings::get_option('api_key', ''));
        $this->model = trim((string) Settings::get_option('model', 'gpt-5-mini'));
    }

    public function is_configured(): bool
    {
        return '' !== trim($this->api_key);
    }

    public static function get_article_mode_prompts(): array
    {
        return [
            'news' => 'Treat this as a reported news article. Prioritize clarity, recency, factual restraint, and search intent.',
            'editorial' => 'Treat this as an editorial or opinion piece. Keep the framing sharp and persuasive, but still credible and non-defamatory.',
            'explainer' => 'Treat this as an explainer. Emphasize plain language, search-friendly question answering, and contextual clarity.',
            'social_copy' => 'Treat this as social-first copy. Prioritize shareability, punchy lines, and strong social metadata without becoming vague clickbait.',
        ];
    }

    public static function get_beat_preset_prompts(): array
    {
        return [
            'general' => 'General QueerDispatch framing: center queer communities, maintain credibility, and keep calls to attention grounded in the article facts.',
            'policy_watch' => 'Policy Watch framing: prioritize legislation, executive actions, court developments, and the concrete material impact on queer and trans people.',
            'state_alert' => 'State Alert framing: emphasize why a specific state-level development matters now, who is affected, and what readers should watch next.',
            'media_watch' => 'Media Watch framing: focus on rhetoric, media narratives, public backlash, amplification patterns, and why framing choices matter.',
            'community_voice' => 'Community Voice framing: foreground the lived reality, stakes, and dignity of impacted people while staying specific and concrete.',
            'rights_explainer' => 'Rights Explainer framing: simplify legal and policy complexity into plain-language takeaways without flattening nuance.',
        ];
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
                    'infographic_prompt' => ['type' => 'string'],
                    'featured_image_alt_suggestion' => ['type' => 'string'],
                    'featured_image_caption_suggestion' => ['type' => 'string'],
                    'story_package' => ['type' => 'string'],
                    'social_posts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'network' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                                'body' => ['type' => 'string'],
                            ],
                            'required' => ['network', 'label', 'body'],
                        ],
                    ],
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
                    'infographic_prompt',
                    'featured_image_alt_suggestion',
                    'featured_image_caption_suggestion',
                    'story_package',
                    'social_posts',
                    'internal_link_suggestions',
                ],
            ],
        ];

        $article_mode = sanitize_key((string) ($payload['settings']['article_mode'] ?? 'news'));
        $mode_prompts = self::get_article_mode_prompts();
        $mode_prompt = $mode_prompts[$article_mode] ?? $mode_prompts['news'];

        $beat_preset = sanitize_key((string) ($payload['settings']['beat_preset'] ?? 'general'));
        $beat_prompts = self::get_beat_preset_prompts();
        $beat_prompt = $beat_prompts[$beat_preset] ?? $beat_prompts['general'];

        $system_message = implode("\n", [
            'You are an editorial SEO assistant for QueerDispatch, a queer-focused news and activist publication.',
            (string) Settings::get_option('brand_voice', ''),
            $mode_prompt,
            $beat_prompt,
            'Return only valid JSON matching the provided schema.',
            'Keep SEO title under 65 characters when possible.',
            'Keep meta description under 160 characters when possible.',
            'Use only the provided internal link candidates. Do not invent URLs or facts.',
            'Prefer strong but credible phrasing suitable for a news and advocacy outlet.',
            'Do not fabricate legal claims, dates, or quotes.',
            'headline_variants should be 3 to 5 options, each distinct and plausible.',
            'social_posts should include one item each for Facebook, Bluesky, and X.',
            'featured_image_alt_suggestion should be descriptive, specific, and suitable for accessibility, based on the likely article art or social graphic implied by the story.',
            'featured_image_caption_suggestion should be short, newsroom-friendly, and suitable for a featured image or share graphic caption.',
            'story_package should be a clean copy-and-paste package with short section headers for SEO title, meta description, focus keyphrase, social copy, image prompt, alt text, and caption.',
            'infographic_prompt should be a concise but vivid prompt for a branded QueerDispatch share graphic or featured image.',
        ]);

        $user_message = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = $this->request_json([
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
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $decoded = json_decode((string) ($response['content'] ?? ''), true);
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
            'infographic_prompt' => sanitize_textarea_field((string) ($decoded['infographic_prompt'] ?? '')),
            'featured_image_alt_suggestion' => sanitize_textarea_field((string) ($decoded['featured_image_alt_suggestion'] ?? '')),
            'featured_image_caption_suggestion' => sanitize_textarea_field((string) ($decoded['featured_image_caption_suggestion'] ?? '')),
            'story_package' => sanitize_textarea_field((string) ($decoded['story_package'] ?? '')),
            'social_posts' => Meta::sanitize_array($decoded['social_posts'] ?? [], 'social_object'),
            'internal_link_suggestions' => Meta::sanitize_array($decoded['internal_link_suggestions'] ?? [], 'link_object'),
        ];
    }

    public static function run_diagnostic(): array|WP_Error
    {
        $client = new self();
        if (! $client->is_configured()) {
            return new WP_Error('qd_ai_seo_missing_api_key', __('OpenAI API key is missing.', 'queerdispatch-ai-seo'));
        }

        $response = $client->request_json([
            'model' => $client->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Return exactly the word OK.'],
                ['role' => 'user', 'content' => 'Ping'],
            ],
            'temperature' => 0,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return [
            'model' => $client->model,
            'latency_ms' => $response['latency_ms'] ?? 0,
            'content' => (string) ($response['content'] ?? ''),
        ];
    }

    private function request_json(array $request_body): array|WP_Error
    {
        $start = microtime(true);
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

        return [
            'content' => $content,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }
}
