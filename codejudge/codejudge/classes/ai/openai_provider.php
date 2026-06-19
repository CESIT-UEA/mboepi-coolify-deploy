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
 * OpenAI provider implementation for qtype_codejudge.
 *
 * Supports GPT-4o, GPT-4-turbo, GPT-3.5-turbo and any model available via
 * the OpenAI Chat Completions API — including custom endpoints / proxies that
 * expose the same API contract (e.g. Azure OpenAI, LiteLLM, OpenRouter).
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Calls the OpenAI Chat Completions API to evaluate student code.
 */
class openai_provider implements provider_interface {

    use http_client_trait;

    /** @var string Default base URL for the OpenAI API. */
    const DEFAULT_BASE_URL = 'https://api.openai.com';

    /** @var string API key for authentication. */
    private string $apikey;

    /** @var string Base URL (allows proxies / Azure OpenAI). */
    private string $baseurl;

    /** @var string Model name (e.g. gpt-4o, gpt-4-turbo). */
    private string $model;

    /**
     * @param string $apikey   OpenAI API key.
     * @param string $baseurl  Optional base URL override (proxy / Azure).
     * @param string $model    Model to use.
     */
    public function __construct(string $apikey, string $baseurl = '', string $model = 'gpt-4o') {
        $this->apikey  = $apikey;
        $this->baseurl = rtrim($baseurl ?: self::DEFAULT_BASE_URL, '/');
        $this->model   = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(string $prompt): array {
        $endpoint = $this->baseurl . '/v1/chat/completions';

        $payload = json_encode([
            'model'       => $this->model,
            'temperature' => 0.2,   // Low temperature for consistent, deterministic grading.
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are an expert pedagogical code evaluator. '
                               . 'You MUST respond with ONLY a valid JSON object — no markdown, no explanation. '
                               . 'Format: {"score": <number 0-100>, "feedback": "<pedagogical text>"}',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ];

        $raw  = $this->http_post($endpoint, $headers, $payload);
        $data = $this->decode_json($raw);

        // Extract the text content from the Chat Completions response.
        $text = $data['choices'][0]['message']['content'] ?? '';

        if (empty($text)) {
            throw new \moodle_exception('error_ai_response_invalid', 'qtype_codejudge');
        }

        return $this->parse_evaluation_text($text, $raw);
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection(): array {
        // Use the models list endpoint — it's lightweight and always available.
        $url  = $this->baseurl . '/v1/models';
        $curl = new \curl(['ignoresecurity' => false]);

        $curloptions = [
            'CURLOPT_HTTPHEADER'     => ['Authorization: Bearer ' . $this->apikey],
            'CURLOPT_TIMEOUT'        => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $response = $curl->get($url, [], $curloptions);
        $info     = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode === 200) {
            return ['success' => true, 'message' => get_string('connection_success', 'qtype_codejudge')];
        }

        $error = $this->extract_api_error_public($response);
        return [
            'success' => false,
            'message' => get_string('connection_failed', 'qtype_codejudge', "HTTP {$httpcode}: {$error}"),
        ];
    }

    /**
     * Public wrapper for extract_api_error (needed by test_connection above).
     * The trait method is private; this exposes it for use within this class.
     */
    private function extract_api_error_public(string $body): string {
        $data = json_decode($body, true);
        if (is_array($data)) {
            return $data['error']['message'] ?? $data['error'] ?? substr($body, 0, 200);
        }
        return substr($body, 0, 200);
    }
}
