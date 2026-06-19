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
 * Event observer callbacks for qtype_codejudge.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge;

defined('MOODLE_INTERNAL') || die();

use core\event\base;
use qtype_codejudge\local\grading_helper;

/**
 * Handles Moodle events that are relevant to codejudge questions.
 */
class observer {

    /**
     * Queue AI grading for codejudge responses after a Quiz attempt is submitted.
     *
     * @param base $event Quiz attempt submitted event.
     */
    public static function quiz_attempt_submitted(base $event): void {
        global $DB;

        $attemptid = (int)$event->objectid;
        if ($attemptid <= 0) {
            return;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, uniqueid, userid');
        if (!$attempt || empty($attempt->uniqueid)) {
            return;
        }

        $sql = "SELECT qa.id AS questionattemptid,
                       qa.questionid,
                       q.questiontext,
                       opts.language,
                       opts.rubric,
                       opts.startercode
                  FROM {question_attempts} qa
                  JOIN {question} q ON q.id = qa.questionid
                  JOIN {qtype_codejudge_options} opts ON opts.questionid = q.id
                 WHERE qa.questionusageid = :questionusageid
                   AND q.qtype = :qtype";

        $questionattempts = $DB->get_records_sql($sql, [
            'questionusageid' => $attempt->uniqueid,
            'qtype' => 'codejudge',
        ]);

        foreach ($questionattempts as $questionattempt) {
            self::queue_latest_response($questionattempt, (int)$attempt->userid);
        }
    }

    /**
     * Queue the latest submitted response for one codejudge question attempt.
     *
     * @param \stdClass $questionattempt Question attempt data plus question options.
     * @param int $userid Attempt owner user id.
     */
    private static function queue_latest_response(\stdClass $questionattempt, int $userid): void {
        global $DB;

        $response = $DB->get_record_sql(
            "SELECT qas.id AS questionattemptstepid,
                    codedata.value AS code,
                    langdata.value AS language
               FROM {question_attempt_steps} qas
               JOIN {question_attempt_step_data} codedata
                 ON codedata.attemptstepid = qas.id
                AND codedata.name = :codename
          LEFT JOIN {question_attempt_step_data} langdata
                 ON langdata.attemptstepid = qas.id
                AND langdata.name = :languagename
              WHERE qas.questionattemptid = :questionattemptid
                AND " . $DB->sql_compare_text('codedata.value') . " <> :emptycode
           ORDER BY qas.sequencenumber DESC",
            [
                'codename' => 'code',
                'languagename' => 'language',
                'questionattemptid' => $questionattempt->questionattemptid,
                'emptycode' => '',
            ],
            IGNORE_MULTIPLE
        );

        if (!$response) {
            return;
        }

        if ($DB->record_exists('qtype_codejudge_grading', [
            'questionattemptid' => $questionattempt->questionattemptid,
            'questionattemptstepid' => $response->questionattemptstepid,
        ])) {
            return;
        }

        $code = (string)$response->code;
        if (trim($code) === '') {
            return;
        }

        $language = (string)($response->language ?? ($questionattempt->language ?? ''));

        grading_helper::queue_grading_request(
            (object) [
                'id' => (int)$questionattempt->questionid,
                'questiontext' => (string)$questionattempt->questiontext,
                'language' => (string)$questionattempt->language,
                'rubric' => (string)$questionattempt->rubric,
                'startercode' => (string)$questionattempt->startercode,
            ],
            [
                'code' => $code,
                'language' => $language,
            ],
            (int)$questionattempt->questionattemptid,
            (int)$response->questionattemptstepid,
            $userid
        );
    }
}
