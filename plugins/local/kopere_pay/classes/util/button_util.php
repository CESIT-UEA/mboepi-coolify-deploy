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
 * button_util.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use Exception;
use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class button_util
 *
 * Helper for rendering the payment method button.
 */
class button_util {
    /**
     * Creates a payment form button for a payment method.
     *
     * @param local_kopere_pay_detail $detalhe
     * @param array $meio
     * @param string $link
     * @param string $extra
     * @param string $imput
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     * @throws dml_exception
     */
    public static function link($detalhe, $meio, $link, $extra = "", $imput = "") {
        global $OUTPUT;

        if ($coupon = coupon_util::get_cupom($detalhe->course)) {
            $link = "{$link}&coupon={$coupon->uniquekey}";
        }

        if ($detalhe->charge === "mensalidade") {
            $buttontext = get_string("button_subscribe_with_method", "local_kopere_pay", $meio["public_name"]);
        } else {
            $buttontext = get_string("button_pay_with_method", "local_kopere_pay", $meio["public_name"]);
        }

        $mustachedata = [
            "action" => $link,
            "methodclass" => $meio["class"],
            "buttontext" => $buttontext,
            "extra" => $extra,
            "inputs" => $imput,
        ];
        return $OUTPUT->render_from_template("local_kopere_pay/util/payment_button", $mustachedata);
    }
}
