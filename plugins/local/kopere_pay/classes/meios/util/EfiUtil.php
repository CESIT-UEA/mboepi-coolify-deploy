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
 * EfiUtil.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios\util;

/**
 * Class EfiUtil
 *
 * @package local_kopere_pay\meios\util
 */
class EfiUtil {

    /** @var string */
    private $client_id;
    /** @var string */
    private $client_secret;
    /** @var object */
    private $token;

    /**
     * EfiUtil constructor.
     *
     * @param $options
     *
     * @throws \Exception
     */
    public function __construct($options) {
        $this->client_id = $options["client_id"];
        $this->client_secret = $options["client_secret"];

        $this->authorize();
    }

    /**
     * Function authorize
     *
     * @throws \Exception
     */
    private function authorize() {
        $ch = $this->curl_init();

        $authorization = base64_encode("{$this->client_id}:{$this->client_secret}");

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/authorize");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["grant_type" => "client_credentials"]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Basic {$authorization}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            new \Exception("cURL Error: {$error}");
        }

        $this->token = json_decode($response);
        if ($this->token == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }

        if (isset($this->token->error) && $this->token->error == "error") {
            throw new \Exception("EfíPay respondeu: " . htmlentities($this->token->error_description));
        }
    }

    /**
     * Function createCharge
     *
     * @param $chargeBody
     *
     * @return mixed
     * @throws \Exception
     */
    public function createCharge($chargeBody) {
        $ch = $this->curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/charge");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($chargeBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token->access_token}",
        ]);

        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $charge = json_decode($response, true);

        if ($charge == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($charge["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($charge["error_description"]));
        }
        if ($charge["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($charge["error_description"]));
        }

        return $charge;
    }

    /**
     * Function payCharge
     *
     * @param $params
     * @param $paymentBody
     *
     * @return mixed
     * @throws \Exception
     */
    public function payCharge($params, $paymentBody) {
        $ch = $this->curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/charge/{$params["id"]}/pay");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token->access_token}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $charge = json_decode($response, true);

        if ($charge == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($charge["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($charge["error_description"]));
        }

        if ($charge["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($charge["error_description"]));
        }

        return $charge;
    }

    /**
     * Function defineLinkPayMethod
     *
     * @param $params
     * @param $paymentBody
     *
     * @return mixed
     * @throws \Exception
     */
    public function defineLinkPayMethod($params, $paymentBody) {
        $ch = $this->curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/charge/{$params["id"]}/link");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token->access_token}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $charge = json_decode($response, true);

        if ($charge == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($charge["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($charge["error_description"]));
        }

        if ($charge["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($charge["error_description"]));
        }

        return $charge;
    }

    /**
     * Function getNotification
     *
     * @param $notification
     *
     * @return mixed
     * @throws \Exception
     */
    public function getNotification($notification) {
        $ch = $this->curl_init("GET");

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/notification/{$notification}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->token->access_token}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $notification = json_decode($response, true);

        if ($notification == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($notification["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($notification["error_description"]));
        }

        if ($notification["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($notification["error_description"]));
        }

        return $notification;
    }

    /**
     * Function listPlans
     *
     * @return mixed
     * @throws \Exception
     */
    public function listPlans() {
        $ch = $this->curl_init("GET");

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/plans?limit=100&offset=0");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->token->access_token}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $plans = json_decode($response, true);

        if ($plans == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($plans["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($plans["error_description"]));
        }

        if ($plans["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($plans["error_description"]));
        }

        return $plans;
    }

    /**
     * Function createPlan
     *
     * @param $planBody
     *
     * @return mixed
     * @throws \Exception
     */
    public function createPlan($planBody) {
        $ch = $this->curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/plan");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($planBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token->access_token}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $plan = json_decode($response, true);

        if ($plan == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($plan["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($plan["error_description"]));
        }

        if ($plan["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($plan["error_description"]));
        }

        return $plan;
    }

    /**
     * Function createOneStepLink
     *
     * @param $params
     * @param $chargeBody
     *
     * @return mixed
     * @throws \Exception
     */
    public function createOneStepLink($params, $chargeBody) {
        $ch = $this->curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://cobrancas.api.efipay.com.br/v1/charge/one-step/link?id={$params["id"]}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($chargeBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->token->access_token}",
        ]);
        $response = curl_exec($ch);

        if ($error = curl_error($ch)) {
            throw new \Exception("cURL Error: {$error}");
        }
        curl_close($ch);
        $link = json_decode($response, true);

        if ($link == null) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($response));
        }
        if (isset($link["error_description"])) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($link["error_description"]));
        }

        if ($link["code"] != 200) {
            throw new \Exception("EfíPay respondeu: " . htmlentities($link["error_description"]));
        }

        return $link;
    }

    /**
     * @param string $status
     *
     * @return string
     */
    public static function getStatus($status) {
        $code = [
            'new' => 'Cobrança gerada, aguardando definição da forma de pagamento',
            'waiting' => 'Forma de pagamento selecionada, aguardando a confirmação do pagamento.',
            'paid' => 'Pagamento confirmado',
            'unpaid' => 'Não foi possível confirmar o pagamento da cobrança',
            'refunded' => 'Pagamento devolvido pelo lojista ou pelo intermediador Efi',
            'contested' => 'Pagamento em processo de contestação',
            'canceled' => 'Cobrança cancelada pelo vendedor ou pelo pagador',
            'settled' => 'Cobrança foi confirmada manualmente através do painel Efi',
            //'link'      => 'Status aplicável a Link de Pagamento. Este status indica que trata-se de uma cobrança que está associada a um link de pagamento. O termo "link" equivale a "link".',
            //'expired'   => 'Status aplicável a Link de Pagamento. Um link de pagamento receberá este status ao atingir a data de vencimento definida no campo expire_at ao consumir o endpoint /charge/:id/link. Se o pagamento não foi realizado até esta data, a Efi enviará para sua URL de notificação a informação sobre o novo status da cobrança. O termo "expired" equivale a "expirado".',
        ];

        if (isset($code[$status])) {
            return $code[$status];
        }

        return $status;
    }

    /**
     * Function curl_init
     *
     * @param string $customrequest
     *
     * @return resource
     */
    private function curl_init($customrequest = "POST") {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customrequest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 150);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, "2");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 30000);

        return $ch;
    }

}