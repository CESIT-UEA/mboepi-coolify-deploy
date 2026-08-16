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
 * MeioAsaas_util.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios\util;

use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\profile_field;
use local_kopere_pay\validate\cpf;

/**
 * Class MeioAsaas_util
 */
class MeioAsaas_util {
    /**
     * Function get_and_create_cliente
     *
     * @param $course
     * @return mixed
     * @throws \Exception
     */
    public static function get_and_create_cliente($course) {
        global $USER, $CFG, $iframe;

        $cpf = formater::only_number(profile_field::get_user_profile_value($USER, 'cpf'));
        if (!cpf::validate($cpf)) {
            header::location("{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&iframe={$iframe}");
        }
        $ch = MeioAsaas_util::curl_init("customers?cpfCnpj={$cpf}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            header::notfound('<h2>Asaas respondeu com error:</h2>' . str_replace("\n", "<br>", htmlentities(curl_error($ch))));
        }
        curl_close($ch);

        $clientes = json_decode($result);
        if (isset($clientes->data)) {
            foreach ($clientes->data as $cliente) {
                if ($cliente->cpfCnpj == $cpf) {
                    return $cliente->id;
                }
            }
        }

        $customer = (object) [
            "name" => fullname($USER),
            "cpfCnpj" => formater::only_number(profile_field::get_user_profile_value($USER, 'cpf')),
            "email" => $USER->email,
        ];
        $ch = MeioAsaas_util::curl_init("customers");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customer));
        $result = curl_exec($ch);

        $customer = json_decode($result);

        if (isset($customer->errors)) {
            header::notfound($customer->errors[0]->description);
        }

        return $customer->id;
    }

    /**
     * Function billing_type
     *
     * @return string
     * @throws \coding_exception
     */
    public static function billing_type($default) {
        $billingtype = optional_param('billing_type', "", PARAM_TEXT);
        switch ($billingtype) {
            case 'BOLETO':
                return 'BOLETO';
            case 'PIX':
                return 'PIX';
        }

        return $default;
    }

    /**
     * Function webhooks
     *
     * @return bool|string
     */
    public static function webhooks() {
        global $CFG;

        $admin = get_admin();

        $webhook = (object) [
            "apiVersion" => "3",
            "enabled" => true,
            "interrupted" => false,
            "url" => "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=MeioAsaas",
            "email" => $admin->email,
            "authToken" => md5($admin->email),
        ];

        $ch = self::curl_init("webhook");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook));
        return curl_exec($ch);
    }

    /**
     * @param $path
     *
     * @return \CurlHandle
     */
    public static function curl_init($path) {
        global $CFG;

        $ch = curl_init();

        if (config::get_key_int("kopere_pay-meioasaas-sandbox")) {
            curl_setopt($ch, CURLOPT_URL, "https://sandbox.asaas.com/api/v3/{$path}");
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://api.asaas.com/v3/{$path}");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Kopere Pay {$CFG->release}");
        curl_setopt($ch, CURLOPT_REFERER, "{$CFG->wwwroot}/local/kopere_pay/?" . http_build_query($_GET));
        $headers = [
            'Accept: application/json',
            'content-type: application/json',
            'Access_token: ' . config::get_key('kopere_pay-meioasaas-apikey'),
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    /**
     * Function get_payment_method_event
     *
     * @param $event
     * @return string
     */
    public static function get_payment_method_event($event) {
        $events = [
            'PAYMENT_CREATED' => 'Cobrança criada e enviada ao cliente.',
            'PAYMENT_AWAITING_RISK_ANALYSIS' => 'Pagamento em cartão aguardando aprovação pela análise manual de risco.',
            'PAYMENT_APPROVED_BY_RISK_ANALYSIS' => 'Pagamento em cartão aprovado pela análise manual de risco.',
            'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => 'Pagamento em cartão reprovado pela análise manual de risco.',
            'PAYMENT_AUTHORIZED' => 'Pagamento em cartão que foi autorizado e precisa ser capturado.',
            'PAYMENT_UPDATED' => 'Alteração no vencimento ou valor de cobrança existente.',
            'PAYMENT_CONFIRMED' => 'Cobrança confirmada (pagamento efetuado, porém o saldo ainda não foi disponibilizado).',
            'PAYMENT_RECEIVED' => 'Cobrança recebida.',
            'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED' => 'Falha no pagamento de cartão de crédito',
            'PAYMENT_ANTICIPATED' => 'Cobrança antecipada.',
            'PAYMENT_OVERDUE' => 'Cobrança vencida.',
            'PAYMENT_DELETED' => 'Cobrança removida.',
            'PAYMENT_RESTORED' => 'Cobrança restaurada.',
            'PAYMENT_REFUNDED' => 'Cobrança estornada.',
            'PAYMENT_REFUND_IN_PROGRESS' => 'Estorno em processamento (liquidação já está agendada, cobrança será estornada após executar a liquidação).',
            'PAYMENT_RECEIVED_IN_CASH_UNDONE' => 'Recebimento em dinheiro desfeito.',
            'PAYMENT_CHARGEBACK_REQUESTED' => 'Recebido chargeback.',
            'PAYMENT_CHARGEBACK_DISPUTE' => 'Em disputa de chargeback (caso sejam apresentados documentos para contestação).',
            'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => 'Disputa vencida, aguardando repasse da adquirente.',
            'PAYMENT_DUNNING_RECEIVED' => 'Recebimento de negativação.',
            'PAYMENT_DUNNING_REQUESTED' => 'Requisição de negativação.',
            'PAYMENT_BANK_SLIP_VIEWED' => 'Boleto da cobrança visualizado pelo cliente.',
            'PAYMENT_CHECKOUT_VIEWED' => 'Fatura da cobrança visualizada pelo cliente.',
        ];

        if (isset($events[$event])) {
            return $events[$event];
        } else {
            return "--";
        }
    }
}
