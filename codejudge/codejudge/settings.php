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
 * Global administrator settings for qtype_codejudge.
 *
 * Accessible at: Site Administration → Plugins → Question Types → Coding Question
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    global $OUTPUT;

    // -----------------------------------------------------------------------
    // Section heading
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'qtype_codejudge/settings_heading',
        get_string('settings_heading', 'qtype_codejudge'),
        get_string('settings_heading_desc', 'qtype_codejudge')
    ));

    // -----------------------------------------------------------------------
    // AI Provider selector
    // -----------------------------------------------------------------------
    $providerchoices = [
        'core_ai'   => get_string('provider_core_ai',   'qtype_codejudge'),
        'openai'    => get_string('provider_openai',    'qtype_codejudge'),
        'anthropic' => get_string('provider_anthropic', 'qtype_codejudge'),
        'gemini'    => get_string('provider_gemini',    'qtype_codejudge'),
        'ollama'    => get_string('provider_ollama',    'qtype_codejudge'),
    ];

    $settings->add(new admin_setting_configselect(
        'qtype_codejudge/provider',
        get_string('settings_provider', 'qtype_codejudge'),
        get_string('settings_provider_desc', 'qtype_codejudge'),
        'openai',       // Default provider.
        $providerchoices
    ));

    // -----------------------------------------------------------------------
    // API Key (password field — masked in the UI)
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_configpasswordunmask(
        'qtype_codejudge/api_key',
        get_string('settings_api_key', 'qtype_codejudge'),
        get_string('settings_api_key_desc', 'qtype_codejudge'),
        ''              // Default: empty.
    ));

    // -----------------------------------------------------------------------
    // Base URL / Endpoint (required for Ollama and enterprise proxies)
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'qtype_codejudge/base_url',
        get_string('settings_base_url', 'qtype_codejudge'),
        get_string('settings_base_url_desc', 'qtype_codejudge'),
        '',             // Default: empty (each provider uses its own default endpoint).
        PARAM_URL
    ));

    // -----------------------------------------------------------------------
    // Model name
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_configtext(
        'qtype_codejudge/model_name',
        get_string('settings_model_name', 'qtype_codejudge'),
        get_string('settings_model_name_desc', 'qtype_codejudge'),
        'gpt-4o',       // Sensible default.
        PARAM_TEXT
    ));

    // -----------------------------------------------------------------------
    // "Test Connection" widget.
    // -----------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'qtype_codejudge/test_connection_heading',
        get_string('test_connection', 'qtype_codejudge'),
        get_string('test_connection_desc', 'qtype_codejudge')
            . html_writer::tag(
                'div',
                $OUTPUT->render_from_template('qtype_codejudge/admin_settings_test', [
                    'testconnectionlabel' => get_string('test_connection', 'qtype_codejudge'),
                    'testconnectionloadinglabel' => get_string('test_connection_testing', 'qtype_codejudge'),
                ]),
                ['class' => 'mt-2']
            )
    ));

    // Wire the AMD module that handles the button click.
    global $PAGE;
    if ($PAGE->has_set_url()) {
        $PAGE->requires->js_call_amd('qtype_codejudge/settings_test', 'init');
    }
}
