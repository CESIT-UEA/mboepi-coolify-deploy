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
 * resume.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\shopping;

use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class resume
 *
 * @package local_kopere_pay\shopping
 */
class resume {
    /**
     * Function create
     *
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return string
     * @throws \Exception
     */
    public function create($koperepaydetalhe) {
        global $DB, $PAGE, $OUTPUT;

        $data = [
            "value_original" => get_string("order_currency_brl", "local_kopere_pay", $koperepaydetalhe->price),
            "couponlength" => get_config("local_kopere_dashboard", "couponlength"),
            "coupon_error" => coupon_util::$error,
            "value_pagar" => get_string("order_currency_brl", "local_kopere_pay", coupon_util::get_preco($koperepaydetalhe)),
            "type_charge" => get_string(
                ($koperepaydetalhe->charge == "mensalidade") ? "order_total_monthly" : "order_total", "local_kopere_pay"
            ),
        ];

        $price = formater::price_to_float($koperepaydetalhe->price);
        if ($price > 1 &&
            $DB->get_record("local_kopere_pay_coupon", ["course" => $koperepaydetalhe->course], "*", IGNORE_MULTIPLE)) {
            $coupon = coupon_util::get_cupom($koperepaydetalhe->course);
            if ($coupon) {
                $data["coupon"] = $coupon->uniquekey;
                $data["value_total"] =
                    get_string("order_currency_brl", "local_kopere_pay", coupon_util::get_desconto($koperepaydetalhe, $coupon));
                $data["value_desconto"] =
                    get_string("order_currency_brl", "local_kopere_pay", coupon_util::get_desconto($koperepaydetalhe, $coupon));
                $data["text_desconto"] = get_string("order_coupon_discount", "local_kopere_pay", $coupon->uniquekey);
                $data["couponlength"] = get_config("local_kopere_dashboard", "couponlength");
            } else {
                $data["show_form_coupon"] = true;
            }
        }

        $offprice = get_config("local_kopere_dashboard", "builder_offprice_{$koperepaydetalhe->course}");
        if ($offprice) {
            $data["offprice"] = get_string("order_currency_brl", "local_kopere_pay", preg_replace('/[^0-9,\.]/', "", $offprice));
        }

        $PAGE->requires->js_call_amd("local_kopere_pay/coupon", "init", [$data["couponlength"]]);
        return $OUTPUT->render_from_template("local_kopere_pay/form-enrollment", $data);
    }
}
