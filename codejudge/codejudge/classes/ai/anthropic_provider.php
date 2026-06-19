<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Anthropic Claude provider implementation for qtype_codejudge.
 *
 * Uses the Anthropic Messages API (v1/messages).
 * Tested with claude-3-5-sonnet-20241022, claude-3-opus-20240229.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Calls the Anthropic Claude Messages API to evaluate student code.
 */
class anthropic_provider implements provider_interface {

    use http_client_trait;

    /** @var string Anthropic API base URL. */
    const DEFAULT_BASE_URL = 'https://api.anthropic.com';

    /** @var string Anthropic API version header value. */
    const API_VERSION = '2023-06-01';

    /** @var string Maximum output tokens for the evaluation response. */
    const MAX_TOKENS = 1024;

    /** @var string API key. */
    private string $apikey;

    /** @var string Base URL (allows proxies). */
    private string $baseurl;

    /** @var string Model ID. */
    private string $model;

    /**
     * @param string $apikey  Anthropic API key (starts with 'sk-ant-...').
     * @param string $baseurl Optional base URL override for proxies.
     * @param string $model   Model ID to use.
     */
    public function __construct(
        string $apikey,
        string $baseurl = '',
        string $model = 'claude-3-5-sonnet-20241022'
    ) {
        $this->apikey  = $apikey;
        $this->baseurl = rtrim($baseurl ?: self::DEFAULT_BASE_URL, '/');
        $this->model   = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(string $prompt): array {
        $endpoint = $this->baseurl . '/v1/messages';

        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => 'You are an expert pedagogical code evaluator. '
                          . 'You MUST respond with ONLY a valid JSON object — no markdown, no explanation. '
                          . 'Format: {"score": <number 0-100>, "feedback": "<pedagogical text>"}',
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apikey,
            'anthropic-version: ' . self::API_VERSION,
        ];

        $raw  = $this->http_post($endpoint, $headers, $payload);
        $data = $this->decode_json($raw);

        // Anthropic Messages API: content[0].text holds the response.
        $text = $data['content'][0]['text'] ?? '';

        if (empty($text)) {
            throw new \moodle_exception('error_ai_response_invalid', 'qtype_codejudge');
        }

        return $this->parse_evaluation_text($text, $raw);
    }

    /**
     * {@inheritdoc}
     *
     * Anthropic does not expose a free "list models" endpoint.
     * We send a minimal message to verify authentication.
     */
    public function test_connection(): array {
        $endpoint = $this->baseurl . '/v1/messages';

        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => 5,
            'messages'   => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apikey,
            'anthropic-version: ' . self::API_VERSION,
        ];

        try {
            $this->http_post($endpoint, $headers, $payload, 15);
            return ['success' => true, 'message' => get_string('connection_success', 'qtype_codejudge')];
        } catch (\moodle_exception $e) {
            return [
                'success' => false,
                'message' => get_string('connection_failed', 'qtype_codejudge', $e->a ?? $e->getMessage()),
            ];
        }
    }
}
