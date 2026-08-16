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
 * MeioAsaasBoleto.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_checkbox_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\meios\util\MeioAsaas_util;
use local_kopere_pay\shopping\pay_header;
use local_kopere_pay\util\button_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\profile_field;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\send_event;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\validate\cpf;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 * Class MeioAsaasBoleto
 */
class MeioAsaasBoleto implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        $habilitado = config::get_key("kopere_pay-habilitar-MeioAsaasBoleto");
        if ($habilitado) {
            set_config("form_pedircpf", 1, "local_kopere_dashboard");
        }

        return [
            "name" => "Asaas - Boleto ou PIX",
            "public_name" => "Boleto ou PIX",
            "class" => "MeioAsaasBoleto",
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
        if (config::get_key("kopere_pay-habilitar-MeioAsaasBoleto")) {
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
     * @return void
     * @throws \Exception
     */
    public static function edit(form $form) {

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Ambiente em SandBox?")
                ->set_checked_by_config("kopere_pay-meioasaas-sandbox")
                ->set_description("Ao mudar está configuração, save e volta a está página!")
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title("Máximo de parcelas")
                ->set_value_by_config("kopere_pay-meioasaasboleto-installmentCount", 0)
                ->set_description(
                    "ASAAS permite Pix e Boleto parcelado. Defina a quantidade máxima de parcelas que o aluno poderá pagar!<br>0 é somente a vista."
                )
                ->set_type("number")
                ->add_extras('min="0" max="12"')
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title("Chaves da API")
                ->set_value_by_config("kopere_pay-meioasaas-apikey")
                ->set_description(
                    "Sua chave de API oferece acesso total para visualizar e modificar seus dados do Asaas.
                     Trate a chave como uma senha e tenha cuidado ao compartilhá-la.
                     <a href='https://asaas.com/customerConfigIntegrations/index'
                        target='_blank'>https://asaas.com/customerConfigIntegrations/index</a>"
                )
                ->set_required()
        );

        if (isset(config::get_key("kopere_pay-meioasaas-apikey")[10])) {
            MeioAsaas_util::webhooks();
        }
    }

    /**
     * @param local_kopere_pay_detail $detalhe
     * @param \stdClass $course
     *
     * @return string
     * @throws \Exception
     */
    public static function pay_button($detalhe, $course) {
        global $CFG, $USER, $iframe;

        $cpf = formater::only_number(profile_field::get_user_profile_value($USER, 'cpf'));
        if (!cpf::validate($cpf)) {
            header::location("{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&iframe={$iframe}");
        }

        $input = "";
        $installmentCount = config::get_key("kopere_pay-meioasaasboleto-installmentCount");
        if ($installmentCount >= 2) {

            $options = "<option value='0'>Pagar a vista</option>";
            for ($i = 1; $i <= $installmentCount; $i++) {
                $options .= "<option value='{$i}'>{$i} Parcelas</option>";
            }

            $input = "
                <div>
                  <label>Quantas parcelas você deseja para o pagamento?</label>
                  <select id='installmentCount' name='installmentCount'>
                      {$options}
                  </select>
              </div>";
        }

        $link = "{$CFG->wwwroot}/local/kopere_pay/?id={$detalhe->course}&meio=MeioAsaasBoleto&enroll=1";
        return button_util::link($detalhe, self::get_name(), $link, "", $input);
    }

    /**
     * @param $course
     *
     * @return mixed
     * @throws \Exception
     */
    public static function enroll($course) {
        global $DB, $USER, $CFG;

        $id = optional_param("id", false, PARAM_TEXT);
        if (!$id) {
            die("no courseid");
        }
        if (!isset(config::get_key("kopere_pay-meioasaas-apikey")[10])) {
            pay_header::notfound("Token do ASAAS não localizada");
        }

        require_login();
        MeioAsaas_util::webhooks();

        $course = course_util::find($id);

        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $id]);
        $coupon = coupon_util::get_cupom($course->id);
        $price = coupon_util::get_preco($detalhe, $coupon);

        $local_kopere_pay_rename_key =
            local_kopere_pay_enrollment::add_enrollment($USER->id, $id, "MeioAsaasBoleto", $coupon, $price);

        if ($detalhe->charge == "mensalidade") {
            if ($detalhe->days < 3) {
                $detalhe->days = 3;
            }

            $payments = (object) [
                "billingType" => MeioAsaas_util::billing_type("BOLETO"),
                "customer" => MeioAsaas_util::get_and_create_cliente($course),
                "value" => (int) formater::price_to_float($price),
                "nextDueDate" => date("Y-m-d"),
                "description" => $course->fullname,
                "externalReference" => "M-{$local_kopere_pay_rename_key}",
                "cycle" => "MONTHLY",
                "maxPayments" => $detalhe->days,
            ];

            $installmentCount = optional_param('installmentCount', 0, PARAM_INT);
            if ($installmentCount >= 2) {
                $payments->installmentCount = $installmentCount;
                $payments->installmentValue = $price / $installmentCount;
            }

            $ch = MeioAsaas_util::curl_init("subscriptions");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payments));

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                pay_header::notfound(
                    "<h2>Asaas respondeu com error:</h2>" . str_replace("\n", "<br>", htmlentities(curl_error($ch)))
                );
            }
            curl_close($ch);

            $return = json_decode($result);
            local_kopere_pay_history::add_history($local_kopere_pay_rename_key, $payments, $return);

            header::location(
                "{$CFG->wwwroot}/local/kopere_pay/?id={$id}&completed=1&meio=MeioAsaasBoleto&matricula={$local_kopere_pay_rename_key}&vai=1"
            );
        } else {
            $price = (int) formater::price_to_float($price);
            $payments = (object) [
                "billingType" => MeioAsaas_util::billing_type("BOLETO"),
                "customer" => MeioAsaas_util::get_and_create_cliente($course),
                "value" => $price,
                "dueDate" => date("Y-m-d", time() + 60 * 60 * 24 * 3),
                "description" => $course->fullname,
                "externalReference" => "M-{$local_kopere_pay_rename_key}",
            ];

            $installmentCount = optional_param('installmentCount', 0, PARAM_INT);
            if ($installmentCount >= 2) {
                $payments->installmentCount = $installmentCount;
                $payments->installmentValue = $price / $installmentCount;
            }

            $ch = MeioAsaas_util::curl_init("payments");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payments));

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                pay_header::notfound(
                    "<h2>Asaas respondeu com error:</h2>" . str_replace("\n", "<br>", htmlentities(curl_error($ch)))
                );
            }
            curl_close($ch);

            $return = json_decode($result);

            local_kopere_pay_history::add_history($local_kopere_pay_rename_key, $payments, $return);
            if (isset($return->invoiceUrl)) {
                header::location($return->invoiceUrl);
            } else {
                echo '<pre>';
                print_r($payments);
                echo '</pre>';
                $return .= message::danger(
                    "Asaas respondeu com erro:<br>Número: " . curl_errno($ch) . "<br>Erro: " . curl_error($ch) .
                    "<br><pre>Retorno: " . print_r($result, 1) . "</pre>"
                );
            }
        }
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

        $json = file_get_contents("php://input");
        $returned = json_decode($json);

        if (!$returned) {
            die("API de retorno do Asaas! (1)");
        }

        $local_kopere_pay_rename_key = intval(substr($returned->payment->externalReference, 2));
        if ($local_kopere_pay_rename_key) {
            local_kopere_pay_history::add_history($local_kopere_pay_rename_key, "", $returned);

            /** @var local_kopere_pay_enrollment $enrollment */
            $enrollment = $DB->get_record("local_kopere_pay_enrollment", ["id" => $local_kopere_pay_rename_key]);
            if (!$enrollment) {
                die("Matrícula Not Found");
            }
            $course = course_util::find($enrollment->course);
            $user = $DB->get_record("user", ["id" => $enrollment->userid]);
            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $enrollment->course]);

            if ($returned->event == "PAYMENT_RECEIVED" || $returned->event == "PAYMENT_CONFIRMED") {
                if ($course->isCoorte) {
                    enroll_util::cohort_enrol($enrollment->course, $user->id);
                } else {
                    $timeend = timeend_util::calculate_day($detalhe);
                    enroll_util::enrol($course, $user, time(), $timeend);
                }
                enrollment_util::changue_status($enrollment, enrollment_util::PAID);
                send_event::kopere_pay_pago($course, $user);
            } else if ($returned->event == "PAYMENT_REFUNDED") {

                if ($course->isCoorte) {
                    enroll_util::cohort_unenrol($enrollment->course, $user->id);
                } else {
                    enroll_util::unenrol($course, $user);
                }
                enrollment_util::changue_status($enrollment, enrollment_util::WAITING);
                send_event::kopere_pay_refunded($course, $user);
            }
        }

        die("API de retorno do Asaas! (2)");
    }

    /**
     * @param \stdClass $course
     * @param \stdClass $user
     *
     * @throws \Exception
     */
    public static function completed($course, $user) {
        global $OUTPUT, $DB, $USER;

        $local_kopere_pay_rename_key = optional_param("enrollment", 0, PARAM_INT);

        $enrollment = $DB->get_record("local_kopere_pay_enrollment", ["id" => $local_kopere_pay_rename_key]);
        $detalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $enrollment->course]);

        echo "<div class='text-center'>";
        echo $OUTPUT->heading("Matrícula concluída, aguardando confirmação de pagamento.", 2);
        echo $OUTPUT->heading("Número da transação: <strong style='color:#EF5350'>{$local_kopere_pay_rename_key}</strong>", 3);

        if ($detalhe->charge == "mensalidade") {
            $enrollmentCreated = false;
            if (optional_param("vai", false, PARAM_INT)) {
                $sql =
                    "SELECT * FROM {local_kopere_pay_history} WHERE local_kopere_pay_rename_key = {$local_kopere_pay_rename_key} AND receive LIKE '%PAYMENT_CREATED%' LIMIT 1";
                $enrollmentCreated = $DB->get_record_sql($sql);
                if ($enrollmentCreated) {
                    $receive = json_decode($enrollmentCreated->receive);
                    if (isset($receive->payment->invoiceUrl)) {
                        header::location($receive->payment->invoiceUrl);
                    }
                } else {
                    echo "<script>setTimeout(function(){location.reload()},1000);</script>";
                }
            }
            if (!$enrollmentCreated) {
                echo "<p>Por favor, acesse o seu e-mail {$USER->email} e confira as instruções de pagamento que foram enviadas para você.</p>";
            }
        }

        echo "</div>";
    }

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        if (isset($historico->send[10])) {
            $send = json_decode($historico->send);

            if (isset($send->maxPayments)) {
                return "Assinatura criada";
            } else {
                return "Cobrança criada";
            }
        }
        $receive = json_decode($historico->receive);
        return MeioAsaas_util::get_payment_method_event($receive->event);
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {
        // TODO: Implement ajax() method.
    }
}
