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
 * Ollama local LLM provider implementation for qtype_codejudge.
 *
 * Communicates with a locally running Ollama server via its REST API.
 * No API key is required — authentication is handled by network access control.
 *
 * Compatible with any model pulled into Ollama (llama3, mistral, codellama,
 * deepseek-coder, phi3, etc.).
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Calls a local Ollama instance to evaluate student code.
 */
class ollama_provider implements provider_interface {

    use http_client_trait;

    /** @var string Default Ollama API base URL. */
    const DEFAULT_BASE_URL = 'http://localhost:11434';

    /** @var string Timeout in seconds — increased for local LLMs which can be slow. */
    const TIMEOUT = 180;

    /** @var string Base URL of the Ollama server. */
    private string $baseurl;

    /** @var string Model name (e.g. llama3, mistral, codellama). */
    private string $model;

    /**
     * @param string $baseurl Base URL of the Ollama server (e.g. http://localhost:11434).
     * @param string $model   Model name to use (must be pulled in Ollama first).
     */
    public function __construct(
        string $baseurl = self::DEFAULT_BASE_URL,
        string $model = 'llama3'
    ) {
        $this->baseurl = rtrim($baseurl, '/');
        $this->model   = $model;
    }

    /**
     * {@inheritdoc}
     *
     * Uses the Ollama /api/chat endpoint with the messages array format,
     * which mirrors OpenAI's interface and supports system prompts.
     */
    public function evaluate(string $prompt): array {
        $endpoint = $this->baseurl . '/api/chat';

        $payload = json_encode([
            'model'  => $this->model,
            'stream' => false,      // Receive the complete response in one JSON object.
            'options' => [
                'temperature' => 0.2,
                'num_predict' => 1024,
            ],
            'messages' => [
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

        $headers = ['Content-Type: application/json'];

        // Local models can be slow; use a generous timeout.
        $raw  = $this->http_post($endpoint, $headers, $payload, self::TIMEOUT);
        $data = $this->decode_json($raw);

        // Ollama /api/chat response: message.content
        $text = $data['message']['content'] ?? '';

        if (empty($text)) {
            throw new \moodle_exception('error_ai_response_invalid', 'qtype_codejudge');
        }

        return $this->parse_evaluation_text($text, $raw);
    }

    /**
     * {@inheritdoc}
     *
     * Hits the Ollama /api/tags endpoint, which returns the list of
     * locally available models. Verifies that the configured model is pulled.
     */
    public function test_connection(): array {
        $url  = $this->baseurl . '/api/tags';
        $curl = new \curl(['ignoresecurity' => false]);

        $curloptions = [
            'CURLOPT_TIMEOUT'        => 10,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $response = $curl->get($url, [], $curloptions);
        $errno    = $curl->get_errno();
        $info     = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($errno !== 0 || $httpcode !== 200) {
            $errmsg = $errno !== 0 ? $curl->error : "HTTP {$httpcode}";
            return [
                'success' => false,
                'message' => get_string('connection_failed', 'qtype_codejudge', $errmsg),
            ];
        }

        $data   = json_decode($response, true);
        $models = array_column($data['models'] ?? [], 'name');

        // Check whether the configured model (or its base name) is available.
        $modelbase = explode(':', $this->model)[0]; // Strip tag (e.g. 'llama3:latest' → 'llama3').
        $found = array_filter(
            $models,
            fn($m) => str_starts_with($m, $modelbase)
        );

        if (empty($found)) {
            return [
                'success' => false,
                'message' => get_string(
                    'connection_failed',
                    'qtype_codejudge',
                    "Model '{$this->model}' not found in Ollama. Available: " . implode(', ', $models)
                ),
            ];
        }

        return ['success' => true, 'message' => get_string('connection_success', 'qtype_codejudge')];
    }
}
