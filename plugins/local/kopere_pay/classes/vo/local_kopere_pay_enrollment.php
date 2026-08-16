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
 * local_kopere_pay_enrollment.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\vo;

use local_kopere_pay\util\enrollment_util;

/**
 * Class local_kopere_pay_enrollment
 */
class local_kopere_pay_enrollment extends \stdClass {

    /** @var int */
    public $id;

    /** @var int */
    public $userid;

    /** @var string */
    public $course;

    /** @var string */
    public $coupon;

    /** @var string */
    public $method;

    /** @var string */
    public $value;

    /**
     * Function create_by_object
     *
     * @param $item
     * @return local_kopere_pay_enrollment
     * @throws \coding_exception
     */
    public static function create_by_object($item) {
        $return = new local_kopere_pay_enrollment();

        $return->id = $item->id;
        $return->userid = optional_param('userid', $item->userid, PARAM_INT);
        $return->course = optional_param('course', $item->course, PARAM_TEXT);
        $return->coupon = optional_param('coupon', $item->coupon, PARAM_TEXT);
        $return->value = optional_param("value", $item->value, PARAM_TEXT);

        return $return;
    }

    /**
     * Function create_by_default
     *
     * @return local_kopere_pay_enrollment
     * @throws \coding_exception
     */
    public static function create_by_default() {
        $return = new local_kopere_pay_enrollment();

        $return->id = optional_param('id', 0, PARAM_TEXT);
        $return->userid = optional_param('userid', 0, PARAM_INT);
        $return->course = optional_param('course', '', PARAM_TEXT);
        $return->coupon = optional_param('coupon', '', PARAM_TEXT);
        $return->value = optional_param("value", '', PARAM_TEXT);

        return $return;
    }

    /**
     * Function add_enrollment
     *
     * @param $userid
     * @param $course
     * @param $method
     * @param $coupon
     * @param $value
     * @return bool|int
     * @throws \dml_exception
     */
    public static function add_enrollment($userid, $course, $method, $coupon, $value) {
        global $DB;

        if (is_string($coupon) || $coupon == null) {
            if (!isset($coupon[3])) {
                $datacoupon = '';
            } else {
                $datacoupon = $coupon;
            }
        } else if (isset($coupon->uniquekey)) {
            $datacoupon = $coupon->uniquekey;
        } else {
            $datacoupon = '';
        }

        $data = (object) [
            'userid' => $userid,
            'course' => $course,
            'method' => $method,
            "value" => $value,
            'status' => enrollment_util::WAITING,
            'coupon' => $datacoupon,
        ];

        try {
            $enrollment = $DB->insert_record('local_kopere_pay_enrollment', $data);
        } catch (\Exception $e) {

            $enrollment = 0;
        }

        if ($enrollment && strlen($datacoupon)) {
            $couponobject = $DB->get_record("local_kopere_pay_coupon", ['uniquekey' => $datacoupon]);
            if ($couponobject) {
                $couponobject->amount = $couponobject->amount - 1;

                if ($couponobject->amount < 0) {
                    $couponobject->amount = 0;
                }

                $sql = "
                    UPDATE {local_kopere_pay_coupon}
                       SET amount = amount - 1
                     WHERE uniquekey = :uniquekey
                       AND amount    > 0";
                $DB->execute($sql, ["uniquekey" => $datacoupon]);
            }
        }

        return $enrollment;
    }
}
