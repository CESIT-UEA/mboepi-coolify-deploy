<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * editor.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

/**
 * Class editor
 */
class editor extends \editor_tiny\editor {
    /**
     * Use this editor for given element.
     *
     * @return string
     * @throws \dml_exception
     */
    public function tyni_editor_config() {
        global $PAGE;

        $options = ['noclean' => true];
        $context = $PAGE->context;
        if (isset($options['context']) && ($options['context'] instanceof \context)) {
            $context = $options['context'];
        }
        $config = (object) [
            'css' => $PAGE->theme->editor_css_url()->out(false),
            'context' => $context->id,
            'filepicker' => [],
            'currentLanguage' => current_language(),
            'branding' => false,
            'language' => [
                'currentlang' => current_language(),
                'installed' => get_string_manager()->get_list_of_translations(true),
                'available' => get_string_manager()->get_list_of_languages(),
            ],

            'placeholderSelectors' => [],
            'plugins' => $this->manager->get_plugin_configuration($context, $options, [], $this),
            'nestedmenu' => true,
        ];

        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
            $config->placeholderSelectors = ['.behat-tinymce-placeholder'];
        }

        $config = convert_to_array($config);
        return json_encode($config);
    }
}
