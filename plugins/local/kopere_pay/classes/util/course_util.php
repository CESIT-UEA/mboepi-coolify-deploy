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
 * course_util.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use coding_exception;
use dml_exception;
use Exception;
use local_kopere_pay\shopping\pay_header;
use stdClass;

/**
 * Class course_util
 */
class course_util {
    /**
     * Function find
     *
     * @param $id
     * @param $showerror
     * @return false|mixed|stdClass|null
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function find($id, $showerror = true) {
        global $DB;

        if (!isset($id[0])) {
            throw new Exception("no course");
        }

        if ($id[0] == 'c') {
            $findid = str_replace("c", "", $id);
            $course = $DB->get_record('cohort', ['id' => $findid]);
            if ($showerror) {
                pay_header::notfound_null($course, get_string('cohort_not_found', "local_kopere_pay"));
            } else if ($course == null) {
                return null;
            }

            $course->fullname = $course->name;
            $course->isCoorte = true;
        } else {
            $findid = intval($id);
            $course = $DB->get_record('course', ['id' => $findid]);
            if ($showerror) {
                pay_header::notfound_null($course, get_string('course_not_found', "local_kopere_pay"));
            } else if ($course == null) {
                return null;
            }

            $course->isCoorte = false;
        }

        return $course;
    }
}
