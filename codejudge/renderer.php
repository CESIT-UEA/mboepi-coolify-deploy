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
 * Renderer for coding questions.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer that provides the code editor and question controls.
 */
class qtype_codejudge_renderer extends qtype_renderer {

    /**
     * Render the question formulation and response control.
     *
     * @param question_attempt $qa Question attempt.
     * @param question_display_options $options Display options.
     * @return string
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $fieldname = $qa->get_qt_field_name('code');
        $current = trim((string)($response['code'] ?? ''));

        if ($current === '') {
            $current = (string)($question->startercode ?? '');
        }

        $height = (int)($question->editorheight ?? 420);
        $rootid = uniqid('qtype-codejudge-');
        $language = \qtype_codejudge\local\language_helper::normalise($question->language ?? null);
        $languageoptions = \qtype_codejudge\local\language_helper::get_options();
        $questionattemptid = method_exists($qa, 'get_database_id') ? (int)$qa->get_database_id() : 0;

        $textareaattributes = [
            'id' => $rootid . '-code',
            'name' => $fieldname,
            'class' => 'form-control qtype-codejudge-editor',
            'rows' => 12,
            'style' => 'min-height:' . $height . 'px; font-family: monospace;',
            'spellcheck' => 'false',
            'autocapitalize' => 'off',
            'autocomplete' => 'off',
            'autocorrect' => 'off',
            'data-region' => 'codejudge-code',
            'data-language' => $language,
        ];

        if ($options->readonly) {
            $textareaattributes['readonly'] = 'readonly';
            $textareaattributes['data-readonly'] = '1';
        }

        $html = [];
        $html[] = html_writer::tag(
            'div',
            $question->format_questiontext($qa),
            ['class' => 'qtext']
        );
        $html[] = html_writer::tag(
            'div',
            get_string('allowedlanguage', 'qtype_codejudge') . ': ' . s($languageoptions[$language] ?? $language),
            ['class' => 'badge bg-light text-dark mb-2']
        );
        $html[] = html_writer::tag(
            'div',
            get_string('editor_help', 'qtype_codejudge'),
            ['class' => 'small text-muted mb-2']
        );
        $html[] = html_writer::tag('label', get_string('responseeditor', 'qtype_codejudge'), [
            'class' => 'font-weight-bold mb-2',
            'for' => $rootid . '-code',
        ]);
        $html[] = html_writer::tag('textarea', s($current), $textareaattributes);
        $html[] = html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => $qa->get_qt_field_name('language'),
            'value' => $language,
        ]);

        $this->page->requires->js_call_amd('qtype_codejudge/editor', 'init', [$rootid]);

        return html_writer::tag('div', implode('', $html), [
            'class' => 'qtype-codejudge-formulation',
            'id' => $rootid,
            'data-region' => 'codejudge-root',
            'data-questionid' => (int)$question->id,
            'data-questionattemptid' => $questionattemptid,
            'data-language' => $language,
        ]);
    }
}
