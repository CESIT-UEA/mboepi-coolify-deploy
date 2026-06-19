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
 * Moodle core AI API provider adapter for qtype_codejudge.
 *
 * This adapter is intentionally defensive: Moodle's AI subsystem surface may
 * vary slightly between releases. We probe for the core AI classes/methods at
 * runtime and normalise the result into the plugin's provider contract.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Adapter that delegates code evaluation to Moodle's core AI subsystem.
 */
class core_ai_provider implements provider_interface {

    use http_client_trait;

    /** @var string Model identifier configured for the core AI subsystem. */
    private string $model;

    /**
     * @param string $model Optional model identifier or alias managed by Moodle core.
     */
    public function __construct(string $model = '') {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(string $prompt): array {
        $result = $this->invoke_core_ai($prompt);
        $text = $this->normalise_result_to_text($result);

        if ($text === '') {
            throw new \moodle_exception(
                'error_ai_response_invalid',
                'qtype_codejudge',
                '',
                null,
                'The Moodle core AI API returned an empty response.'
            );
        }

        $rawresponse = is_string($result) ? $result : json_encode($this->serialise_result($result));
        if (!is_string($rawresponse)) {
            $rawresponse = '';
        }

        return $this->parse_evaluation_text($text, $rawresponse);
    }

    /**
     * {@inheritdoc}
     */
    public function test_connection(): array {
        if (!$this->is_core_ai_available()) {
            return [
                'success' => false,
                'message' => get_string('connection_failed', 'qtype_codejudge', 'Moodle core AI API is not available on this site.'),
            ];
        }

        try {
            $this->invoke_core_ai('Health check.');
            return ['success' => true, 'message' => get_string('connection_success', 'qtype_codejudge')];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => get_string('connection_failed', 'qtype_codejudge', $e->getMessage()),
            ];
        }
    }

    /**
     * Attempts to call a Moodle core AI entry point.
     *
     * The adapter tries a small set of likely public APIs so the plugin can run
     * across Moodle 5.x minor releases without hard-coding one exact signature.
     *
     * @param string $prompt
     * @return mixed
     */
    private function invoke_core_ai(string $prompt) {
        $candidates = [
            '\core_ai\manager',
            '\core_ai\api',
            '\core_ai\local\api',
        ];

        $methods = [
            'generate_text',
            'chat_completion',
            'chat',
            'generate',
            'evaluate',
            'request',
        ];

        foreach ($candidates as $classname) {
            if (!class_exists($classname)) {
                continue;
            }

            foreach ($methods as $method) {
                if (!method_exists($classname, $method)) {
                    continue;
                }

                try {
                    $reflection = new \ReflectionMethod($classname, $method);
                    if (!$reflection->isPublic()) {
                        continue;
                    }

                    $argsets = [[$prompt]];
                    if ($this->model !== '') {
                        $argsets[] = [$prompt, ['model' => $this->model]];
                    }

                    $instance = null;
                    if (!$reflection->isStatic()) {
                        $instance = $this->instantiate_core_ai_class($classname);
                        if ($instance === null) {
                            continue;
                        }
                    }

                    foreach ($argsets as $args) {
                        try {
                            if ($reflection->isStatic()) {
                                return $reflection->invokeArgs(null, $args);
                            }

                            return $reflection->invokeArgs($instance, $args);
                        } catch (\Throwable $inner) {
                            continue;
                        }
                    }
                } catch (\Throwable $e) {
                    // Try the next candidate/method pair.
                    continue;
                }
            }
        }

        throw new \moodle_exception(
            'error_provider_not_configured',
            'qtype_codejudge',
            '',
            null,
            'Unable to locate a compatible Moodle core AI API entry point.'
        );
    }

    /**
     * Attempts to instantiate a core AI class with no constructor arguments.
     *
     * @param string $classname
     * @return object|null
     */
    private function instantiate_core_ai_class(string $classname): ?object {
        try {
            $reflection = new \ReflectionClass($classname);
            $constructor = $reflection->getConstructor();
            if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                return $reflection->newInstance();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Checks whether any known core AI entry point exists.
     *
     * @return bool
     */
    private function is_core_ai_available(): bool {
        return class_exists('\core_ai\manager')
            || class_exists('\core_ai\api')
            || class_exists('\core_ai\local\api');
    }

    /**
     * Converts a core AI response to plain text.
     *
     * @param mixed $result
     * @return string
     */
    private function normalise_result_to_text($result): string {
        if (is_string($result)) {
            return trim($result);
        }

        if (is_object($result)) {
            if (isset($result->score) && isset($result->feedback)) {
                return json_encode([
                    'score' => $result->score,
                    'feedback' => $result->feedback,
                ]) ?: '';
            }

            foreach (['text', 'content', 'response', 'output'] as $property) {
                if (isset($result->$property) && is_string($result->$property)) {
                    return trim($result->$property);
                }
            }

            foreach (['get_text', 'get_content', 'get_response', '__toString'] as $method) {
                if (method_exists($result, $method)) {
                    try {
                        $value = $result->$method();
                        if (is_string($value) && trim($value) !== '') {
                            return trim($value);
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }
        }

        if (is_array($result)) {
            if (isset($result['score']) && isset($result['feedback'])) {
                return json_encode([
                    'score' => $result['score'],
                    'feedback' => $result['feedback'],
                ]) ?: '';
            }

            $keys = ['text', 'content', 'response', 'output', 'message'];
            foreach ($keys as $key) {
                if (isset($result[$key]) && is_string($result[$key])) {
                    return trim($result[$key]);
                }
            }

            if (isset($result['choices'][0]['message']['content']) && is_string($result['choices'][0]['message']['content'])) {
                return trim($result['choices'][0]['message']['content']);
            }

            if (isset($result['candidates'][0]['content']['parts'][0]['text']) && is_string($result['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($result['candidates'][0]['content']['parts'][0]['text']);
            }
        }

        return '';
    }

    /**
     * Converts arbitrary results to a serialisable structure for logging/debugging.
     *
     * @param mixed $result
     * @return array|string|null
     */
    private function serialise_result($result) {
        if (is_array($result) || is_string($result) || $result === null) {
            return $result;
        }

        if (is_object($result)) {
            return get_object_vars($result);
        }

        return null;
    }
}
