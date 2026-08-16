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
 * coupon_util.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use coding_exception;
use dml_exception;
use local_kopere_pay\vo\local_kopere_pay_coupon;
use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class coupon_util
 */
class coupon_util {
    /** @var string */
    public static $price;
    /** @var string */
    public static $desconto = '0,00';

    /**
     * Function get_preco
     *
     * @param local_kopere_pay_detail $koperepaydetalhe
     * @param local_kopere_pay_coupon $coupon
     *
     * @return string
     * @throws \Exception
     */
    public static function get_preco($koperepaydetalhe, $coupon = null, $isfornated = true) {

        if ($coupon == null) {
            $coupon = self::get_cupom($koperepaydetalhe->course);
        }

        if ($coupon && $coupon->amount > 0) {
            self::$price = formater::price_to_float($coupon->value);

            $desconto = formater::price_to_float($koperepaydetalhe->price) - formater::price_to_float($coupon->value);
            if ($isfornated) {
                self::$desconto = formater::number_formater($desconto);
            } else {
                self::$desconto = $desconto;
            }

        } else {
            self::$price = $koperepaydetalhe->price;
        }

        if ($isfornated) {
            return formater::number_formater(self::$price);
        } else {
            return self::$price;
        }
    }

    /**
     * Function get_desconto
     *
     * @param local_kopere_pay_detail $koperepaydetalhe
     * @param local_kopere_pay_coupon $coupon
     *
     * @return string
     * @throws \Exception
     */
    public static function get_desconto($koperepaydetalhe, $coupon = null) {
        self::get_preco($koperepaydetalhe, $coupon);

        return self::$desconto;
    }

    /** @var string */
    public static $error = "";
    /** @var array */
    public static $coupons = [];

    /**
     * Function get_cupom
     *
     * @param $courseid
     * @return local_kopere_pay_coupon|mixed|null
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_cupom($courseid) {
        global $DB, $SESSION;

        $couponkey = false;
        if (isset($SESSION->enrollment_coupon[3])) {
            $couponkey = $SESSION->enrollment_coupon;
        }
        $couponkey = optional_param('coupon', $couponkey, PARAM_TEXT);

        if (isset(self::$coupons[$couponkey . $courseid])) {
            return self::$coupons[$couponkey . $courseid];
        }

        if ($couponkey) {
            /** @var local_kopere_pay_coupon $coupon */
            $coupon = $DB->get_record("local_kopere_pay_coupon", ['uniquekey' => $couponkey, 'course' => $courseid]);
            if (!$coupon) {
                self::$error = get_string("order_coupon_not_found_html", "local_kopere_pay", $couponkey);

                $SESSION->enrollment_coupon = null;
                return null;
            }
            if ($coupon->amount <= 0) {
                self::$error = get_string("order_coupon_sold_out_html", "local_kopere_pay", $couponkey);

                $SESSION->enrollment_coupon = null;
                return null;
            }

            self::$coupons[$couponkey . $courseid] = $coupon;
            $SESSION->enrollment_coupon = $coupon->uniquekey;
            return $coupon;
        }

        $SESSION->enrollment_coupon = null;
        return null;
    }
}
