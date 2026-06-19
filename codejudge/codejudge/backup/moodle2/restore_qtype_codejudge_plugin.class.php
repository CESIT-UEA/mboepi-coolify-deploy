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
 * Restore class for qtype_codejudge.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore class for the codejudge question type.
 */
class restore_qtype_codejudge_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the restore process.
     *
     * @return array
     */
    protected function define_question_plugin_structure(): array {
        $paths = [];

        // Define the XML path for restoring codejudge options.
        // It maps directly to: /question_categories/question_category/questions/question/plugin_qtype_codejudge_private/qtype_codejudge_options
        $optionspath = $this->get_pathfor('/qtype_codejudge_options');
        $paths[] = new restore_path_element('qtype_codejudge_options', $optionspath);

        return $paths;
    }

    /**
     * Processes the XML element data and inserts the record into the database.
     *
     * @param array $data Data from the XML.
     * @return void
     */
    public function process_qtype_codejudge_options(array $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);

        // Map the question ID to the new question ID in the database.
        $data->questionid = $this->get_new_parentid('question');

        // Check if a record already exists (defensive).
        $exists = $DB->record_exists('qtype_codejudge_options', ['questionid' => $data->questionid]);
        if (!$exists) {
            $newitemid = $DB->insert_record('qtype_codejudge_options', $data);
            $this->set_mapping('qtype_codejudge_options', $oldid, $newitemid);
        }
    }
}
