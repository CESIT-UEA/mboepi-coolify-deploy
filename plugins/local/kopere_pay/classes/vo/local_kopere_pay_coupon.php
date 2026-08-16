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
 * local_kopere_pay_coupon.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\vo;

/**
 * Class local_kopere_pay_coupon
 */
class local_kopere_pay_coupon extends \stdClass {

    /** @var int */
    public $id;

    /** @var string */
    public $course;

    /** @var string */
    public $type;

    /** @var string */
    public $uniquekey;

    /** @var string */
    public $email;

    /** @var int */
    public $amount;

    /** @var string */
    public $value;

    /** @var int */
    public $time;

    /**
     * Function create_by_object
     *
     * @param $item
     * @return local_kopere_pay_coupon
     * @throws \coding_exception
     */
    public static function create_by_object($item) {
        $return = new local_kopere_pay_coupon();

        $return->id = $item->id;
        $return->course = optional_param('course', $item->course, PARAM_TEXT);
        $return->type = optional_param('type', $item->type, PARAM_TEXT);
        $return->uniquekey = optional_param('uniquekey', $item->uniquekey, PARAM_TEXT);
        $return->email = optional_param('email', $item->email, PARAM_TEXT);
        $return->amount = optional_param('amount', $item->amount, PARAM_INT);
        $return->value = optional_param("value", $item->value, PARAM_TEXT);
        $return->time = optional_param('time', $item->time, PARAM_INT);

        return $return;
    }

    /**
     * Function create_by_default
     *
     * @return local_kopere_pay_coupon
     * @throws \coding_exception
     */
    public static function create_by_default() {
        $return = new local_kopere_pay_coupon();

        $return->id = optional_param('id', 0, PARAM_INT);
        $return->course = optional_param('course', '', PARAM_TEXT);
        $return->type = optional_param('type', '', PARAM_TEXT);
        $return->uniquekey = optional_param('uniquekey', '', PARAM_TEXT);
        $return->email = optional_param('email', '', PARAM_TEXT);
        $return->amount = optional_param('amount', 0, PARAM_INT);
        $return->value = optional_param("value", '', PARAM_TEXT);
        $return->time = optional_param('time', 0, PARAM_INT);

        return $return;
    }
}
