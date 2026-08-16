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
 * MeioPagseguro.php
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
use local_kopere_dashboard\util\mensagem;
use local_kopere_dashboard\util\message;
use local_kopere_pay\meios\util\MeioPagseguro_mensalidade;
use local_kopere_pay\meios\util\MeioPagseguro_unico;
use local_kopere_pay\meios\util\PagseguroUtil;
use local_kopere_pay\util\button_util;
use local_kopere_pay\util\completed_util;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\send_event;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 * Class MeioPagseguro
 *
 * @package local_kopere_pay\meios
 */
class MeioPagseguro implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {

        // $habilitado = config::get_key( "kopere_pay-habilitar-MeioPagseguro");
        // $formmensalidade = config::get_key( "form_monthly_fee");
        // if ($habilitado && $formmensalidade) {
        //     set_config('form_pedircpf', 1, "local_kopere_dashboard");
        //     set_config('form_pedir_celular', 1, "local_kopere_dashboard");
        //     set_config('form_pedirbirth', 1, "local_kopere_dashboard");
        // }

        return [
            'name' => 'PagSeguro',
            'public_name' => 'PagSeguro',
            'class' => 'MeioPagseguro',
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
        if (config::get_key("kopere_pay-habilitar-MeioPagseguro")) {
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
        global $CFG, $DB, $USER, $COURSE;

        if (optional_param("logsproducao", false, PARAM_INT)) {
            $koperepaydetalhe = $DB->get_record_sql(
                "SELECT * FROM {local_kopere_pay_detail} kpd JOIN {course} c ON c.id = kpd.course WHERE price > 2 LIMIT 1"
            );
            if ($koperepaydetalhe) {
                $course = $DB->get_record('course', ['id' => $koperepaydetalhe->course]);
                echo self::get_data($course, $koperepaydetalhe->price);
            } else {
                $form->return .= message::danger(
                    "
                        <p>Antes de darmos início ao processo de homologação do PagSeguro, há um passo crucial que requer a
                           sua atenção: a configuração do preço em pelo menos um dos cursos.</p>
                        <p>Para realizar essa configuração, por favor, siga as instruções abaixo:</p>
                        <ol>
                            <li>Acesse a seção <a href='{$CFG->wwwroot}/local/kopere_pay/open.php?classname=dashboard&method=start'>Todos os cursos</a>:</li>
                            <li>Selecione o curso no qual você deseja configurar o preço.</li>
                            <li>Certifique-se de definir um preço que seja maior que 2 reais.
                                Isso é necessário para garantir que todas as transações sejam processadas corretamente.</li>
                            <li>Após concluir essa configuração, retorne a esta tela para que possamos prosseguir com a homologação do PagSeguro.</li>
                        </ol>"
                );
            }
        } else {
            if (config::get_key_int('form_monthly_fee')) {
                $form->return .= message::info(
                    "
                Localize o Token <a href='https://pagseguro.uol.com.br/preferencias/integracoes.jhtml#geraToken'
                                    target='_blank'>aqui</a>"
                );

            } else {
                $form->return .= message::info(
                    "Localize o Token
                    <a href='https://pagseguro.uol.com.br/preferencias/integracoes.jhtml#geraToken' target='_blank'>aqui</a>!
                    <br>Não é necessário configurar A URL de Notificação de transação!"
                );
            }

            if (config::get_key_int("kopere_pay-pagseguro-sandbox")) {
                //$form->add_input(
                //    input_text::new_instance()
                //        ->set_title('Email do PagSeguro (SandBox)')
                //        ->set_value_by_config('kopere_pay-pagseguro-email-sandbox')
                //        ->set_required()
                //        ->add_validator('email'));

                $form->add_input(
                    input_text::new_instance()
                        ->set_title('Token do PagSeguro (SandBox)')
                        ->set_value_by_config('kopere_pay-pagseguro-token-sandbox')
                        ->set_required()
                );

                $order = "";
                if ($DB->get_dbfamily() == 'mysql') {
                    $order = "ORDER BY RAND()";
                } else if ($DB->get_dbfamily() === 'postgres') {
                    $order = "ORDER BY RANDOM()";
                }
                $koperepaydetalhe =
                    $DB->get_record_sql("SELECT * FROM {local_kopere_pay_detail} WHERE price LIKE '__%' {$order} LIMIT 1");
                if ($koperepaydetalhe) {
                    $course = $DB->get_record('course', ['id' => $koperepaydetalhe->course]);
                    $form->print_row(
                        "Solicitar a Homologação", "
                <p>O objetivo da homologação é garantir que a integração do Moodle com o PagSeguro está ocorrendo da
                   melhor maneira possível, evitando transtornos no ambiente de produção e auxiliando na validação das
                   solicitações para uma melhor experiência. Nesse processo, o PagSeguro validará os testes e,
                   com esse processo concluído, você poderá acessar o ambiente Produção.</p>
                <p>Para solicitar a Homologação primeiro defina pelo menos um curso com preço de venda.</p>
                <p>Depois, basta abrir um chamado
                   <a href='https://app.pipefy.com/public/form/2e56YZLK?informe_o_e_mail_do_respons_vel_t_cnico={$USER->email}&selecione_o_tipo_de_integra_o=Plataformas&nos_informe_qual_plataforma_realizou_a_integra_o=Outros&selecione_qual_servi_o_voc_integrou=Checkout%20PagBank' target='_blank'>neste link</a>
                   e usar como base o texto abaixo enviar:</p>

                <pre style='word-break:initial;word-wrap:initial;white-space:break-spaces;'>
Prezada equipe do PagSeguro,

Meu nome é " . fullname($USER) . ", e sou representante pelo Moodle {$COURSE->fullname}. Estou entrando em contato para solicitar a validação da API para o nosso software de pagamento, Kopere Pay, desenvolvido pelo Eduardo Kraus.

Entendemos a importância de garantir a segurança e eficiência das transações financeiras, e é por isso que buscamos a validação da API junto ao PagSeguro. Nosso plugin, o Kopere Pay, é um software confiável e robusto que visa facilitar e aprimorar a experiência de pagamento para nossos usuários.

A integração com o PagSeguro é fundamental para que nossos clientes possam usufruir dos benefícios e funcionalidades oferecidos por ambas as plataformas. Acreditamos que essa colaboração será benéfica para ambas as partes, proporcionando uma experiência de pagamento mais eficiente e segura.

Nome do Software: Kopere Pay
Desenvolvedor: Eduardo Kraus
Curso para testes: <span style='color:#E91E63'>{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}</span>

" . self::get_data($course, $koperepaydetalhe->price) . "

Estamos à disposição para fornecer qualquer documentação adicional, esclarecer dúvidas ou realizar ajustes necessários para garantir uma integração perfeita. Agradecemos antecipadamente pela atenção dispensada a esta solicitação e aguardamos ansiosos pela confirmação da validação da API.

Ficamos à disposição para qualquer esclarecimento adicional e agradecemos pela parceria contínua.

Atenciosamente,
" . fullname($USER) . "
            </pre>"
                    );
                } else {
                    $form->return .= message::danger(
                        "
                <p>Antes de darmos início ao processo de homologação do PagSeguro, há um passo crucial que requer a
                   sua atenção: a configuração do preço em pelo menos um dos cursos.</p>
                <p>Para realizar essa configuração, por favor, siga as instruções abaixo:</p>
                <ol>
                    <li>Acesse a seção <a href='{$CFG->wwwroot}/local/kopere_pay/open.php?classname=dashboard&method=start'>Todos os cursos</a>:</li>
                    <li>Selecione o curso no qual você deseja configurar o preço.</li>
                    <li>Certifique-se de definir um preço que seja maior que 2 reais.
                        Isso é necessário para garantir que todas as transações sejam processadas corretamente.</li>
                    <li>Após concluir essa configuração, retorne a esta tela para que possamos prosseguir com a homologação do PagSeguro.</li>
                </ol>"
                    );
                }
            } else {
                //$form->add_input(
                //    input_text::new_instance()
                //        ->set_title('Email do PagSeguro')
                //        ->set_value_by_config('kopere_pay-pagseguro-email')
                //        ->set_required()
                //        ->add_validator('email'));

                $form->add_input(
                    input_text::new_instance()
                        ->set_title('Token do PagSeguro')
                        ->set_value_by_config('kopere_pay-pagseguro-token')
                        ->set_required()
                );
            }

            if (config::get_key("kopere_pay-pagseguro-soft_descriptor") == null) {
                global $COURSE;
                $softdescriptor = substr($COURSE->shortname, 0, 17);
                set_config("kopere_pay-pagseguro-soft_descriptor", $softdescriptor, "local_kopere_dashboard");
            }
            $softdescriptor = config::get_key("kopere_pay-pagseguro-soft_descriptor");
            if (isset($softdescriptor[17])) {
                $softdescriptor = substr($softdescriptor, 0, 17);
                set_config("kopere_pay-pagseguro-soft_descriptor", $softdescriptor, "local_kopere_dashboard");
            }

            $form->add_input(
                input_text::new_instance()
                    ->set_title('Texto adicional do Cartão')
                    ->set_value_by_config('kopere_pay-pagseguro-soft_descriptor')
                    ->set_description(
                        "Texto adicional que será apresentado junto ao nome do estabelecimento na fatura do cartão de crédito do comprador. Limite: 17 caracteres"
                    )
                    ->set_required()
            );

            if (config::get_key("kopere_pay-pagseguro-credit_card") == null) {
                set_config("kopere_pay-pagseguro-credit_card", 1, "local_kopere_dashboard");
                set_config("kopere_pay-pagseguro-debit_card", 1, "local_kopere_dashboard");
                set_config("kopere_pay-pagseguro-pix", 1, "local_kopere_dashboard");
                set_config("kopere_pay-pagseguro-boleto", 1, "local_kopere_dashboard");
            }

            $form->add_input(
                input_checkbox_select::new_instance()
                    ->set_title('Aceitar Cartão de crédito')
                    ->set_checked_by_config('kopere_pay-pagseguro-credit_card')
            );

            $form->add_input(
                input_checkbox_select::new_instance()
                    ->set_title('Aceitar Cartão de débito')
                    ->set_checked_by_config('kopere_pay-pagseguro-debit_card')
            );

            $form->add_input(
                input_checkbox_select::new_instance()
                    ->set_title('Aceitar PIX')
                    ->set_checked_by_config('kopere_pay-pagseguro-pix')
            );

            $form->add_input(
                input_checkbox_select::new_instance()
                    ->set_title('Aceitar Boleto Bancário')
                    ->set_checked_by_config('kopere_pay-pagseguro-boleto')
            );

            $form->add_input(
                input_checkbox_select::new_instance()
                    ->set_title('Ambiente em SandBox?')
                    ->set_checked_by_config('kopere_pay-pagseguro-sandbox')
                    ->set_description("Ao mudar está configuração, save e volta a está página!")
            );

            $sandbox = get_config("local_kopere_dashboard", 'kopere_pay-pagseguro-sandbox');
            if (!$sandbox) {
                echo "<a href='?classname=payment_method&method=edit&meio=MeioPagseguro&logsproducao=1'
                         target='_blank'>Obter LOGS do PagSeguro em Produção</a>";
            }
        }
    }

    /**
     * @throws \dml_exception
     */
    public static function get_data($course, $price) {
        global $CFG;

        $enrollmentid = rand();

        $data = [
            "reference_id" => "M-{$enrollmentid}",
            "expiration_date" => date("Y-m-d\T23:59:00-00:00", time() + 60 * 60 * 24 * 3),
            "customer" => [
                "name" => "Testador PagSeguro",
                "email" => "testador_pagseguro@eduardokraus.com",
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
            "payment_methods" => [
                ["type" => "credit_card"],
                ["type" => "debit_card"],
                ["type" => "PIX"],
                ["type" => "BOLETO"],
            ],
            "soft_descriptor" => config::get_key('kopere_pay-pagseguro-soft_descriptor'),
            "redirect_url" => "{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&completed=1&meio=MeioPagseguro&enrollment={$enrollmentid}",
            "return_url" => "{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&completed=1&meio=MeioPagseguro&matricula={$enrollmentid}",
            "notification_urls" => [
                "{$CFG->wwwroot}/local/kopere_pay/r.php?meio=ps",
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.api.pagseguro.com/checkouts');
        $sandbox = get_config("local_kopere_dashboard", 'kopere_pay-pagseguro-sandbox');
        if ($sandbox) {
            $headers = [
                "Authorization: Bearer " . config::get_key('kopere_pay-pagseguro-token-sandbox'),
                'Content-Type: application/json',
            ];
        } else {
            $headers = [
                "Authorization: Bearer " . config::get_key('kopere_pay-pagseguro-token'),
                'Content-Type: application/json',
            ];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return '<h2>PagSeguro respondeu com error:</h2>' . str_replace("\n", "<br>", htmlentities(curl_error($ch)));
        }
        curl_close($ch);

        return
            "<pre>Enviado ao PagSeguro:\n" . print_r($data, 1) . '</pre>' .
            "<pre>Recebido do PagSeguro:\n" . print_r(json_decode($result), 1) . '</pre>';
    }

    /**
     * @param local_kopere_pay_detail $detalhe
     * @param \stdClass $course
     *
     * @return string
     * @throws \Exception
     */
    public static function pay_button($detalhe, $course) {
        global $CFG;

        $link = "{$CFG->wwwroot}/local/kopere_pay/?id={$detalhe->course}&meio=MeioPagseguro&enroll=1";

        if ($coupon = optional_param("coupon", false, PARAM_TEXT)) {
            $link = "{$link}&coupon={$coupon}";
        }

        return button_util::link($detalhe, self::get_name(), $link);
    }

    /**
     * @param $course
     *
     * @return mixed
     * @throws \Exception
     */
    public static function enroll($course) {
        global $DB;

        require_login();

        if ($course->isCoorte) {
            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => "c{$course->id}"]);
        } else {
            $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $course->id]);
        }

        if ($detalhe) {
            if ($detalhe->charge == 'unico') {
                MeioPagseguro_unico::enroll($course, $detalhe);
            } else {
                MeioPagseguro_mensalidade::enroll($course, $detalhe);
            }
        } else {
            mensagem::print_danger("Curso não localizado!");
        }
    }

