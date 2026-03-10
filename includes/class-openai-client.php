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
        $this->model = trim((string) Settings::get_option('model', 'gpt-4o-mini'));
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

    public static function get_visual_preset_prompts(): array
    {
        return [
            'clean_news' => 'Clean News: crisp newsroom look, strong hierarchy, restrained drama, readable overlays, publication-ready.',
            'urgent_alert' => 'Urgent Alert: high-contrast, high-urgency social graphic treatment for breaking policy or rights alerts without becoming misleading.',
            'editorial_heat' => 'Editorial Heat: sharper activist energy, bold framing, emotionally resonant composition, still legible and credible.',
            'rights_explainer' => 'Rights Explainer: educational visual framing, plain-language diagram or explainer-card sensibility, clarity over spectacle.',
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
                    'featured_image_brief' => ['type' => 'string'],
                    'social_card_copy_pack' => ['type' => 'string'],
                    'story_package' => ['type' => 'string'],
                    'overlay_text_suggestions' => ['type' => 'array', 'items' => ['type' => 'string']],
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
                    'visual_prompt_variants' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'format' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                                'prompt' => ['type' => 'string'],
                            ],
                            'required' => ['format', 'label', 'prompt'],
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
                    'focus_keyphrase','keyphrase_variants','headline_variants','seo_title','meta_description','social_title','social_description','excerpt_suggestion','ai_disclosure','analysis_notes','infographic_prompt','featured_image_alt_suggestion','featured_image_caption_suggestion','featured_image_brief','social_card_copy_pack','story_package','overlay_text_suggestions','social_posts','visual_prompt_variants','internal_link_suggestions',
                ],
            ],
        ];

        $article_mode = sanitize_key((string) ($payload['settings']['article_mode'] ?? 'news'));
        $mode_prompts = self::get_article_mode_prompts();
        $mode_prompt = $mode_prompts[$article_mode] ?? $mode_prompts['news'];

        $beat_preset = sanitize_key((string) ($payload['settings']['beat_preset'] ?? 'general'));
        $beat_prompts = self::get_beat_preset_prompts();
        $beat_prompt = $beat_prompts[$beat_preset] ?? $beat_prompts['general'];

        $visual_preset = sanitize_key((string) ($payload['settings']['visual_preset'] ?? 'clean_news'));
        $visual_prompts = self::get_visual_preset_prompts();
        $visual_prompt = $visual_prompts[$visual_preset] ?? $visual_prompts['clean_news'];

        $system_message = implode("
", [
            'You are an editorial SEO and visual packaging assistant for QueerDispatch, a queer-focused news and activist publication.',
            (string) Settings::get_option('brand_voice', ''),
            $mode_prompt,
            $beat_prompt,
            $visual_prompt,
            'Return only valid JSON matching the provided schema.',
            'Keep SEO title under 65 characters when possible.',
            'Keep meta description under 160 characters when possible.',
            'For visual outputs, design for publication-ready queer news graphics with strong readability and accessible overlay text.',
            'Provide visual prompt variants for square, vertical story, and landscape banner formats.',
            'Overlay text suggestions should be short, legible, and safe for image-based headlines.',
            'Use only the provided internal link candidates. Do not invent URLs or facts.',
            'Prefer strong but credible phrasing suitable for a news and advocacy outlet.',
            'Do not fabricate legal claims, dates, or quotes.',
        ]);

        $user_message = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $estimated_prompt_tokens = $this->estimate_tokens($system_message . "
" . $user_message);
        $token_cap = absint((string) Settings::get_option('per_request_token_cap', '12000'));
        if ($estimated_prompt_tokens > $token_cap) {
            return new WP_Error('qd_ai_seo_token_cap', __('This generation request is larger than the configured token cap.', 'queerdispatch-ai-seo'), ['status' => 400]);
        }

        Logger::log('request_started', [
            'model' => $this->model,
            'post_id' => absint($payload['post']['id'] ?? 0),
            'estimated_prompt_tokens' => $estimated_prompt_tokens,
            'article_mode' => $article_mode,
            'beat_preset' => $beat_preset,
            'visual_preset' => $visual_preset,
        ]);

        $response = $this->request_json([
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $system_message,
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $user_message,
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schema['name'],
                    'schema' => $schema['schema'],
                ],
                'verbosity' => 'medium',
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::log('request_failed', [
                'model' => $this->model,
                'post_id' => absint($payload['post']['id'] ?? 0),
                'error' => self::flatten_error($response),
            ]);
            return $response;
        }

        $decoded = json_decode((string) ($response['content'] ?? ''), true);
        if (! is_array($decoded)) {
            return new WP_Error('qd_ai_seo_invalid_response', __('The AI response was not valid JSON.', 'queerdispatch-ai-seo'), ['status' => 500]);
        }

        $estimated_completion_tokens = $this->estimate_tokens((string) ($response['content'] ?? ''));
        Logger::log('request_completed', [
            'model' => $this->model,
            'post_id' => absint($payload['post']['id'] ?? 0),
            'latency_ms' => absint($response['latency_ms'] ?? 0),
            'estimated_prompt_tokens' => $estimated_prompt_tokens,
            'estimated_completion_tokens' => $estimated_completion_tokens,
        ]);

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
            'featured_image_brief' => sanitize_textarea_field((string) ($decoded['featured_image_brief'] ?? '')),
            'social_card_copy_pack' => sanitize_textarea_field((string) ($decoded['social_card_copy_pack'] ?? '')),
            'story_package' => sanitize_textarea_field((string) ($decoded['story_package'] ?? '')),
            'overlay_text_suggestions' => Meta::sanitize_array($decoded['overlay_text_suggestions'] ?? [], 'string'),
            'social_posts' => Meta::sanitize_array($decoded['social_posts'] ?? [], 'social_object'),
            'visual_prompt_variants' => Meta::sanitize_array($decoded['visual_prompt_variants'] ?? [], 'visual_prompt_object'),
            'internal_link_suggestions' => Meta::sanitize_array($decoded['internal_link_suggestions'] ?? [], 'link_object'),
            '_stats' => [
                'latency_ms' => absint($response['latency_ms'] ?? 0),
                'estimated_prompt_tokens' => $estimated_prompt_tokens,
                'estimated_completion_tokens' => $estimated_completion_tokens,
            ],
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
            'input' => 'Return exactly the word OK. Ping',
            'text' => [
                'verbosity' => 'low',
            ],
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
            'https://api.openai.com/v1/responses',
            [
                'timeout' => absint((string) Settings::get_option('request_timeout', '45')),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($request_body),
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('request_transport_error', [
                'message' => $response->get_error_message(),
            ]);
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code < 200 || $code >= 300) {
            $api_message = '';
            if (is_array($body) && isset($body['error']['message']) && is_string($body['error']['message'])) {
                $api_message = $body['error']['message'];
            }

            Logger::log('request_api_error', [
                'status' => $code,
                'message' => $api_message,
                'body' => is_array($body) ? $body : ['raw' => $raw_body],
            ]);

            return new WP_Error(
                'qd_ai_seo_api_error',
                $api_message !== ''
                    ? sprintf(__('OpenAI API error (%d): %s', 'queerdispatch-ai-seo'), $code, $api_message)
                    : __('The OpenAI API returned an error.', 'queerdispatch-ai-seo'),
                ['status' => $code, 'body' => is_array($body) ? $body : ['raw' => $raw_body]]
            );
        }

        $content = $this->extract_output_text(is_array($body) ? $body : []);
        if ('' === trim($content)) {
            Logger::log('request_parse_error', [
                'status' => $code,
                'body' => is_array($body) ? $body : ['raw' => $raw_body],
            ]);
            return new WP_Error('qd_ai_seo_empty_response', __('The AI response was empty.', 'queerdispatch-ai-seo'), ['status' => 502]);
        }

        return [
            'content' => $content,
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            'response_id' => is_array($body) ? (string) ($body['id'] ?? '') : '',
        ];
    }

    private function extract_output_text(array $body): string
    {
        $content = '';

        if (! isset($body['output']) || ! is_array($body['output'])) {
            return $content;
        }

        foreach ($body['output'] as $item) {
            if (! is_array($item) || ! isset($item['content']) || ! is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $part) {
                if (! is_array($part)) {
                    continue;
                }

                if (isset($part['type'], $part['text']) && 'output_text' === $part['type'] && is_string($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }

        return $content;
    }

    private function estimate_tokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    private static function flatten_error(WP_Error $error): string
    {
        return $error->get_error_code() . ': ' . $error->get_error_message();
    }
}
