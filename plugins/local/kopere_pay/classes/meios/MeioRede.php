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
 * MeioRede.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

// https://github.com/DevelopersRede/erede-php

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\util\button_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 * Class MeioRede
 */
class MeioRede implements IMeio {
    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        return [
            'name' => 'E-Rede',
            'public_name' => 'Cartão de Crédito',
            'class' => 'MeioRede',
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
        if (config::get_key("kopere_pay-habilitar-MeioRede")) {
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
            input_text::new_instance()
                ->set_title('ID do estabelecimento')
                ->set_value_by_config('kopere_pay-meiorede-pv')
                ->set_description(
                    "ID do seu estabelecimento, disponíve no topo em
                     <a href='https://meu.userede.com.br/home' target='_blank'>https://meu.userede.com.br/home</a>"
                )
                ->set_required()
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title('TOKEN E-Rede')
                ->set_value_by_config('kopere_pay-meiorede-token')
                ->set_description(
                    "Gere em <a href='https://www.userede.com.br/sites/fechado/erede/paginas/pn_gerartoken.aspx' target='_blank'>
                     https://www.userede.com.br/sites/fechado/erede/paginas/pn_gerartoken.aspx</a>"
                )
                ->set_required()
        );

        $parcelas = [
            ["key" => 0, "value" => "Somente a vista"],
        ];
        for ($i = 1; $i <= 12; $i++) {
            $parcelas[] = ["key" => $i, "value" => "{$i} x"];
        }
        $form->add_input(
            input_select::new_instance()
                ->set_title('Máximo de Parcelas')
                ->set_values($parcelas)
                ->set_value_by_config('kopere_pay-meiorede-numparcelas')
                ->set_description("Especifique a quantidade máxima de parcelas que o aluno pode escolher!")
                ->set_required()
        );

        if (config::get_key('kopere_pay-meiorede-minparcela') < 5) {
            set_config('kopere_pay-meiorede-minparcela', 5, "local_kopere_dashboard");
        }

        $form->add_input(
            input_text::new_instance()
                ->set_title('Valor mínimo de cada parcela')
                ->set_value_by_config('kopere_pay-meiorede-minparcela')
                ->set_description("Especifique o preço mínimo de cada parcela!")
                ->set_required()
        );
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

        if ($detalhe->charge != 'mensalidade') {
            $link = "{$CFG->wwwroot}/local/kopere_pay/r.php?id={$detalhe->course}&meio=MeioRede&enroll=1";
            return button_util::link($detalhe, self::get_name(), $link);
        }

        return '';
    }

