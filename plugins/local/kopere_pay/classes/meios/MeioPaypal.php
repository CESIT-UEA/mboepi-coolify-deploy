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
 * MeioPaypal.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\util\button_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payee;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Api\Webhook;
use PayPal\Api\WebhookEventType;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

/**
 * Class MeioPaypal
 */
class MeioPaypal implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        return [
            'name' => 'PayPal',
            'public_name' => 'PayPal',
            'class' => 'MeioPaypal',
            'enable' => self::is_enable(),
            'mensalidade' => self::is_mensal(),
            'escolha' => false,
        ];
    }

    /**
     * Function is_enable
     *
     * @return bool
     */
    public static function is_enable() {
        if (config::get_key("kopere_pay-habilitar-MeioPaypal")) {
            return true;
        }

        return false;
    }

    /**
     * Function is_mensal
     *
     * @return false
     */
    public static function is_mensal() {
        return false;
    }

    /**
     * @param form $form
     * @param \stdClass $koperepaydetalhe
     */
    public static function details_edit_form(Form $form, $koperepaydetalhe) {

    }

    /**
     * @param form $form
     *
     * @throws \Exception
     */
    public static function edit(form $form) {
        global $CFG;

        $baseUrl = "{$CFG->wwwroot}/local/kopere_pay/assets/help/paypal";
        $return .= message::info(
            "Os dados abaixo você encontra em
                    <a href='https://developer.paypal.com/developer/applications/' target='_blank'>My apps & credentials</a>!<br><br>
                    Clique em <a href='{$baseUrl}/create-app-01.png' target='_blank'>Live >> Crete App</a>
                    depois defina um nome em <a href='{$baseUrl}/create-app-02.png' target='_blank'>\"App Name\" e marque \"Merchant\"</a>
                    e clique em Create App."
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title('Client ID')
                ->set_value_by_config('kopere_pay-paypal-clientid')
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title('Secret')
                ->set_value_by_config('kopere_pay-paypal-secret')
                ->set_required()
        );

        $urlWebhook = "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=MeioPaypal";
        if (config::get_key('kopere_pay-paypal-secret')) {
            try {
                $credencial = self::getCredential();
                $output = Webhook::getAll($credencial);
                $achou = false;
                foreach ($output->toArray()['webhooks'] as $webhook) {
                    if ($webhook['url'] == $urlWebhook) {
                        $achou = $webhook;
                    }
                }

                if ($achou) {
                    $clientid = config::get_key('kopere_pay-paypal-clientid');
                    $linkDashboard = "https://developer.paypal.com/developer/applications/edit/" . base64_encode($clientid);
                    echo "Webhook ID: <a href='{$linkDashboard}#webhookListLive' target='_blank'>{$achou['id']}</a>";
                } else {
                    $webhook = new Webhook();
                    $webhook->setUrl($urlWebhook);
                    $webhook->setEventTypes([
                        new WebhookEventType(
                            '{"name":"*","description":"ALL"}'
                        ),
                    ]);

                    $webhook->create($credencial);

                    header::location("?classname=payment_method&method=edit&meio=MeioPaypal");
                }
            } catch (\Exception $ex) {
                echo $ex->getMessage();
            }
        }
    }

    /**
     * @param local_kopere_pay_detail $detalhe
     * @param                    $course
     *
     * @return string
     * @throws \Exception
     */
    public static function pay_button($detalhe, $course) {
        global $CFG;

        if ($detalhe->charge == 'mensalidade') {

        } else {
            $link = "{$CFG->wwwroot}/local/kopere_pay/r.php?id={$detalhe->course}&meio=MeioPaypal&enroll=1";
            return button_util::link($detalhe, self::get_name(), $link);
        }

        return '';
    }

    /**
     * @param     $course
     * @param     $user
     * @param int $enrollmentid
     */
    public static function completed($course, $user) {

        $enrollmentid = optional_param('matricula', 0, PARAM_INT);
    }

    /**
     * @param $course
     * @return mixed
     */
    public static function enroll($course) {
        require_once(__DIR__ . '/paypal/vendor/autoload.php');
    }

    /**
     * Function returned
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function returned() {
        global $DB;

        $enroll = optional_param('enroll', false, PARAM_INT);

        if ($enroll) {
            $id = optional_param('id', false, PARAM_TEXT);
            $course = course_util::find($id);

            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record('kopere_pay_detail', ['course' => $id]);

            if ($detalhe->charge == 'mensalidade') {
                self::requisicao_mensal($course, $detalhe);
            } else {
                self::requisicao_unico($course, $detalhe);
            }
        }
    }

    /**
     * @param $course
     * @param local_kopere_pay_detail $detalhe
     * @throws \Exception
     */
    private static function requisicao_unico($course, $detalhe) {
        global $DB, $USER;

        $local_kopere_pay_rename_key = optional_param('matricula', 0, PARAM_INT);

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $local_kopere_pay_rename_key]);

        $item = (new Item())
            ->setSku($course->id)
            ->setName($course->fullname)
            ->setCurrency('BRL')
            ->setQuantity(1)
            ->setPrice(formater::price_to_float($detalhe->price));

        coupon_util::get_preco($detalhe, null, false);
        //        if (coupon_util::$desconto) {
        //            $coupon = coupon_util::get_cupom($detalhe->course);
        //            $itemDesconto = (new Item())
        //                ->setName(get_string("order_coupon_discount", "local_kopere_pay", $coupon->uniquekey))
        //                ->setCurrency('BRL')
        //                ->setQuantity(1)
        //                ->setPrice(coupon_util::$desconto * -1);
        // $itemList = (new ItemList())->setItems([$item, $itemDesconto]);
        //        }else{
        $itemList = (new ItemList())->setItems([$item]);
        //}

        $amount = (new Amount())
            ->setCurrency('BRL')
            ->setTotal(formater::price_to_float($detalhe->price));

        $payee = (new Payee())->setEmail($USER->email);

        $transaction = (new Transaction())
            ->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Payment description")
            ->setInvoiceNumber("M-{$enrollment->id}")
            ->setPayee($payee);

        $payment = new Payment();
        $payment->setIntent("order")
            ->setPayer((new Payer())->setPaymentMethod("paypal"))
            ->setTransactions([$transaction]);

        try {
            $payment->create(self::getCredential());
        } catch (\Exception $ex) {
        }

        $approvalUrl = $payment->getApprovalLink();
    }

    /**
     * @param                    $course
     * @param local_kopere_pay_detail $detalhe
     *
     * @throws \Exception
     */
    private static function requisicao_mensal($course, $detalhe) {

    }

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     */
    public static function get_status($historico) {
        if ($historico == null) {
            return get_string("paypal_no_history", "local_kopere_pay");
        }

        if (strlen($historico->error) > 10) {
            return $historico->error;
        }
        $receive = json_decode($historico->receive, true);

        if ($receive['PAYMENTINFO_0_PAYMENTSTATUS'] == "start") {
            return get_string("paypal_payment_sent", "local_kopere_pay");
        }

        return $receive['PAYMENTINFO_0_PAYMENTSTATUS'];
    }

    /**
     * Function urlToVars
     *
     * @param $response
     * @return array
     */
    private static function urlToVars($response) {
        $responseNvp = [];
        if (preg_match_all('/(?<name>[^\=]+)\=(?<value>[^&]+)&?/', $response, $matches)) {
            foreach ($matches['name'] as $offset => $name) {
                $responseNvp[$name] = $matches["value"][$offset];
            }
        }

        return $responseNvp;
    }

    /**
     * Function getCredential
     *
     * @return \PayPal\Rest\ApiContext
     */
    private static function getCredential() {
        $apiContext = new ApiContext(
            new OAuthTokenCredential(
                config::get_key('kopere_pay-paypal-clientid'),     // ClientID
                config::get_key('kopere_pay-paypal-secret')      // ClientSecret
            )
        );
        $apiContext->setConfig(['mode' => 'live']);

        return $apiContext;
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {

    }
}