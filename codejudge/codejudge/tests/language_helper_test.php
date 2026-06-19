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
 * Unit tests for language helper.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/codejudge/classes/local/language_helper.php');

/**
 * Tests for supported language handling.
 */
class qtype_codejudge_language_helper_testcase extends advanced_testcase {

    public function test_get_default_returns_python(): void {
        $this->assertSame('python', \qtype_codejudge\local\language_helper::get_default());
    }

    public function test_normalise_falls_back_to_default_for_invalid_values(): void {
        $this->assertSame('python', \qtype_codejudge\local\language_helper::normalise(''));
        $this->assertSame('python', \qtype_codejudge\local\language_helper::normalise('rust'));
    }

    public function test_normalise_keeps_supported_values(): void {
        $this->assertSame('java', \qtype_codejudge\local\language_helper::normalise('java'));
    }
}
