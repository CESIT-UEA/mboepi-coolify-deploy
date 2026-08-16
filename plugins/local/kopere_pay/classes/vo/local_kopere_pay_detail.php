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
 * local_kopere_pay_detail.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\vo;

use local_kopere_dashboard\util\string_util;
use local_kopere_pay\util\formater;

/**
 * Class local_kopere_pay_detail
 */
class local_kopere_pay_detail extends \stdClass {

    /** @var int */
    public $id;

    /** @var string */
    public $course;

    /** @var string */
    public $price;

    /** @var int */
    public $days;

    /** @var string */
    public $date;

    /** @var int */
    public $students;

    /** @var string */
    public $charge;

    /** @var string */
    public $status;

    /** @var string */
    public $link;

    /** @var string */
    public $portfolio;

    /** @var string */
    public $extra;

    /** @var int */
    public $fieldcategory;

    /** @var string */
    public $extradata;

    /**
     * Function create_by_object
     *
     * @param $item
     * @return local_kopere_pay_detail
     * @throws \coding_exception
     */
    public static function create_by_object($item) {
        $return = new local_kopere_pay_detail();

        $return->id = $item->id;
        $return->course = optional_param('course', $item->course, PARAM_TEXT);
        $return->price = optional_param('price', $item->price, PARAM_TEXT);
        $return->days = optional_param('days', $item->days, PARAM_INT);
        $return->date = optional_param('data', $item->date, PARAM_TEXT);
        $return->students = optional_param('students', $item->students, PARAM_INT);
        $return->charge = optional_param('charge', $item->charge, PARAM_TEXT);
        $return->status = optional_param('status', $item->status, PARAM_TEXT);
        $return->link = optional_param('link', $item->link, PARAM_TEXT);
        $return->extra = optional_param('extra', $item->extra, PARAM_RAW);
        $return->fieldcategory = optional_param('fieldcategory', $item->fieldcategory, PARAM_INT);
        $return->extradata = json_encode($_POST["extradata"]);
        $return->portfolio = optional_param('portfolio', $item->portfolio, PARAM_TEXT);

        $number = formater::price_to_float($return->price);
        $return->price = number_format($number, 2, ',', '');

        return $return;
    }

    /**
     * Function create_by_default
     *
     * @return local_kopere_pay_detail
     * @throws \coding_exception
     */
    public static function create_by_default() {
        $return = new local_kopere_pay_detail();

        $return->course = optional_param('course', '', PARAM_TEXT);
        $return->price = optional_param('price', '', PARAM_TEXT);
        $return->days = optional_param('days', 0, PARAM_INT);
        $return->date = optional_param('data', '', PARAM_TEXT);
        $return->students = optional_param('students', 0, PARAM_INT);
        $return->charge = optional_param('charge', '', PARAM_TEXT);
        $return->status = optional_param('status', '', PARAM_TEXT);
        $return->link = optional_param('link', '', PARAM_TEXT);
        $return->extra = optional_param('extra', '', PARAM_RAW);
        $return->fieldcategory = optional_param('fieldcategory', '', PARAM_TEXT);
        $return->extradata = json_encode($_POST["extradata"]);

        return $return;
    }
}
