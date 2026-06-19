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
 * External function: check_status.
 *
 * Placeholder endpoint for future grading-status polling from the quiz flow.
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
/**
 * Returns the current status and result for a grading record.
 */
class check_status extends external_api {

    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gradingid' => new external_value(PARAM_INT, 'Grading record id.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Returns the current grading status and stored AI result.
     *
     * @param int $gradingid Grading record id.
     * @return array
     */
    public static function execute(int $gradingid): array {
        global $DB;

        self::validate_parameters(self::execute_parameters(), [
            'gradingid' => $gradingid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $record = $DB->get_record('qtype_codejudge_grading', ['id' => $gradingid]);
        if (!$record) {
            return [
                'available' => false,
                'status' => 'missing',
                'score' => 0.0,
                'feedback' => '',
                'errormessage' => get_string('grading_status_unavailable', 'qtype_codejudge'),
                'message' => get_string('grading_status_unavailable', 'qtype_codejudge'),
            ];
        }

        $statusmessage = match ($record->status) {
            'graded' => get_string('grading_status_graded', 'qtype_codejudge'),
            'error' => get_string('grading_status_error', 'qtype_codejudge'),
            'processing' => get_string('grading_status_processing', 'qtype_codejudge'),
            default => get_string('grading_status_queued', 'qtype_codejudge'),
        };

        return [
            'available' => true,
            'status' => (string)$record->status,
            'score' => (float)($record->score ?? 0),
            'feedback' => (string)($record->feedback ?? ''),
            'errormessage' => (string)($record->errormessage ?? ''),
            'message' => $statusmessage,
        ];
    }

    /**
     * Describes the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Whether grading status tracking is implemented and available.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Current status value.'),
            'score' => new external_value(PARAM_FLOAT, 'AI score, when available.'),
            'feedback' => new external_value(PARAM_RAW, 'AI feedback, when available.'),
            'errormessage' => new external_value(PARAM_RAW, 'Error message captured during processing, when available.'),
            'message' => new external_value(PARAM_RAW, 'Human-readable status message.'),
        ]);
    }
}
