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
 * Backup class for qtype_codejudge.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backup class for the codejudge question type.
 */
class backup_qtype_codejudge_plugin extends backup_qtype_plugin {

    /**
     * Returns the XML structure for the question type configurations.
     *
     * @return backup_nested_element
     */
    protected function define_question_plugin_structure(): backup_nested_element {
        // Define the virtual plugin element
        $plugin = $this->get_plugin_element(null, '../../qtype', 'codejudge');

        // Create the helper structure
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the pluginwrapper to the plugin element
        $plugin->add_child($pluginwrapper);

        // Define our custom table
        $options = new backup_nested_element('qtype_codejudge_options', ['id'], [
            'language', 'rubric', 'startercode', 'editorheight', 'timecreated', 'timemodified'
        ]);

        // Connect the table to the wrapper
        $pluginwrapper->add_child($options);

        // Define source mapping
        $options->set_source_table('qtype_codejudge_options', ['questionid' => backup::VAR_PARENTID]);

        return $plugin;
    }
}
