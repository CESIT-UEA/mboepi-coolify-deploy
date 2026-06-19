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
 * Editing form for coding questions.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/edit_question_form.php');

/**
 * Coding question editing form.
 */
class qtype_codejudge_edit_form extends question_edit_form {

    /**
     * Return the question type name.
     *
     * @return string
     */
    public function qtype() {
        return 'codejudge';
    }

    /**
     * Define type-specific fields.
     *
     * @param MoodleQuickForm $mform Form object.
     */
    protected function definition_inner($mform) {
        $languages = \qtype_codejudge\local\language_helper::get_options();

        $mform->addElement('select', 'language', get_string('language', 'qtype_codejudge'), $languages);
        $mform->setDefault('language', \qtype_codejudge\local\language_helper::get_default());
        $mform->addRule('language', null, 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'rubric',
            get_string('rubric', 'qtype_codejudge'),
            ['rows' => 8, 'cols' => 80, 'style' => 'width:100%;']
        );
        $mform->setType('rubric', PARAM_RAW);

        $mform->addElement(
            'textarea',
            'startercode',
            get_string('startercode', 'qtype_codejudge'),
            ['rows' => 12, 'cols' => 80, 'style' => 'width:100%; font-family: monospace;']
        );
        $mform->setType('startercode', PARAM_RAW);

        $mform->addElement('text', 'editorheight', get_string('editorheight', 'qtype_codejudge'), ['size' => 6]);
        $mform->setType('editorheight', PARAM_INT);
        $mform->setDefault('editorheight', 420);
    }

    /**
     * Load the saved options into the form.
     *
     * @param array $question Question data.
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        if (!empty($question->options)) {
            $question->language = \qtype_codejudge\local\language_helper::normalise($question->options->language ?? null);
            $question->rubric = $question->options->rubric ?? '';
            $question->startercode = $question->options->startercode ?? '';
            $question->editorheight = $question->options->editorheight ?? 420;
        }

        return $question;
    }

    /**
     * Validate the custom fields.
     *
     * @param array $data Submitted form data.
     * @param array $files Uploaded files.
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['rubric'] ?? ''))) {
            $errors['rubric'] = get_string('error_empty_rubric', 'qtype_codejudge');
        }

        $language = $data['language'] ?? '';
        if (empty(trim((string)$language))) {
            $errors['language'] = get_string('error_no_language', 'qtype_codejudge');
        } else if (!\qtype_codejudge\local\language_helper::is_supported((string)$language)) {
            $errors['language'] = get_string('error_invalid_language', 'qtype_codejudge');
        }

        $height = (int)($data['editorheight'] ?? 0);
        if ($height < 200) {
            $errors['editorheight'] = get_string('error_invalid_editorheight', 'qtype_codejudge');
        }

        return $errors;
    }
}
