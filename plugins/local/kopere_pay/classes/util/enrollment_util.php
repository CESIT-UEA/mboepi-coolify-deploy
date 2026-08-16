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
 * enrollment_util.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class enrollment_util
 *
 * @package local_kopere_pay\util
 */
class enrollment_util {
    /**
     *
     */
    const PAID = "paid";
    /**
     *
     */
    const WAITING = "waiting";

    /**
     * Function changue_status
     *
     * @param $enrollment
     * @param $newstatus
     *
     * @throws \dml_exception
     */
    public static function changue_status($enrollment, $newstatus) {
        global $DB;

        if (is_number($enrollment)) {
            $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollment]);
        }

        $enrollment->status = $newstatus;
        $DB->update_record("local_kopere_pay_enrollment", $enrollment);
    }

    /**
     * Function hide_order_details
     *
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function hide_order_details($koperepaydetalhe) {
        $price = coupon_util::get_preco($koperepaydetalhe);

        return self::hide_order($price);
    }

    /**
     * Function hide_order
     *
     * @param $price
     *
     * @return string
     *
     * @throws \dml_exception
     */
    public static function hide_order($price) {
        if (get_config("local_kopere_dashboard", "hide_zero_summary") && $price <= .5) {
            return "
            <style>
                .form-enrollment .order-up {
                    display:none;
                }
                .form-enrollment .order-down{
                    width:100%;
                    flex:0 0 100%;
                    max-width:100%;
                }
            </style>";
        }

        return "";
    }
}
