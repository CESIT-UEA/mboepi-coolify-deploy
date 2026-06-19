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
 * Helper for assembling AI grading jobs.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared helpers used by the grading queue and future request entry points.
 */
class grading_helper {

    /**
     * Normalises source code before it is stored or sent to the AI provider.
     *
     * @param string $code Source code.
     * @return string
     */
    public static function normalise_code(string $code): string {
        return str_replace(["\r\n", "\r"], "\n", $code);
    }

    /**
     * Converts rich question text into plain text for the AI prompt.
     *
     * @param string $text Question text.
     * @return string
     */
    public static function plain_text(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return trim(strip_tags($text));
    }

    /**
     * Builds the prompt sent to the AI provider.
     *
     * @param string $questiontext Question statement.
     * @param string $rubric Rubric or grading instructions.
     * @param string $language Programming language key.
     * @param string $code Student code.
     * @param string $startercode Optional starter code.
     * @return string
     */
    public static function build_prompt(
        string $questiontext,
        string $rubric,
        string $language,
        string $code,
        string $startercode = ''
    ): string {
        $language = language_helper::normalise($language);
        $languages = language_helper::get_options();
        $languagelabel = $languages[$language] ?? $language;
        $questiontext = self::plain_text($questiontext);
        $rubric = trim($rubric);
        $code = self::normalise_code(trim($code));
        $startercode = self::normalise_code(trim($startercode));

        $prompt = [];
        $prompt[] = 'You are an expert pedagogical code evaluator.';
        $prompt[] = 'Return ONLY a valid JSON object with this exact structure:';
        $prompt[] = '{"score": <number between 0 and 100>, "feedback": "<pedagogical feedback>"}';
        $prompt[] = '';

        if ($questiontext !== '') {
            $prompt[] = 'QUESTION:';
            $prompt[] = $questiontext;
            $prompt[] = '';
        }

        if ($rubric !== '') {
            $prompt[] = 'RUBRIC:';
            $prompt[] = $rubric;
            $prompt[] = '';
        }

        $prompt[] = 'PROGRAMMING LANGUAGE: ' . $languagelabel;
        $prompt[] = '';

        if ($startercode !== '') {
            $prompt[] = 'STARTER CODE:';
            $prompt[] = '```' . $language;
            $prompt[] = $startercode;
            $prompt[] = '```';
            $prompt[] = '';
        }

        $prompt[] = 'STUDENT CODE:';
        $prompt[] = '```' . $language;
        $prompt[] = $code;
        $prompt[] = '```';
        $prompt[] = '';
        $prompt[] = 'Be constructive, specific, and do not reveal a full solution.';

        return implode("\n", $prompt);
    }

    /**
     * Creates a grading record and queues the background task.
     *
     * @param \stdClass $question Loaded question record or definition.
     * @param array $response Question response data.
     * @param int $questionattemptid Quiz question attempt id, if available.
     * @param int $questionattemptstepid Question attempt step id, if available.
     * @param int $userid User id.
     * @return int The created grading record id.
     */
    public static function queue_grading_request(
        \stdClass $question,
        array $response,
        int $questionattemptid = 0,
        int $questionattemptstepid = 0,
        int $userid = 0
    ): int {
        global $DB, $USER;

        $code = (string)($response['code'] ?? '');
        $language = language_helper::normalise((string)($response['language'] ?? ($question->language ?? '')));
        $rubric = (string)($question->rubric ?? '');
        $startercode = (string)($question->startercode ?? '');
        $questiontext = (string)($question->questiontext ?? '');
        $userid = $userid > 0 ? $userid : (int)($USER->id ?? 0);

        if ($userid <= 0) {
            throw new \moodle_exception(
                'error_invalid_userid',
                'qtype_codejudge',
                '',
                null,
                'A valid user id is required to queue a grading request.'
            );
        }

        $record = (object) [
            'questionid' => (int)$question->id,
            'questionattemptid' => $questionattemptid > 0 ? $questionattemptid : null,
            'questionattemptstepid' => $questionattemptstepid > 0 ? $questionattemptstepid : null,
            'userid' => $userid,
            'language' => $language,
            'code' => self::normalise_code($code),
            'rubric' => $rubric,
            'prompt' => self::build_prompt($questiontext, $rubric, $language, $code, $startercode),
            'status' => 'queued',
            'score' => null,
            'feedback' => null,
            'rawresponse' => null,
            'errormessage' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $record->id = $DB->insert_record('qtype_codejudge_grading', $record);

        $task = new \qtype_codejudge\task\grade_submission();
        $task->set_custom_data(['gradingid' => $record->id]);
        \core\task\manager::queue_adhoc_task($task);

        return (int)$record->id;
    }
}
