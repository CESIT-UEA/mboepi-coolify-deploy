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
 * send_event.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use coding_exception;
use stdClass;

/**
 * Class send_event
 */
class send_event {
    /**
     * Function kopere_pay_pago
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return bool
     * @throws coding_exception
     */
    public static function kopere_pay_pago(stdClass $course, stdClass $user): bool {
        return email_event::paid($course, $user);
    }

    /**
     * Function kopere_pay_refunded
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return bool
     * @throws coding_exception
     */
    public static function kopere_pay_refunded(stdClass $course, stdClass $user): bool {
        return email_event::refunded($course, $user);
    }
}
