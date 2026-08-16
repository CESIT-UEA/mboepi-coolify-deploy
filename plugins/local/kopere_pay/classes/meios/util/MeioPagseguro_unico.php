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
 * MeioPagseguro_unico.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios\util;

use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_pay\shopping\pay_header;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_history;
use local_kopere_pay\vo\local_kopere_pay_enrollment;

class MeioPagseguro_unico {

    /**
     * @param \stdClass $course
     * @param local_kopere_pay_detail $detalhe
     *
     * @throws \dml_exception
     * @throws \Exception
     */
    public static function enroll($course, $detalhe) {
        global $USER, $CFG, $iframe;

        $paymentmethods = [];
        if (config::get_key_int("kopere_pay-pagseguro-credit_card")) {
            $paymentmethods[] = ["type" => "credit_card"];
        }
        if (config::get_key_int("kopere_pay-pagseguro-debit_card")) {
            $paymentmethods[] = ["type" => "debit_card"];
        }
        if (config::get_key_int("kopere_pay-pagseguro-pix")) {
            $paymentmethods[] = ["type" => "PIX"];
        }
        if (config::get_key_int("kopere_pay-pagseguro-boleto")) {
            $paymentmethods[] = ["type" => "BOLETO"];
        }

        $coupon = coupon_util::get_cupom($course->id);
        $price = coupon_util::get_preco($detalhe, $coupon);

        $local_kopere_pay_rename_key = local_kopere_pay_enrollment::add_enrollment($USER->id, $course->id, 'MeioPagseguro', $coupon, $price);

        $data = [
            "reference_id" => "M-{$local_kopere_pay_rename_key}",
            "expiration_date" => date("Y-m-d\T23:59:00-00:00", time() + 60 * 60 * 24 * 3),
            "customer" => [
                "name" => fullname($USER),
                "email" => $USER->email,
            ],
            "customer_modifiable" => true,
            "items" => [
                [
                    "reference_id" => $course->id,
                    "name" => $course->fullname,
                    "quantity" => 1,
                    "unit_amount" => formater::only_number($price),
                ],
            ],
            "additional_amount" => 0,
            "discount_amount" => 0,
            "payment_methods" => [],
            "soft_descriptor" => config::get_key('kopere_pay-pagseguro-soft_descriptor'),
            "redirect_url" => "{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&completed=1&meio=MeioPagseguro&matricula={$local_kopere_pay_rename_key}",
            "return_url" => "{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&completed=1&meio=MeioPagseguro&matricula={$local_kopere_pay_rename_key}",
            "notification_urls" => [
                "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=ps",
            ],
        ];

        if (config::get_key_int('kopere_pay-pagseguro-credit_card')) {
            $data['payment_methods'][] = ["type" => "credit_card"];
        }
        if (config::get_key_int('kopere_pay-pagseguro-debit_card')) {
            $data['payment_methods'][] = ["type" => "debit_card"];
        }
        if (config::get_key_int('kopere_pay-pagseguro-pix')) {
            $data['payment_methods'][] = ["type" => "PIX"];
        }
        if (config::get_key_int('kopere_pay-pagseguro-boleto')) {
            $data['payment_methods'][] = ["type" => "BOLETO"];
        }
        if (!isset($data['payment_methods'][0])) {
            $data['payment_methods'][] = ["type" => "credit_card"];
        }

        $ch = curl_init();
        if (config::get_key_int("kopere_pay-pagseguro-sandbox")) {
            curl_setopt($ch, CURLOPT_URL, 'https://sandbox.api.pagseguro.com/checkouts');
            $headers = [
                "Authorization: Bearer " . config::get_key('kopere_pay-pagseguro-token-sandbox'),
                'Content-Type: application/json',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($ch, CURLOPT_URL, 'https://api.pagseguro.com/checkouts');
            $headers = [
                "Authorization: Bearer " . config::get_key('kopere_pay-pagseguro-token'),
                'Content-Type: application/json',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            pay_header::notfound(
                '<h2>PagSeguro respondeu com error:</h2>' . str_replace("\n", "<br>", htmlentities(curl_error($ch)))
            );
        }
        curl_close($ch);

        $payment = json_decode($result);

        if (isset($payment->error_messages)) {
            local_kopere_pay_history::add_history($local_kopere_pay_rename_key, $data, null, $payment);
            redirect(
                "/local/kopere_pay/?id={$course->id}&pagar=1&iframe={$iframe}",
                "Pagseguro respondeu: <strong>{$payment->error_messages[0]->description}</strong>", null,
                \core\output\notification::NOTIFY_ERROR
            );
        } else {
            $href = "";
            foreach ($payment->links as $link) {
                if ($link->rel == "PAY") {
                    $href = $link->href;
                }
            }

            local_kopere_pay_history::add_history($local_kopere_pay_rename_key, $data, $payment);

            if (isset($href[12])) {
                header::location($href);
            } else {
                pay_header::notfound("URL retornada pelo PagSeguro está errada!");
            }
        }
    }
}