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
 * Shared HTTP utility trait used by all AI provider classes.
 *
 * Wraps Moodle's curl class (which respects proxy settings, SSL certificates,
 * and the $CFG->curlsecurityblockedhosts allow/deny list) so that all outbound
 * API calls are subject to the site's network security policies.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP helper methods shared by all provider implementations.
 */
trait http_client_trait {

    /**
     * Performs an HTTP POST request using Moodle's curl wrapper.
     *
     * @param  string $url     Full endpoint URL.
     * @param  array  $headers HTTP headers as ['Header-Name: value', ...].
     * @param  string $body    JSON-encoded request body.
     * @param  int    $timeout Request timeout in seconds (default 60).
     * @return string          Raw HTTP response body.
     * @throws \moodle_exception On curl error or non-2xx HTTP status.
     */
    protected function http_post(string $url, array $headers, string $body, int $timeout = 60): string {
        $curl = new \curl(['ignoresecurity' => false]);

        $curloptions = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT'        => $timeout,
            'CURLOPT_POST'           => true,
            'CURLOPT_POSTFIELDS'     => $body,
            'CURLOPT_HTTPHEADER'     => $headers,
        ];

        $response = $curl->post($url, $body, $curloptions);
        $info     = $curl->get_info();
        $errno    = $curl->get_errno();

        // Check for curl-level errors.
        if ($errno !== 0) {
            throw new \moodle_exception(
                'connection_failed',
                'qtype_codejudge',
                '',
                $curl->error
            );
        }

        // Check for HTTP-level errors (non-2xx).
        $httpcode = $info['http_code'] ?? 0;
        if ($httpcode < 200 || $httpcode >= 300) {
            // Try to extract a useful error message from the response body.
            $errorbody = $this->extract_api_error($response);
            throw new \moodle_exception(
                'connection_failed',
                'qtype_codejudge',
                '',
                "HTTP {$httpcode}: {$errorbody}"
            );
        }

        return $response;
    }

    /**
     * Decodes a JSON response string and validates its structure.
     *
     * @param  string $raw Raw JSON string from the API.
     * @return array       Decoded associative array.
     * @throws \moodle_exception If JSON decoding fails.
     */
    protected function decode_json(string $raw): array {
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception(
                'error_ai_response_invalid',
                'qtype_codejudge',
                '',
                null,
                'JSON decode error: ' . json_last_error_msg() . ' | Raw: ' . substr($raw, 0, 500)
            );
        }
        return $data;
    }

    /**
     * Parses the AI-generated text to extract a score and feedback JSON object.
     *
     * The AI is instructed to return ONLY a JSON string like:
     *   {"score": 85.5, "feedback": "Good logic but..."}
     *
     * This method is tolerant: it tries strict JSON first, then falls back to
     * a regex extraction in case the model wrapped the JSON in markdown fences.
     *
     * @param  string $text  The raw text content returned by the AI model.
     * @param  string $rawresponse The full raw API response (for logging).
     * @return array{score: float, feedback: string, raw: string}
     * @throws \moodle_exception If no valid JSON payload can be extracted.
     */
    protected function parse_evaluation_text(string $text, string $rawresponse): array {
        // Strip markdown code fences if present (```json ... ```).
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        // Attempt direct JSON decode.
        $parsed = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            // Fallback: extract the first {...} block from the text.
            if (preg_match('/\{[^{}]*"score"[^{}]*\}/s', $cleaned, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
        }

        if (
            !is_array($parsed)
            || !isset($parsed['score'])
            || !isset($parsed['feedback'])
        ) {
            throw new \moodle_exception(
                'error_ai_response_invalid',
                'qtype_codejudge',
                '',
                null,
                'Could not extract score/feedback. Raw text: ' . substr($text, 0, 500)
            );
        }

        return [
            'score'    => (float) $parsed['score'],
            'feedback' => (string) $parsed['feedback'],
            'raw'      => $rawresponse,
        ];
    }

    /**
     * Attempts to extract a human-readable error message from an API error body.
     *
     * @param  string $body Raw response body (may be JSON or plain text).
     * @return string       Extracted error message or truncated raw body.
     */
    private function extract_api_error(string $body): string {
        $data = json_decode($body, true);
        if (is_array($data)) {
            // OpenAI / Anthropic style: {"error": {"message": "..."}}
            return $data['error']['message']
                ?? $data['error']
                ?? $data['message']
                ?? substr($body, 0, 300);
        }
        return substr($body, 0, 300);
    }
}
