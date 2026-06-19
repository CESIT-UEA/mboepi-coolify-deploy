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
 * External function: test_connection
 *
 * Tests the connection to an AI provider with the supplied configuration parameters.
 * Called via AJAX by the AMD settings_test.js module on the admin settings page.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use qtype_codejudge\ai\provider_factory;

/**
 * External function that validates credentials and endpoint configuration.
 */
class test_connection extends external_api {

    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'provider'   => new external_value(PARAM_ALPHANUMEXT, 'The AI provider key (openai|core_ai|anthropic|gemini|ollama).', VALUE_REQUIRED),
            'api_key'    => new external_value(PARAM_RAW, 'The API key (optional).', VALUE_DEFAULT, ''),
            'base_url'   => new external_value(PARAM_RAW, 'The base/endpoint URL (optional).', VALUE_DEFAULT, ''),
            'model_name' => new external_value(PARAM_TEXT, 'The model name (optional).', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Instantiates the provider with the passed inputs and checks connectivity.
     *
     * @param  string $provider
     * @param  string $api_key
     * @param  string $base_url
     * @param  string $model_name
     * @return array             Associative array matching execute_returns().
     * @throws \moodle_exception If user doesn't have site admin capabilities.
     */
    public static function execute(string $provider, string $api_key, string $base_url, string $model_name): array {
        // Context check: only administrators should be able to trigger connection tests.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'provider'   => $provider,
            'api_key'    => $api_key,
            'base_url'   => $base_url,
            'model_name' => $model_name,
        ]);

        $providerkey = $params['provider'];
        $apikey      = $params['api_key'];
        $baseurl     = $params['base_url'];
        $modelname   = $params['model_name'];

        try {
            $instance = provider_factory::create_provider($providerkey, $apikey, $baseurl, $modelname);
            return $instance->test_connection();

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Describes the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if connection succeeded, false otherwise.'),
            'message' => new external_value(PARAM_RAW,  'Detailed response or error message.'),
        ]);
    }
}
