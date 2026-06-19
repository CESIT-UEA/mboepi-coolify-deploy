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
 * Question type definition for coding questions.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/questiontypebase.php');

/**
 * Question type class for coding questions.
 */
class qtype_codejudge extends question_type {

    /**
     * Default editor height, in pixels.
     */
    private const DEFAULT_EDITOR_HEIGHT = 420;

    /**
     * Minimum editor height accepted by the editing form.
     */
    private const MIN_EDITOR_HEIGHT = 200;

    /**
     * Returns the plugin name.
     *
     * @return string
     */
    public function name() {
        return 'codejudge';
    }

    /**
     * Fields stored in the extra question options table.
     *
     * @return array
     */
    public function extra_question_fields() {
        return [
            'qtype_codejudge_options',
            'language',
            'rubric',
            'startercode',
            'editorheight',
        ];
    }

    /**
     * Saves the extra question options.
     *
     * @param stdClass $question Question data from the editing form.
     * @return bool
     */
    public function save_question_options($question) {
        global $DB;

        $record = $DB->get_record('qtype_codejudge_options', ['questionid' => $question->id]);
        $now = time();
        $language = \qtype_codejudge\local\language_helper::normalise($question->language ?? null);

        if (!$record) {
            $record = new stdClass();
            $record->questionid = $question->id;
            $record->timecreated = $now;
        }

        $record->language = $language;
        $record->rubric = trim((string)($question->rubric ?? ''));
        $record->startercode = (string)($question->startercode ?? '');
        $record->editorheight = self::normalise_editor_height($question->editorheight ?? null);
        $record->timemodified = $now;

        if (empty($record->id)) {
            $record->id = $DB->insert_record('qtype_codejudge_options', $record);
        } else {
            $DB->update_record('qtype_codejudge_options', $record);
        }

        return true;
    }

    /**
     * Loads the extra question options.
     *
     * @param stdClass $question Question data being loaded.
     * @return bool
     */
    public function get_question_options($question) {
        global $DB;

        $options = $DB->get_record('qtype_codejudge_options', ['questionid' => $question->id]);
        if (!$options) {
            $options = (object) [
                'questionid' => $question->id,
                'language' => \qtype_codejudge\local\language_helper::get_default(),
                'rubric' => '',
                'startercode' => '',
                'editorheight' => self::DEFAULT_EDITOR_HEIGHT,
                'timecreated' => 0,
                'timemodified' => 0,
            ];
        }

        $question->options = $options;
        $question->language = \qtype_codejudge\local\language_helper::normalise($options->language ?? null);
        $question->rubric = (string)($options->rubric ?? '');
        $question->startercode = (string)($options->startercode ?? '');
        $question->editorheight = self::normalise_editor_height($options->editorheight ?? null);

        return true;
    }

    /**
     * Initialise the question instance properties from the loaded database options.
     *
     * @param question_definition $question
     * @param stdClass $questiondata
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->language = \qtype_codejudge\local\language_helper::normalise($questiondata->options->language ?? null);
        $question->rubric = (string)($questiondata->options->rubric ?? '');
        $question->startercode = (string)($questiondata->options->startercode ?? '');
        $question->editorheight = self::normalise_editor_height($questiondata->options->editorheight ?? null);
    }

    /**
     * Deletes a question and its extra data.
     *
     * @param int $questionid The question id.
     * @param int $contextid Context id.
     * @return bool
     */
    public function delete_question($questionid, $contextid) {
        global $DB;

        $DB->delete_records('qtype_codejudge_grading', ['questionid' => $questionid]);
        $DB->delete_records('qtype_codejudge_options', ['questionid' => $questionid]);
        return parent::delete_question($questionid, $contextid);
    }

    /**
     * Normalise the stored editor height to a safe value for the edit form and renderer.
     *
     * @param mixed $height Raw height value.
     * @return int Normalised height in pixels.
     */
    private static function normalise_editor_height($height): int {
        $height = (int)$height;

        if ($height < self::MIN_EDITOR_HEIGHT) {
            return self::DEFAULT_EDITOR_HEIGHT;
        }

        return $height;
    }
}
