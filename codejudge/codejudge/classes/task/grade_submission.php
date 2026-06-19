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
 * Ad-hoc task that sends a queued grading request to an AI provider.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\task;

defined('MOODLE_INTERNAL') || die();

use qtype_codejudge\ai\provider_factory;

/**
 * Background task that processes one queued AI grading request.
 */
class grade_submission extends \core\task\adhoc_task {

    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_grade_submission', 'qtype_codejudge');
    }

    /**
     * Executes the background grading request.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->gradingid)) {
            mtrace('qtype_codejudge grade_submission: missing gradingid in task payload.');
            return;
        }

        $gradingid = (int)$data->gradingid;
        $record = $DB->get_record('qtype_codejudge_grading', ['id' => $gradingid]);
        if (!$record) {
            mtrace("qtype_codejudge grade_submission: grading record {$gradingid} not found.");
            return;
        }

        if ($record->status === 'graded') {
            mtrace("qtype_codejudge grade_submission: grading record {$gradingid} already processed.");
            return;
        }

        $now = time();
        $record->status = 'processing';
        $record->timemodified = $now;
        $DB->update_record('qtype_codejudge_grading', $record);

        try {
            $provider = provider_factory::get_provider();
            $result = $provider->evaluate((string)$record->prompt);

            $record->status = 'graded';
            $record->score = max(0.0, min(100.0, (float)$result['score']));
            $record->feedback = (string)$result['feedback'];
            $record->rawresponse = (string)$result['raw'];
            $record->errormessage = null;
            $record->timemodified = time();
            $DB->update_record('qtype_codejudge_grading', $record);

            mtrace("qtype_codejudge grade_submission: grading record {$gradingid} completed with score {$record->score}.");
        } catch (\Throwable $e) {
            $record->status = 'error';
            $record->errormessage = $e->getMessage();
            $record->timemodified = time();
            $DB->update_record('qtype_codejudge_grading', $record);

            mtrace("qtype_codejudge grade_submission: error for record {$gradingid}: " . $e->getMessage());

            throw new \moodle_exception(
                'error_ai_response_invalid',
                'qtype_codejudge',
                '',
                null,
                $e->getMessage()
            );
        }
    }
}
