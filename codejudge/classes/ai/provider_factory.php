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
 * Factory that instantiates the correct AI provider based on site configuration.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads the plugin's admin settings and returns the configured provider instance.
 *
 * Usage:
 *   $provider = \qtype_codejudge\ai\provider_factory::get_provider();
 *   $result   = $provider->evaluate($prompt);
 */
class provider_factory {

    /**
     * Creates a provider instance from an explicit provider key and settings.
     *
     * @param string $providerkey
     * @param string $apikey
     * @param string $baseurl
     * @param string $modelname
     * @return provider_interface
     */
    public static function create_provider(
        string $providerkey,
        string $apikey = '',
        string $baseurl = '',
        string $modelname = ''
    ): provider_interface {
        switch ($providerkey) {
            case 'openai':
                return new openai_provider($apikey, $baseurl, $modelname ?: 'gpt-4o');

            case 'core_ai':
                return new core_ai_provider($modelname);

            case 'anthropic':
                return new anthropic_provider($apikey, $baseurl, $modelname ?: 'claude-3-5-sonnet-20241022');

            case 'gemini':
                return new gemini_provider($apikey, $baseurl, $modelname ?: 'gemini-1.5-pro');

            case 'ollama':
                $ollamaurl = $baseurl ?: 'http://localhost:11434';
                return new ollama_provider($ollamaurl, $modelname ?: 'llama3');

            default:
                throw new \moodle_exception(
                    'error_provider_not_configured',
                    'qtype_codejudge',
                    '',
                    null,
                    "Unknown provider key: '{$providerkey}'"
                );
        }
    }

    /**
     * Returns an instantiated, ready-to-use AI provider.
     *
     * @return provider_interface A concrete provider implementation.
     * @throws \moodle_exception  If the configured provider is unknown or
     *                             required settings are missing.
     */
    public static function get_provider(): provider_interface {
        $config = get_config('qtype_codejudge');

        $providerkey = $config->provider ?? 'openai';
        $apikey      = $config->api_key   ?? '';
        $baseurl     = $config->base_url  ?? '';
        $modelname   = $config->model_name ?? '';

        return self::create_provider($providerkey, $apikey, $baseurl, $modelname);
    }
}
