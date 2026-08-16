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
 * MeioGerencianetBoleto.php
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
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\error_message;
use local_kopere_pay\meios\util\EfiUtil;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\profile_field;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\send_event;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_history;
use local_kopere_pay\vo\local_kopere_pay_enrollment;

/**
 * Class MeioGerencianetBoleto
 *
 * @package local_kopere_pay\meios
 */
class MeioGerencianetBoleto implements IMeio {
    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        $habilitado = config::get_key("kopere_pay-habilitar-MeioGerencianetBoleto");
        if ($habilitado) {
            set_config("form_pedircpf", 1, "local_kopere_dashboard");
            set_config("form_pedir_celular", 1, "local_kopere_dashboard");
            set_config("form_pedirbirth", 1, "local_kopere_dashboard");
        }

        $publicname = "Boleto ou PIX";
        if ($descontoboleto = config::get_key("kopere_pay-gerencianet-desconto_boleto")) {
            $publicname = "Boleto ou PIX ({$descontoboleto}% OFF)";
        }

        return [
            "name" => "Boleto bancário e PIX pelo Efi",
            "public_name" => $publicname,
            "class" => "MeioGerencianetBoleto",
            "icon" => "MeioBoleto",
            "enable" => self::is_enable(),
            "mensalidade" => self::is_mensal(),
            "escolha" => true,
        ];
    }

    /**
     * Function is_enable
     *
     * @return bool
     */
    public static function is_enable() {
        if (config::get_key("kopere_pay-habilitar-MeioGerencianetBoleto")) {
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
        return false;
    }

    /**
     * Function details_edit_form
     *
     * @param form $form
     * @param \stdClass $koperepaydetalhe
     */
    public static function details_edit_form(Form $form, $koperepaydetalhe) {
    }

    /**
     * Function edit
     *
     * @param form $form
     *
     * @throws \coding_exception
     */
    public static function edit(form $form) {
        $form->return = message::info("Será habilitado pedido de CPF e Data de Nascimento por requisito obrigatório da Efi!");

        $form->add_input(
            input_text::new_instance()
                ->set_title("Client ID")
                ->set_value_by_config("kopere_pay-gerencianet-clientid")
                ->set_description(
                    "Crie uma integração em <a href='https://app.sejaefi.com.br/api/aplicacoes' target='_blank'>https://app.sejaefi.com.br/api/aplicacoes</a>"
                )
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title("Client Secret")
                ->set_value_by_config("kopere_pay-gerencianet-clientsecret")
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_type("number")
                ->set_title("Porcentagem de desconto para Boleto e PIX")
                ->set_value_by_config("kopere_pay-gerencianet-desconto_boleto")
                ->add_extras('min="0" max="20"')
                ->add_validator(input_base::VAL_INT)
                ->set_description(
                    "O cartão de crédito implica em uma taxa percentual.
                     Portanto, para incentivar o pagamento via boleto, estabeleça um desconto equivalente a
                     essa porcentagem como benefício para essa modalidade de quitação."
                )
        );

        $habilitado = config::get_key("kopere_pay-habilitar-MeioGerencianetBoleto");
        if ($habilitado) {
            set_config("form_pedircpf", $habilitado, "local_kopere_dashboard");
            set_config("form_pedir_celular", $habilitado, "local_kopere_dashboard");
            set_config("form_pedirbirth", $habilitado, "local_kopere_dashboard");
        }
    }

    /**
     * Function pay_button
     *
     * @param local_kopere_pay_detail $detalhe
     * @param \stdClass $course
     *
     * @return string
     */
    public static function pay_button($detalhe, $course) {
        global $CFG;

        $link = "{$CFG->wwwroot}/local/kopere_pay/?id={$detalhe->course}&meio=MeioGerencianetBoleto&enroll=1";

        $meio = self::get_name();
        $htmlReturn = "
                <div class='botao-meio'>";
        if ($descontoboleto = config::get_key("kopere_pay-gerencianet-desconto_boleto")) {
            $htmlReturn .= message::success(
                "Ao optar pelo pagamento via boleto, você usufrui de um desconto de {$descontoboleto}%."
            );
        }

        $htmlReturn .= "
                    <a href='{$link}' id='button_{$meio['class']}' target='_blank'
                       class='btn btn-success bt-submit botao'>Clique aqui para gerar o Boleto bancário</a>
                </div>";

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

        $id = optional_param("id", false, PARAM_TEXT);
        if (!$id) {
            die("no courseid");
        }

        require_login();

        $course = course_util::find($id);

        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $id]);
        $coupon = coupon_util::get_cupom($course->id);

        if ($descontoboleto = config::get_key("kopere_pay-gerencianet-desconto_boleto")) {
            if ($descontoboleto > 20) {
                $descontoboleto = 20;
            }

            $price = formater::price_to_float($detalhe->price);
            $detalhe->price = $price - ($price * ($descontoboleto / 100));
        }
        $price = coupon_util::get_preco($detalhe, $coupon);

        $enrollmentid = local_kopere_pay_enrollment::add_enrollment($USER->id, $id, "MeioGerencianetBoleto", $coupon, $price);

        $chargeBody = [
            "items" => [
                [
                    "name" => $course->fullname,
                    "amount" => 1,
                    "value" => (int) formater::only_number($price),
                ],
            ],
            "metadata" => [
                "custom_id" => "M-{$enrollmentid}",
                "notification_url" => "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=MeioGerencianetBoleto&enrollment={$enrollmentid}",
            ],
        ];
        $customer = [
            "name" => fullname($USER),
            "email" => $USER->email,
            "cpf" => formater::only_number(profile_field::get_user_profile_value($USER, 'cpf')),
            "birth" => preg_replace('/(\d+)\/(\d+)\/(\d+)/', '$3-$2-$1', profile_field::get_user_profile_value($USER, 'birth')),
            "phone_number" => formater::only_number($USER->phone2),
        ];

        try {
            $apiGerencianet = new EfiUtil(self::get_options());
            $charge = $apiGerencianet->createCharge($chargeBody);

            if ($charge["code"] == "200") {
                $params = [
                    "id" => $charge["data"]["charge_id"],
                ];
                $paymentBody["payment"]["banking_billet"] = [
                    "expire_at" => date("Y-m-d", time() + (5 * 24 * 60 * 60)),// 5 dias
                    "customer" => $customer,
                ];
                $payment = $apiGerencianet->payCharge($params, $paymentBody);

                $_SESSION["gerencianet"] = $payment;
                $_SESSION["enrollment_id"] = $enrollmentid;

                $send = [
                    "chargeBody" => $chargeBody,
                    "charge" => $charge,
                    "paymentBody" => $paymentBody,
                ];

                local_kopere_pay_history::add_history($enrollmentid, $send, $payment);

                header::location(
                    "{$CFG->wwwroot}/local/kopere_pay/?id={$id}&completed=1&meio=MeioGerencianetBoleto&enrollment={$enrollmentid}"
                );

            } else {
                local_kopere_pay_history::add_history($enrollmentid, $chargeBody, null, $charge);

                error_message::show("Código de erro do Efí: {$charge["code"]}");
            }
        } catch (\Exception $ex) {
            $err = ["error" => $ex->getMessage()];
            local_kopere_pay_history::add_history($enrollmentid, $chargeBody, "", $err);

            error_message::show($ex->getMessage());
        }
    }

    /**
     * Function completed
     *
     * @param $course
     * @param $user
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function completed($course, $user) {
        global $DB, $USER, $OUTPUT;

        $enrollmentid = optional_param("enrollment", 0, PARAM_INT);

        $_SESSION["gerencianet"] = null;
        $_SESSION["enrollment_id"] = null;

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record("local_kopere_pay_enrollment", ["id" => $enrollmentid, "userid" => $USER->id]);
        $koperepayhistoricos = $DB->get_records("local_kopere_pay_history", ["enrollmentid" => $enrollmentid], "id ASC");

        /** @var local_kopere_pay_history $koperepayhistorico */
        foreach ($koperepayhistoricos as $koperepayhistorico) {

            $receive = json_decode($koperepayhistorico->receive, true);

            if (isset($receive["code"])) {
                if ($receive["code"] == 200) {
                    $payment = $receive["data"];

                    $data = [
                        "expireat" => preg_replace('/(\d+)-(\d+)-(\d+)/', '$3/$2/$1', $payment["expire_at"]),
                        "charge_id" => $payment["charge_id"],
                        "pdf-url" => $payment["pdf"]["charge"],
                        "qrcode_image" => $payment["pix"]["qrcode_image"],
                        //"qrcode" => preg_replace('/./', '$0<wbr>', $payment["pix"]["qrcode"]),
                        "qrcode" => $payment["pix"]["qrcode"],
                    ];
                    echo $OUTPUT->render_from_template("local_kopere_pay/efi/completed", $data);
                } else {
                    $return .= message::danger("Código de erro do Efí: {$receive["code"]}");
                }
            }
        }
    }

    /**
     * Function returned
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function returned() {
        global $DB;

        $notification = optional_param("notification", false, PARAM_TEXT);

        if (!$notification) {
            die("Retorno Efi 1");
        }

        $enrollmentid = optional_param("enrollment", false, PARAM_INT);
        if (!$enrollmentid) {
            die("Retorno Efi 2");
        }

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record("local_kopere_pay_enrollment", ["id" => $enrollmentid]);

        $course = course_util::find($enrollment->course);

        $user = $DB->get_record("user", ["id" => $enrollment->userid]);
        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $enrollment->course]);

        try {

            // $params = ["token" => $notification];
            // $api = new Gerencianet(self::get_options());
            // $chargeNotification = $api->getNotification($params, []);

            $apiGerencianet = new EfiUtil(self::get_options());
            $chargeNotification = $apiGerencianet->getNotification($notification);

            $i = count($chargeNotification["data"]);
            $notification = $chargeNotification["data"][$i - 1];

            // $enrollmentid = (int)substr($notification["custom_id"], 1);
            $status = $notification["status"]["current"];

            local_kopere_pay_history::add_history($enrollmentid, null, ["data" => $notification]);

            if ($status == "paid") {
                echo "Matricula inserida";

                if ($course->isCoorte) {
                    enroll_util::cohort_enrol($enrollment->course, $user->id);
                } else {
                    $timeend = timeend_util::calculate_day($detalhe);
                    enroll_util::enrol($course, $user, time(), $timeend);
                }
                enrollment_util::changue_status($enrollment, enrollment_util::PAID);
                send_event::kopere_pay_pago($course, $user);

            } else if ($status == "refunded" || $status == "contested") {
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
            "client_id" => config::get_key("kopere_pay-gerencianet-clientid"),
            "client_secret" => config::get_key("kopere_pay-gerencianet-clientsecret"),
        ];

        return $options;
    }

    /**
     * Function get_status
     *
     * @param local_kopere_pay_history $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        if ($historico->error == null) {
            return "Erro na transação";
        }
        $receive = json_decode($historico->receive, true);

        if (isset($receive["modo"])) {
            return MeioManual::get_status($receive);
        }

        if (is_array($receive["data"]["status"])) {
            return EfiUtil::getStatus($receive["data"]["status"]["current"]);
        } else {
            return EfiUtil::getStatus($receive["data"]["status"]);
        }
    }

    /**
     * Function ajax
     *
     */
    public static function ajax() {
    }
}
