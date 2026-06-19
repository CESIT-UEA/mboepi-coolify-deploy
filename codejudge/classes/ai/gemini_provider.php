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
 * Google Gemini provider implementation for qtype_codejudge.
 *
 * Uses the Google Generative Language API v1beta
 * (https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent).
 *
 * Tested with gemini-1.5-pro, gemini-1.5-flash, gemini-2.0-flash-exp.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Calls the Google Gemini generateContent API to evaluate student code.
 */
class gemini_provider implements provider_interface {

    use http_client_trait;

    /** @var string Google Generative Language API base URL. */
    const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com';

    /** @var string API version prefix. */
    const API_VERSION = 'v1beta';

    /** @var string API key. */
    private string $apikey;

    /** @var string Base URL (allows Vertex AI / enterprise proxies). */
    private string $baseurl;

    /** @var string Gemini model name. */
    private string $model;

    /**
     * @param string $apikey  Google AI Studio API key.
     * @param string $baseurl Optional base URL override (Vertex AI, proxies).
     * @param string $model   Model name (e.g. gemini-1.5-pro, gemini-1.5-flash).
     */
    public function __construct(
        string $apikey,
        string $baseurl = '',
        string $model = 'gemini-1.5-pro'
    ) {
        $this->apikey  = $apikey;
        $this->baseurl = rtrim($baseurl ?: self::DEFAULT_BASE_URL, '/');
        $this->model   = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(string $prompt): array {
        $endpoint = $this->build_endpoint('generateContent');

        $systeminstruction = 'You are an expert pedagogical code evaluator. '
                           . 'You MUST respond with ONLY a valid JSON object — no markdown, no explanation. '
                           . 'Format: {"score": <number 0-100>, "feedback": "<pedagogical text>"}';

        $payload = json_encode([
            'system_instruction' => [
                'parts' => [['text' => $systeminstruction]],
            ],
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 1024,
                // Request JSON output when supported.
                'responseMimeType' => 'application/json',
            ],
        ]);

        $headers = ['Content-Type: application/json'];

        $raw  = $this->http_post($endpoint, $headers, $payload);
        $data = $this->decode_json($raw);

        // Gemini response structure: candidates[0].content.parts[0].text
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            // Check for blocked prompt.
            $reason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            throw new \moodle_exception(
                'error_ai_response_invalid',
                'qtype_codejudge',
                '',
                null,
                "Gemini returned no text. Finish reason: {$reason}"
            );
        }

        return $this->parse_evaluation_text($text, $raw);
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection(): array {
        // Use the models list endpoint to verify the key.
        $url = $this->baseurl . '/' . self::API_VERSION . '/models?key=' . urlencode($this->apikey);

        $curl = new \curl(['ignoresecurity' => false]);
        $curloptions = [
            'CURLOPT_TIMEOUT'        => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $response = $curl->get($url, [], $curloptions);
        $info     = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode === 200) {
            return ['success' => true, 'message' => get_string('connection_success', 'qtype_codejudge')];
        }

        $data  = json_decode($response, true);
        $error = $data['error']['message'] ?? substr($response, 0, 200);

        return [
            'success' => false,
            'message' => get_string('connection_failed', 'qtype_codejudge', "HTTP {$httpcode}: {$error}"),
        ];
    }

    /**
     * Builds the full Gemini API endpoint URL including the API key query parameter.
     *
     * @param  string $action API action (e.g. 'generateContent').
     * @return string Full endpoint URL.
     */
    private function build_endpoint(string $action): string {
        return sprintf(
            '%s/%s/models/%s:%s?key=%s',
            $this->baseurl,
            self::API_VERSION,
            urlencode($this->model),
            $action,
            urlencode($this->apikey)
        );
    }
}
