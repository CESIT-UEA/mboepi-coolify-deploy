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
 * MeioPagseguro_mensalidade.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios\util;

use local_kopere_dashboard\util\config;
use local_kopere_pay\shopping\pay_header;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\profile_field;
use local_kopere_pay\validate\phone;

/**
 * Class MeioPagseguro_mensalidade
 *
 * @package local_kopere_pay\meios\util
 */
class MeioPagseguro_mensalidade {
    /**
     * Function enroll
     *
     * @param $course
     * @param $detalhe
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function enroll($course, $detalhe) {

        // https://dev.pagbank.uol.com.br/v2.0/reference/criar-plano
        // https://dev.pagbank.uol.com.br/v2.0/reference/criar-assinante
        // https://dev.pagbank.uol.com.br/v2.0/reference/criar-assinatura

        $plan = self::getAndCreatePlan($course, $detalhe);
        $assinante = self::getAndCreateAssinante();
    }

    /**
     * Function getAndCreatePlan
     *
     * @param $course
     * @param $detalhe
     *
     * @return mixed
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function getAndCreatePlan($course, $detalhe) {
        $ch = curl_init();
        self::curl_init($ch, 'plans?offset=0&limit=1000');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        $plans = json_decode($result);

        foreach ($plans->plans as $plan) {
            if ($plan->reference_id == "curso-{$detalhe->id}") {
                if ($plan->amount->value != formater::only_number($detalhe->price)) {
                    $plan = self::createPlan($course, $detalhe, $plan->id);
                } else if ($plan->billing_cycles != $detalhe->days) {
                    $plan = self::createPlan($course, $detalhe, $plan->id);
                } else if ($plan->name != $course->fullname) {
                    $plan = self::createPlan($course, $detalhe, $plan->id);
                }

                return $plan;
            }
        }

        return self::createPlan($course, $detalhe);
    }

    /**
     * Function createPlan
     *
     * @param $course
     * @param $detalhe
     * @param null $updateId
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function createPlan($course, $detalhe, $updateId = null) {
        $ch = curl_init();

        $paymentmethods = [];
        if (config::get_key_int("kopere_pay-pagseguro-credit_card")) {
            $paymentmethods[] = "CREDIT_CARD";
        }
        //if (config::get_key_int("kopere_pay-pagseguro-boleto")) {
        //    $paymentmethods[] = "BOLETO";
        //}

        if ($detalhe->days < 3) {
            $detalhe->days = 3;
        }
        $planInfo = (object) [
            "amount" => (object) [
                "currency" => "BRL",
                "value" => formater::only_number($detalhe->price),
            ],
            "interval" => (object) [
                "unit" => "MONTH",
                "length" => 1,
            ],
            "payment_method" => $paymentmethods,
            "reference_id" => "curso-{$detalhe->id}",
            "name" => $course->fullname,
            "billing_cycles" => $detalhe->days,
        ];

        if ($updateId) {
            self::curl_init($ch, "plans/{$updateId}");
        } else {
            self::curl_init($ch, 'plans');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($planInfo));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            pay_header::notfound(
                '<h2>PagseguroMensalidade respondeu com error:</h2>' . str_replace("\n", "<br>", htmlentities(curl_error($ch)))
            );
        }
        curl_close($ch);

        return json_decode($result);
    }

    /**
     * Function getAndCreateAssinante
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function getAndCreateAssinante() {
        global $USER;

        $ch = curl_init();
        self::curl_init($ch, 'customers?offset=0&limit=1000');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);

        $customers = json_decode($result);

        foreach ($customers->customers as $customer) {
            if ($customer->tax_id == formater::only_number(profile_field::get_user_profile_value($USER, 'cpf'))) {
                return $customer;
            }
            if ($customer->email == $USER->email) {
                return $customer;
            }
        }

        return self::createAssinante();
    }

    /**
     * Function createAssinante
     *
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function createAssinante() {
        global $USER;

        $USER->phone2 = "(48)984968854";
        $USER->profile['cpf'] = "039.537.549-55";
        $USER->profile['birth'] = "17/05/1983";

        $area = "";
        $number = "";
        if (phone::validate_celphone($USER->phone2) || phone::validate_fixedphone($USER->phone2)) {
            $area = substr(formater::only_number($USER->phone2), 0, 2);
            $number = substr(formater::only_number($USER->phone2), 2);
        } else if (phone::validate_celphone($USER->phone1) || phone::validate_fixedphone($USER->phone1)) {
            $area = substr(formater::only_number($USER->phone1), 0, 2);
            $number = substr(formater::only_number($USER->phone1), 2);
        }

        $userInfo = (object) [
            "name" => substr(fullname($USER), 0, 100),
            "email" => $USER->email,
            "phones" => [
                (object) [
                    "country" => "55",
                    "area" => $area,
                    "number" => $number,
                ],
            ],
            "tax_id" => formater::only_number(profile_field::get_user_profile_value($USER, 'cpf')),
            "birth_date" => preg_replace('/(\d+)\/(\d+)\/(\d+)/', '$3-$2-$1', profile_field::get_user_profile_value($USER, 'birth')),
        ];

        $ch = curl_init();
        self::curl_init($ch, 'customers');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userInfo));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            pay_header::notfound(
                '<h2>PagseguroMensalidade respondeu com error:</h2>' . str_replace("\n", "<br>", htmlentities(curl_error($ch)))
            );
        }
        curl_close($ch);

        return json_decode($result);
    }

    /**
     * Function curl_init
     *
     * @param $ch
     * @param $pathurl
     */
    private static function curl_init($ch, $pathurl) {
        if (config::get_key_int("kopere_pay-pagseguro-sandbox")) {
            curl_setopt($ch, CURLOPT_URL, "https://sandbox.api.assinaturas.pagseguro.com/{$pathurl}");
            $headers = [
                "Authorization: Bearer " . config::get_key('kopere_pay-pagseguro-token-sandbox'),
                'Content-Type: application/json',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://api.assinaturas.pagseguro.com/{$pathurl}");
            $headers = [
                "Authorization: Bearer " . config::get_key('kopere_pay-pagseguro-token'),
                'Content-Type: application/json',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
}
