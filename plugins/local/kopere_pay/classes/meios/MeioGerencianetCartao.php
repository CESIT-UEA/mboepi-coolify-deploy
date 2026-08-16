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
 * MeioGerencianetCartao.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_base;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\message;
use local_kopere_pay\error_message;
use local_kopere_pay\meios\util\EfiUtil;
use local_kopere_pay\util\completed_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\send_event;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 * Class MeioGerencianetCartao
 *
 * @package local_kopere_pay\meios
 */
class MeioGerencianetCartao implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        $habilitado = config::get_key("kopere_pay-habilitar-MeioGerencianetCartao");
        if ($habilitado) {
            // set_config('form_pedircpf', 1, "local_kopere_dashboard");
            // set_config('form_pedir_celular', 1, "local_kopere_dashboard");
            // set_config('form_pedirbirth', 1, "local_kopere_dashboard");
        }

        return [
            'name' => 'Cartão de crédito pelo Efi',
            'public_name' => 'Cartão de crédito',
            'class' => 'MeioGerencianetCartao',
            'icon' => 'MeioCartao',
            'enable' => self::is_enable(),
            'mensalidade' => self::is_mensal(),
            'escolha' => true,
        ];
    }

    /**
     * Function is_enable
     *
     * @return bool
     */
    public static function is_enable() {
        if (config::get_key("kopere_pay-habilitar-MeioGerencianetCartao")) {
            return true;
        }

        return false;
    }

    /**
     * Function is_mensal
     *
     * @return bool
     */
    public static function is_mensal() {
        return true;
    }

    /**
     * Function details_edit_form
     *
     * @param form $form
     * @param $koperepaydetalhe
     *
     * @return mixed|void
     */
    public static function details_edit_form(Form $form, $koperepaydetalhe) {
    }

    /**
     * Function edit
     *
     * @param form $form
     *
     * @return mixed|void
     * @throws \coding_exception
     */
    public static function edit(form $form) {
        $form->return .= message::info("Será habilitado pedido de CPF e Data de Nascimento por requisito obrigatório da Efi!");

        $form->add_input(
            input_text::new_instance()
                ->set_title('Client ID')
                ->set_value_by_config('kopere_pay-gerencianet-clientid')
                ->set_description(
                    "Crie uma integração em <a href='https://app.sejaefi.com.br/api/aplicacoes' target='_blank'>https://app.sejaefi.com.br/api/aplicacoes</a>"
                )
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title('Client Secret')
                ->set_value_by_config('kopere_pay-gerencianet-clientsecret')
                ->set_required()
        );

        if (config::get_key_int('form_monthly_fee')) {
            $form->add_input(
                input_text::new_instance()
                    ->set_type("number")
                    ->set_title('Quantidade de dias de testes em mensalidade')
                    ->set_value_by_config('kopere_pay-gerencianet-trial_days')
                    ->add_validator(input_base::VAL_INT)
                    ->set_description(
                        "Quantidade de dias para teste gratuito do plano de assinatura.
                         Atributo disponível somente quando o pagamento for mensalidade com Cartão!"
                    )
                    ->set_required()
            );
        }

        $habilitado = config::get_key("kopere_pay-habilitar-MeioGerencianetCartao");
        if ($habilitado) {
            set_config('form_pedircpf', $habilitado, "local_kopere_dashboard");
            set_config('form_pedir_celular', $habilitado, "local_kopere_dashboard");
            set_config('form_pedirbirth', $habilitado, "local_kopere_dashboard");
        }
    }

    /**
     * Function pay_button
     *
     * @param $detalhe
     * @param $course
     *
     * @return string
     */
    public static function pay_button($detalhe, $course) {
        global $CFG;

        $link = "{$CFG->wwwroot}/local/kopere_pay/?id={$detalhe->course}&meio=MeioGerencianetCartao&enroll=1";

        $meio = self::get_name();
        if ($detalhe->charge == 'mensalidade') {
            $htmlReturn = "
                <div class='botao-meio'>
                    <a href='{$link}' id='button_{$meio['class']}' target='_blank'
                       class='btn btn-success bt-submit botao'>Clique aqui para assinar utilizando o Cartão de Crédito.</a>";
            if ($trialdays = config::get_key('kopere_pay-gerencianet-trial_days')) {
                $htmlReturn .= message::success("Teste grátis por {$trialdays} dias sem custo. Cancele quando quiser!");
            }
        } else {
            $htmlReturn = "
                <div class='botao-meio'>
                    <a href='{$link}' id='button_{$meio['class']}' target='_blank'
                       class='btn btn-success bt-submit botao'>Clique aqui para efetuar o pagamento utilizando o Cartão de Crédito.</a>";
        }

        $htmlReturn .= "</div>";

        return $htmlReturn;
    }

    /**
     * Function enroll
     *
     * @param $course
     *
     * @return mixed|void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function enroll($course) {
        global $DB, $USER, $CFG;

        $id = optional_param('id', false, PARAM_TEXT);
        if (!$id) {
            die("no courseid");
        }

        require_login();

        $course = course_util::find($id);

        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id]);
        $coupon = coupon_util::get_cupom($course->id);

        $price = coupon_util::get_preco($detalhe, $coupon);

        $enrollmentid = local_kopere_pay_enrollment::add_enrollment($USER->id, $id, 'MeioGerencianetCartao', $coupon, $price);

        if ($detalhe->charge == 'mensalidade') {
            try {
                $params = [
                    "id" => self::getPlano($detalhe->days),
                ];

                $chargeBody = [
                    "items" => [
                        [
                            'name' => $course->fullname,
                            'amount' => 1,
                            "value" => (int) formater::only_number($price),
                        ],
                    ],
                    "metadata" => [
                        'custom_id' => "M-{$enrollmentid}",
                        'notification_url' => "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=MeioGerencianetCartao&enrollment={$enrollmentid}",
                    ],
                    "settings" => [
                        'expire_at' => date('Y-m-d', time() + (1 * 24 * 60 * 60)),
                        "payment_method" => "credit_card",
                        "request_delivery_address" => false,
                    ],
                ];

                $apiGerencianet = new EfiUtil(self::get_options());
                $payment = $apiGerencianet->createOneStepLink($params, $chargeBody);

                $chargeBody['subscription_id'] = $payment['data']['subscription_id'];
                local_kopere_pay_history::add_history($enrollmentid, $chargeBody, $payment);

                header("Location: {$payment['data']['payment_url']}");
                die();

            } catch (\Exception $ex) {

                $err = ['error' => $ex->getMessage()];
                local_kopere_pay_history::add_history($enrollmentid, $chargeBody, "", $err);

                error_message::show($ex->getMessage());
            }

        } else {
            $chargeBody = [
                'items' => [
                    [
                        'name' => $course->fullname,
                        'amount' => 1,
                        "value" => (int) formater::only_number($price),
                    ],
                ],
                'metadata' => [
                    'custom_id' => "M-{$enrollmentid}",
                    'notification_url' => "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=MeioGerencianetCartao&enrollment={$enrollmentid}",
                ],
            ];

            try {
                // https://www.prepbootstrap.com/bootstrap-template/credit-card-payment
                //$api = new EfiPay(self::get_options());
                //$charge = $api->createCharge([], $chargeBody);

                $apiGerencianet = new EfiUtil(self::get_options());
                $charge = $apiGerencianet->createCharge($chargeBody);

                $params = [
                    'id' => $charge['data']['charge_id'],
                ];
                $body = [
                    'expire_at' => date('Y-m-d', time() + (5 * 24 * 60 * 60)),
                    'request_delivery_address' => false,
                    'payment_method' => 'credit_card',
                ];

                $payment = $apiGerencianet->defineLinkPayMethod($params, $body);

                $chargeBody['charge_id'] = $charge['data']['charge_id'];
                local_kopere_pay_history::add_history($enrollmentid, $chargeBody, $payment);

                header("Location: {$payment['data']['payment_url']}");
                die();

            } catch (\Exception $ex) {
                $err = ['error' => $ex->getMessage()];
                local_kopere_pay_history::add_history($enrollmentid, $chargeBody, "", $err);
                error_message::show($ex->getMessage());
            }
        }
    }

    /**
     * Function getPlano
     *
     * @param $repeats
     *
     * @return mixed
     * @throws \Exception
     */
    private static function getPlano($repeats) {
        $apiGerencianet = new EfiUtil(self::get_options());
        $response = $apiGerencianet->listPlans();

        foreach ($response['data'] as $plano) {
            if (strpos($plano['name'], "Assinatura mensal") === 0) {
                if ($plano['repeats'] == $repeats) {
                    return $plano['plan_id'];
                }
            }
        }

        $body = [
            "name" => "Assinatura mensal",
            "interval" => 1,
            "repeats" => (int) $repeats,
        ];
        $response = $apiGerencianet->createPlan($body);

        return $response['data']['plan_id'];
    }

    /**
     * Function completed
     *
     * @param $course
     * @param $user
     *
     * @return mixed|void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function completed($course, $user) {
        global $DB, $USER;

        $enrollmentid = optional_param('enrollment', 0, PARAM_INT);

        $_SESSION['gerencianet'] = null;
        $_SESSION['enrollment_id'] = null;

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid, 'userid' => $USER->id]);
        $koperepayhistoricos = $DB->get_records('local_kopere_pay_history', ['enrollmentid' => $enrollmentid], 'id ASC');

        /** @var local_kopere_pay_history $koperepayhistorico */
        foreach ($koperepayhistoricos as $koperepayhistorico) {

            $receive = json_decode($koperepayhistorico->receive, true);

            if ($receive['code'] == 200) {
                $payment = $receive['data'];
                echo "
                    <div class='row'>
                        <div class='col-lg-7 col-12 order-down text-center'>
                            <div class='kopere_dashboard-card well2'>
                                <h2>Pré-Matrícula realizada. Aguardando o pagamento.</h2>";

                if (isset($payment['link'])) {
                    $expireat = preg_replace('/(\d+)-(\d+)-(\d+)/', '$3/$2/$1', $payment['expire_at']);
                    echo "<p>Sua matrícula esta aguardando pagamento, faça o download do boleto no botão abaixo e
                             efetue o pagamento até o dia {$expireat}.</p>";

                    if (isset($payment['subscription_id'])) {
                        echo "<a target='_blank' class='link-pay' href='{$payment['link']}'>Acessar o primeiro boleto</a>";
                    } else {
                        echo "<a target='_blank' class='link-pay' href='{$payment['link']}'>Acessar o boleto</a>";
                    }
                }
                if ($payment['payment'] == 'credit_card') {
                    echo "<p>Sua matrícula está em fase de processamento.
                             Você será informado, quando confirmado, no e-mail cadastrado.</p>";
                }

                if (isset($payment['subscription_id'])) {
                    echo "<h3>Número da transação: <strong style='color:#EF5350'>{$payment['charge']['id']}</strong></h3>";
                } else {
                    echo "<h3>Número da transação: <strong style='color:#EF5350'>{$payment['charge_id']}</strong></h3>";
                }

                echo "      </div>
                        </div>
                        <div class='col-lg-5 col-12 order-up'>
                        " . completed_util::summary($course, $enrollment) . "
                    </div>
                </div>";
            }
        }
    }

    /**
     * Function returned
     *
     * @return mixed|void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function returned() {
        global $DB;

        $notification = optional_param('notification', false, PARAM_TEXT);

        if (!$notification) {
            die("Retorno Efi 1");
        }

        $enrollmentid = optional_param('enrollment', false, PARAM_INT);
        if (!$enrollmentid) {
            die("Retorno Efi 2");
        }

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid]);

        $course = course_util::find($enrollment->course);

        $user = $DB->get_record('user', ['id' => $enrollment->userid]);
        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $enrollment->course]);

        try {
            //$api = new EfiPay(self::get_options());
            //$params = ['token' => $notification];
            //$chargeNotification = $api->getNotification($params, []);

            $apiGerencianet = new EfiUtil(self::get_options());
            $chargeNotification = $apiGerencianet->getNotification($notification);

            $i = count($chargeNotification["data"]);
            $notification = $chargeNotification["data"][$i - 1];

            // $enrollmentid = (int)substr($notification['custom_id'], 1);
            $status = $notification['status']['current'];

            local_kopere_pay_history::add_history($enrollmentid, null, ['data' => $notification]);

            if ($status == 'paid') {
                echo "Matricula inserida";

                if ($course->isCoorte) {
                    enroll_util::cohort_enrol($enrollment->course, $user->id);
                } else {
                    $timeend = timeend_util::calculate_day($detalhe);
                    enroll_util::enrol($course, $user, time(), $timeend);
                }
                enrollment_util::changue_status($enrollment, enrollment_util::PAID);
                send_event::kopere_pay_pago($course, $user);

            } else if ($status == 'refunded' || $status == 'contested') {
                echo "Matricula removida";

                if ($course->isCoorte) {
                    enroll_util::cohort_unenrol($enrollment->course, $user->id);
                } else {
                    enroll_util::unenrol($course, $user);
                }
                enrollment_util::changue_status($enrollment, enrollment_util::WAITING);
                send_event::kopere_pay_refunded($course, $user);

            } else {
                echo "Matricula nada";
            }

        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
    }

    /**
     * Function get_options
     *
     * @return array
     */
    public static function get_options() {
        $options = [
            'client_id' => config::get_key('kopere_pay-gerencianet-clientid'),
            'client_secret' => config::get_key('kopere_pay-gerencianet-clientsecret'),
        ];

        return $options;
    }

    /**
     * Function get_status
     *
     * @param $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        if ($historico->error == null) {
            return "Erro na transação";
        }
        $receive = json_decode($historico->receive, true);

        if (isset($receive['modo'])) {
            return MeioManual::get_status($receive);
        }

        if (is_array($receive['data']['status'])) {
            return EfiUtil::getStatus($receive['data']['status']['current']);
        } else {
            return EfiUtil::getStatus($receive['data']['status']);
        }
    }

    /**
     * Function ajax
     *
     * @return mixed|void
     */
    public static function ajax() {
    }
}
