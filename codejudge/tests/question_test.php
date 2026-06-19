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
 * Unit tests for the codejudge question definition.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/codejudge/question.php');

/**
 * Tests for qtype_codejudge_question.
 */
class qtype_codejudge_question_testcase extends advanced_testcase {

    private function create_question(): \qtype_codejudge_question {
        $question = new \qtype_codejudge_question();
        $question->startercode = "<?php\n";
        $question->editorheight = 420;
        $question->language = 'python';
        $question->rubric = 'Check the output.';

        return $question;
    }

    public function test_expected_data_includes_code_and_language(): void {
        $question = $this->create_question();
        $expected = $question->get_expected_data();

        $this->assertArrayHasKey('code', $expected);
        $this->assertArrayHasKey('language', $expected);
        $this->assertSame(PARAM_RAW, $expected['code']);
        $this->assertSame(PARAM_ALPHANUMEXT, $expected['language']);
    }

    public function test_response_completion_and_grading_are_based_on_code(): void {
        $question = $this->create_question();

        $this->assertFalse($question->is_complete_response(['code' => '']));
        $this->assertFalse($question->is_gradable_response(['code' => '']));
        $this->assertTrue($question->is_complete_response(['code' => "print('hi')"]));
        $this->assertTrue($question->is_gradable_response(['code' => "print('hi')"]));
    }

    public function test_response_summary_is_truncated_and_normalised(): void {
        $question = $this->create_question();
        $code = str_repeat('abc', 60);
        $summary = $question->summarise_response(['code' => "line1\r\nline2\r\n" . $code]);

        $this->assertSame("line1\nline2\n" . mb_substr($code, 0, 108), $summary);
        $this->assertLessThanOrEqual(120, mb_strlen($summary));
    }

    public function test_same_response_ignores_line_ending_style(): void {
        $question = $this->create_question();

        $this->assertTrue($question->is_same_response(
            ['code' => "line1\r\nline2", 'language' => 'python'],
            ['code' => "line1\nline2", 'language' => 'python']
        ));
        $this->assertFalse($question->is_same_response(
            ['code' => 'print(1)', 'language' => 'python'],
            ['code' => 'print(2)', 'language' => 'python']
        ));
    }

    public function test_un_summarise_response_restores_code_payload(): void {
        $question = $this->create_question();
        $response = $question->un_summarise_response('print(1)');

        $this->assertSame('print(1)', $response['code']);
        $this->assertSame('python', $response['language']);
    }
}
