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
 * External function: queue_grading.
 *
 * Creates a grading job from a question response and queues the background AI task.
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
use qtype_codejudge\local\grading_helper;

/**
 * Queues a grading request for asynchronous AI processing.
 */
class queue_grading extends external_api {

    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question id.', VALUE_REQUIRED),
            'code' => new external_value(PARAM_RAW, 'Student code.', VALUE_REQUIRED),
            'language' => new external_value(PARAM_ALPHANUMEXT, 'Programming language.', VALUE_REQUIRED),
            'questionattemptid' => new external_value(PARAM_INT, 'Question attempt id.', VALUE_DEFAULT, 0),
            'questionattemptstepid' => new external_value(PARAM_INT, 'Question attempt step id.', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'User id.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Queues the grading request.
     *
     * @param int $questionid Question id.
     * @param string $code Student code.
     * @param string $language Language key.
     * @param int $questionattemptid Question attempt id.
     * @param int $questionattemptstepid Question attempt step id.
     * @param int $userid User id.
     * @return array
     */
    public static function execute(
        int $questionid,
        string $code,
        string $language,
        int $questionattemptid = 0,
        int $questionattemptstepid = 0,
        int $userid = 0
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid' => $questionid,
            'code' => $code,
            'language' => $language,
            'questionattemptid' => $questionattemptid,
            'questionattemptstepid' => $questionattemptstepid,
            'userid' => $userid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $question = $DB->get_record('question', ['id' => $params['questionid']], '*', MUST_EXIST);
        if (($question->qtype ?? '') !== 'codejudge') {
            throw new \moodle_exception(
                'error_invalid_question_type',
                'qtype_codejudge',
                '',
                null,
                'Question is not a codejudge question.'
            );
        }

        $options = $DB->get_record('qtype_codejudge_options', ['questionid' => $question->id], '*', MUST_EXIST);
        $question->language = $options->language ?? 'python';
        $question->rubric = $options->rubric ?? '';
        $question->startercode = $options->startercode ?? '';
        $question->questiontext = $question->questiontext ?? '';

        $gradingid = grading_helper::queue_grading_request(
            $question,
            [
                'code' => $params['code'],
                'language' => $params['language'],
            ],
            $params['questionattemptid'],
            $params['questionattemptstepid'],
            $params['userid']
        );

        return [
            'gradingid' => $gradingid,
            'status' => 'queued',
            'message' => 'Queued grading request #' . $gradingid,
        ];
    }

    /**
     * Describes the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'gradingid' => new external_value(PARAM_INT, 'Created grading record id.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Job status.'),
            'message' => new external_value(PARAM_RAW, 'Human-readable response message.'),
        ]);
    }
}
