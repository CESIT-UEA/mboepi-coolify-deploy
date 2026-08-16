<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * complete.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\shopping;

use coding_exception;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\header;
use local_kopere_pay\meios\IMeio;
use moodle_exception;
use require_login_exception;

/**
 * Class complete
 */
class complete {
    /**
     * Function show
     *
     * @param $course
     * @param $id
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     * @throws require_login_exception
     */
    public function show($course, $id) {
        global $USER, $CFG, $OUTPUT, $PAGE;

        require_login();
        if (enroll_util::enrolled($course, $USER)) {
            header::location("{$CFG->wwwroot}/course/view.php?id={$id}");
        }

        $meio = optional_param('meio', '', PARAM_TEXT);

        $PAGE->set_title(get_string("complete_title", "local_kopere_pay"));
        echo $OUTPUT->header();

        if ($meio) {
            /** @var IMeio $class */
            $class = "\\local_kopere_pay\\meios\\{$meio}";
            $class::completed($course, $USER);
        } else {
            echo get_string("complete_method_not_found", "local_kopere_pay");
        }
        echo $OUTPUT->footer();
    }
}