    /**
     * Function returned
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function returned() {
        global $DB, $USER, $CFG;

        require_once(__DIR__ . '/rede/vendor/autoload.php');

        $enroll = optional_param('enroll', false, PARAM_INT);
        $courseid = optional_param('id', false, PARAM_INT);

        if ($enroll) {

            $course = $DB->get_record('course', ['id' => $courseid]);

            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $course->id]);
            $coupon = coupon_util::get_cupom($course->id);

            $price = coupon_util::get_preco($detalhe, $coupon);

            $enrollmentid = local_kopere_pay_enrollment::add_enrollment($USER->id, $course->id, 'MeioRede', $coupon, $price);
            local_kopere_pay_history::add_history($enrollmentid, [], []);

            header::location(
                "{$CFG->wwwroot}/local/kopere_pay/?id={$course->id}&completed=1&meio=MeioRede&matricula={$enrollmentid}"
            );
        }
    }

    /**
     * @param     $course
     * @param     $user
     * @throws \Exception
     */
    public static function completed($course, $user) {
        global $DB, $OUTPUT, $CFG;

        require_once(__DIR__ . '/rede/vendor/autoload.php');

        $enrollmentid = optional_param('matricula', 0, PARAM_INT);
        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid]);

        $cardNumber = optional_param('card-number', false, PARAM_TEXT);
        $cardName = optional_param('card-name', false, PARAM_TEXT);
        $cardMes = optional_param('card-mes', false, PARAM_TEXT);
        $cardAno = optional_param('card-ano', false, PARAM_TEXT);
        $cardCvv = optional_param('card-cvv', false, PARAM_TEXT);
        $cardParcelas = optional_param('card-parcelas', false, PARAM_TEXT);

        $returned = ['status' => false];
        if ($cardNumber) {
            $returned = self::completed_pagar($enrollmentid, $enrollment);
        }

        $optionsMes = $optionsAno = "";
        for ($i = 1; $i <= 12; $i++) {
            $i = substr("0{$i}", -2);
            if ($cardMes == $i) {
                $optionsMes .= "<option selected value='{$i}'>{$i}</option>";
            } else {
                $optionsMes .= "<option value='{$i}'>{$i}</option>";
            }
        }
        for ($i = date('Y'); $i <= date('Y') + 12; $i++) {
            if ($cardAno == $i) {
                $optionsAno .= "<option selected value='{$i}'>{$i}</option>";
            } else {
                $optionsAno .= "<option value='{$i}'>{$i}</option>";
            }
        }

        $statusText = "";
        if (isset($returned['returnCode'])) {
            $status = self::getInternalStatus($returned['returnCode']);

            if ($returned['status'] === false) {
                //$status['DEFINICAO'];
                //$status['ACAO'];

                $statusText = message::danger(
                    "<b>" . $status['DEFINICAO'] . "</b><br>" .
                    $status['ACAO']
                );
            }
        }

        if ($returned['status'] === true) {
            header::location("{$CFG->wwwroot}/course/view.php?id={$enrollment->course}");
        }
        if ($returned['status'] === false) {

            $numParcelas = config::get_key('kopere_pay-meiocielo-numparcelas');
            $minParcela = config::get_key('kopere_pay-meiocielo-minparcela');

            $testParcelas = intval($enrollment->value) / $minParcela;
            if ($testParcelas < $numParcelas) {
                $numParcelas = (int) $testParcelas;
            }

            if ($numParcelas > 1) {

                $options = '';
                for ($i = 2; $i <= $numParcelas; $i++) {
                    if ($cardParcelas == $i) {
                        $options .= "<option selected value=\"{$i}\">{$i} parcelas</option>";
                    } else {
                        $options .= "<option value=\"{$i}\">{$i} parcelas</option>";
                    }
                }
                $parcelamento = '
                    <div class="form-group">
                        <label>Parcelamento:</label>
                        <select name="card-parcelas">
                            <option value="1">A vista</option>
                            ' . $options . '
                        </select>
                    </div>';
            } else {
                $parcelamento = "";
            }

            echo "<div class='text-center'>";
            echo $OUTPUT->heading("Matrícula concluída, aguardando confirmação de pagamento.", 2);
            echo $OUTPUT->heading("Número da transação: <strong style='color:#EF5350'>{$enrollmentid}</strong>", 3);
            echo "</div>";

            echo '
            <article class="card" style="max-width:500px;margin:0 auto;padding:12px;">
                <div class="card-body p-5">
                    ' . $statusText . '
                    <form role="form" method="post">
                        <div class="form-group">
                            <label for="card-number">* Número do cartão:</label>
                            <input type="text" class="form-control" name="card-number" required
                                   placeholder="Digite o número do cartão" style="width:100%;"
                                   value="' . $cardNumber . '">
                        </div>

                        <div class="form-group">
                            <label for="card-name">* Nome impresso no cartão:</label>
                            <input type="text" class="form-control" name="card-name" required
                                   placeholder="Como impresso no cartão" style="width:100%;"
                                   value="' . $cardName . '">
                        </div>

                        <div class="form-group">
                            <label>* Validade</label>
                            <div class="input-group" style="display:flex;align-items:center;">
                                <select type="number" class="form-control" name="card-mes" required>
                                <option>Selecione o Mês</option>
                                ' . $optionsMes . '</select>
                                <span style="width:35px;text-align:center;font-size:30px;">/</span>
                                <select type="number" class="form-control" name="card-ano" required>
                                <option>Selecione o Ano</option>
                                ' . $optionsAno . '</select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>* Cód. Segurança(CVV):</label>
                            <input type="number" class="form-control" name="card-cvv" required
                                   style="width:80px" value="' . $cardCvv . '">
                        </div>

                        ' . $parcelamento . '

                        <input type="submit" class="subscribe btn btn-primary btn-block" value="Pagar">
                    </form>
                </div>
            </article>';
        }
    }

    /**
     * @param $course
     * @return mixed
     */
    public static function enroll($course) {
    }

    /**
     * Function completed_pagar
     *
     * @param $enrollmentid
     * @param $enrollment
     * @return void
     */
    protected static function completed_pagar($enrollmentid, $enrollment) {
    }

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        $receive = json_decode($historico->receive, true);

        if (isset($receive['modo'])) {
            return MeioManual::get_status($receive);
        }

        if (!isset($receive['returnCode'])) {
            return "";
        }
        $status = self::getInternalStatus($receive["returnCode"]);

        $link =
            "https://admin.braspag.com.br/ReportPagador/TransactionDetails/{$receive['paymentId']}?history=False&printVersion=False";

        if (isset($status['DEFINICAO'])) {
            return "<strong>{$status['DEFINICAO']}</strong><br>{$status['ACAO']}<br>
                <strong>Transação ID:</strong> <a href='{$link}' target='_blank'>{$receive['paymentId']}</a>
                <strong>TID:</strong> {$receive['tid']}<br>";
        }
        return '';
    }

    /**
     * Function getInternalStatus
     *
     * @param $returnCode
     * @return void
     */
    protected static function getInternalStatus($returnCode) {
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {
    }
}