    /**
     * @param     $course
     * @param     $user
     * @param int $enrollmentid
     *
     * @throws \Exception
     */
    public static function completed($course, $user) {
        global $DB;

        $enrollmentid = optional_param('matricula', 0, PARAM_INT);

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid]);
        $lasthistory = $DB->get_record_sql(
            "SELECT * FROM {local_kopere_pay_history} WHERE enrollmentid={$enrollmentid} ORDER BY id DESC LIMIT 1"
        );

        $receive = json_decode($lasthistory->receive);

        if (!isset($receive->charges[0])) {
            $receive->charges = [];
            $receive->charges[0] = (object) ["status" => 0];
        }

        if (isset($receive->charges[0]->status)) {
            $status = PagseguroUtil::getStatus($receive->charges[0]->status);

            echo "
                <div class='row'>
                    <div class='col-lg-7 col-12 order-down text-center'>
                        <div class='kopere_dashboard-card well2'>
                            <h2>Matrícula realizada.</h2>
                            <h3>{$status}</h3>";

            $href = false;
            foreach ($receive->links as $link) {
                if ($link->rel == "PAY") {
                    $href = $link->href;
                }
            }

            if ($href) {
                echo "<p>Sua matrícula esta aguardando pagamento, faça o download do boleto no botão abaixo e efetue o pagamento.</p>";
                echo "<a target='_blank' class='link-pay' href='{$href}'>Acessar o formulário de pagamento</a>";
            }

            echo "<h3>O código da transação é: <br><strong style='color:#EF5350'>{$receive->id}</strong></h3>";

            echo "       </div>
                    </div>
                    <div class='col-lg-5 col-12 order-up'>
                        " . completed_util::summary($course, $enrollment) . "
                    </div>
                </div>";
        } else {
            $return .= message::warning("JSON '{$lasthistory->receive}' não é vãlido.");
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

        $returned = file_get_contents('php://input');
        $returned = json_decode($returned);
        if (!$returned) {
            die('API de retorno do PagSeguro! (1)');
        }

        $enrollmentid = intval(substr($returned->reference_id, 2));

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $local_kopere_pay_rename_key]);
        if (!$enrollment) {
            die('Matrícula Not Found');
        }

        $course = course_util::find($enrollment->course);
        $user = $DB->get_record('user', ['id' => $enrollment->userid]);
        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $enrollment->course]);

        local_kopere_pay_history::add_history($local_kopere_pay_rename_key, null, $returned);

        if ($returned->charges[0]->status == "PAID") {
            if ($course->isCoorte) {
                enroll_util::cohort_enrol($enrollment->course, $user->id);
            } else {
                $timeend = timeend_util::calculate_day($detalhe);
                enroll_util::enrol($course, $user, time(), $timeend);
            }
            enrollment_util::changue_status($enrollment, enrollment_util::PAID);
            send_event::kopere_pay_pago($course, $user);

        } else if ($returned->charges[0]->status == "CANCELED") {

            if ($course->isCoorte) {
                enroll_util::cohort_unenrol($enrollment->course, $user->id);
            } else {
                enroll_util::unenrol($course, $user);
            }
            enrollment_util::changue_status($enrollment, enrollment_util::WAITING);
            send_event::kopere_pay_refunded($course, $user);
        }

        die('API de retorno do PagSeguro! (2)');
    }

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        if ($historico == null) {
            return "Ops, sem histórico";
        }

        if (strlen($historico->error) > 10) {
            return "Erro na transação";
        }
        $receive = json_decode($historico->receive, true);

        if (isset($receive['modo'])) {
            return MeioManual::get_status($receive);
        }

        if (!isset($receive['charges'])) {
            return "Pagamento enviado ao PagSeguro";
        }

        return PagseguroUtil::getStatusV4($receive['charges'][0]['status']);
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {
    }
}