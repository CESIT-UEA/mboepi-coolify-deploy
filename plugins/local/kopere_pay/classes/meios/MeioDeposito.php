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
 * MeioDeposito.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_htmleditor;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_pay\util\button_util;
use local_kopere_pay\util\completed_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_enrollment;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 * Class MeioDeposito
 */
class MeioDeposito implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        return [
            'name' => 'Meu PIX',
            'public_name' => 'PIX',
            'class' => 'MeioDeposito',
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
        if (config::get_key("kopere_pay-habilitar-MeioDeposito")) {
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
        global $OUTPUT;

        if (!config::get_key('kopere_pay-meiodeposito-conta')) {
            $value = get_string("pix_default_instructions_html", "local_kopere_pay");
            set_config("kopere_pay-meiodeposito-conta", $value, "local_kopere_dashboard");
        }

        $form->add_input(
            input_htmleditor::new_instance()
                ->set_title("Instrução para o PIX")
                ->set_name("kopere_pay-meiodeposito-conta")
                ->set_value_by_config("kopere_pay-meiodeposito-conta")
                ->set_description("Coloque aqui os dados para o aluno fazer o PIX e também enviar o comprovante!")
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
            $link = "{$CFG->wwwroot}/local/kopere_pay/?id={$detalhe->course}&meio=MeioDeposito&enroll=1";
            return button_util::link($detalhe, self::get_name(), $link);
        }

        return '';
    }

    /**
     * @param     $course
     * @param     $user
     *
     * @throws \Exception
     */
    public static function completed($course, $user) {
        global $DB, $USER, $OUTPUT;

        $enrollmentid = optional_param('enrollment', 0, PARAM_INT);
        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid, 'userid' => $USER->id]);

        $data = [
            "enrollment_id" => $enrollmentid,
            "instrucoes" => config::get_key('kopere_pay-meiodeposito-conta'),
            "summary" => completed_util::summary($course, $enrollment),
        ];
        echo $OUTPUT->render_from_template('local_kopere_pay/pix-completed', $data);
    }

    /**
     * @param $course
     *
     * @return mixed
     * @throws \Exception
     */
    public static function enroll($course) {
        global $DB, $USER, $CFG;

        $id = optional_param('id', false, PARAM_TEXT);

        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id]);
        $coupon = coupon_util::get_cupom($id);

        $price = coupon_util::get_preco($detalhe, $coupon);

        $enrollmentid = local_kopere_pay_enrollment::add_enrollment($USER->id, $id, 'MeioDeposito', $coupon, $price);
        local_kopere_pay_history::add_history($enrollmentid, [], []);

        header::location("{$CFG->wwwroot}/local/kopere_pay/?id={$id}&completed=1&meio=MeioDeposito&enrollment={$enrollmentid}");
    }

    /**
     * Function returned
     *
     * @return void
     */
    public static function returned() {
    }

    /**
     * @param \local_kopere_pay\vo\local_kopere_pay_history $historico
     *
     * @return string
     */
    public static function get_status($historico) {
        return "Pagamento iníciado por PIX";
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {

    }
}