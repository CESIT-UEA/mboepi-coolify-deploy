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
 * form.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\shopping;

use coding_exception;
use core\exception\moodle_exception;
use Exception;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\payment_method;
use local_kopere_pay\meios\IMeio;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use stdClass;

/**
 * Class form
 */
class form {

    /**
     * Function show
     *
     * @return void
     * @throws coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function show() {
        global $OUTPUT, $PAGE, $DB, $USER, $CFG;

        $id = optional_param('id', false, PARAM_TEXT);
        $cadastro = optional_param('cadastro', false, PARAM_INT);
        $pagar = optional_param('pagar', false, PARAM_INT);

        $course = course_util::find($id);

        if (!enroll_util::status_enrol_manual($course)) {
            echo $OUTPUT->header();
            $return .= message::danger(get_string("manual_enrolments_disabled_error", "local_kopere_pay"));
            echo $OUTPUT->footer();
            return;
        } else if (isset($USER->id) && $USER->id > 1 && !$course->isCoorte) {
            if (enroll_util::enrolled($course, $USER)) {
                if (optional_param("iframe", false, PARAM_INT)) {
                    echo get_string("already_enrolled_html", "local_kopere_pay");

                    echo $OUTPUT->render_from_template("local_kopere_pay/shopping/already_enrolled_actions", [
                        "courseurl" => "{$CFG->wwwroot}/course/view.php?id={$course->id}",
                    ]);

                    return;
                } else {
                    header::location("{$CFG->wwwroot}/course/view.php?id={$course->id}");
                }
            }
        }

        /** @var local_kopere_pay_detail $koperepaydetalhe */
        $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id]);

        if (!$koperepaydetalhe) {
            echo $OUTPUT->header();
            echo $OUTPUT->notification(get_string("enrollment_not_available", "local_kopere_pay"), "error", false);
            echo $OUTPUT->footer();
            return;
        }

        if ($koperepaydetalhe->status == 'fechado') {
            echo $OUTPUT->header();
            echo $OUTPUT->notification(get_string("enrollment_not_available", "local_kopere_pay"), "error", false);
            echo $OUTPUT->footer();
            return;
        }

        $PAGE->set_title(get_string("enrollment_title_pay", "local_kopere_pay", $course->fullname));

        echo $OUTPUT->header();

        require_once("{$CFG->dirroot}/user/profile/lib.php");
        $USER = $DB->get_record('user', ['id' => $USER->id]);
        if ($USER == null) {
            $USER = (object) [
                'id' => 0,
                'email' => '',
                'phone2' => '',
            ];
        }
        $USER->profile = (array) profile_user_record($USER->id);

        echo "<div class='enrollment'>";

        echo message::get_message_schedule();

        echo $OUTPUT->heading(get_string("enrollment_title_pay", "local_kopere_pay", $course->fullname), 2, "text-center");

        $register = new register();

        if ($pagar) {
            $config = get_config("local_kopere_dashboard");

            if ($register->validate($USER, $config, $koperepaydetalhe, false, false)) {
                echo $this->form_pagar($course, $koperepaydetalhe);
            } else {
                $register->show($course, $koperepaydetalhe);
            }
        } else {
            if ($cadastro) {
                $register->show($course, $koperepaydetalhe);
            } else {
                echo $register->init($course, $koperepaydetalhe);
            }
        }

        echo "</div>";

        echo $OUTPUT->footer();
    }

    /**
     * Function form_pagar
     *
     * @param stdClass $course
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return string
     * @throws Exception
     */
    public function form_pagar($course, $koperepaydetalhe) {
        global $CFG;

        $price = coupon_util::get_preco($koperepaydetalhe);

        $formtitulo = "";
        $formabas = "";
        $formpagar = "";
        if ($price > .5) {
            if ($koperepaydetalhe->charge == 'mensalidade') {
                $return = $this->form_pagar_mensalidade($course, $koperepaydetalhe);

                $formtitulo = '<h3 class="text-center">' . get_string("enrollment_subscribe_with", "local_kopere_pay") . '</h3>';
                $formabas = $return['formAbas'];
                $formpagar = $return['form_pagar'];
            } else {
                $return = $this->form_pagar_unico($course, $koperepaydetalhe);

                $formtitulo = '<h3 class="text-center">' . get_string("enrollment_pay_with", "local_kopere_pay") . '</h3>';
                $formabas = $return['formAbas'];
                $formpagar = $return['form_pagar'];
            }
        } else {
            $formpagar .= '<div id="value"><strong>R$ 0,00</strong> - ' . get_string("enrollment_free_course", "local_kopere_pay") .
                '</div>';
            $formpagar .= "
                <a class='botao' target='_top'
                   href='{$CFG->wwwroot}/local/kopere_pay/r.php?id={$koperepaydetalhe->course}&meio=MeioGratis&enroll=1'
                   onclick='$(this).hide();'>" . get_string("enrollment_enroll", "local_kopere_pay") . "</a>";
        }

        $resume = new resume();

        return "
            <div class='well'>
                {$formtitulo}
                <div class='row form-enrollment'>
                    <div class='col-lg-7 col-12'>
                        <div class='kopere_dashboard-card'>
                            {$formabas}
                            <div class='well2'>
                                <h3 class='text-center'>&nbsp;</h3>
                                {$formpagar}
                            </div>
                        </div>
                    </div>

                    <div class='col-lg-5 col-12'>
                        {$resume->create($koperepaydetalhe)}
                    </div>
                </div>
            </div>" .
            enrollment_util::hide_order($price);
    }

    /**
     * Function form_pagar_mensalidade
     *
     * @param $course
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return array
     */
    private function form_pagar_mensalidade($course, $koperepaydetalhe) {
        $formabas = "";
        $formpagar = "";

        $meios = payment_method::list_meios();
        foreach ($meios as $meio) {
            if ($meio["enable"] && $meio["mensalidade"]) {
                $listameios[] = $meio;
            }
        }

        $count = 0;
        foreach ($listameios as $meio) {
            $count++;

            /** @var IMeio $class */
            $class = "local_kopere_pay\\meios\\" . "{$meio["class"]}";
            $formpagar .= $class::pay_button($koperepaydetalhe, $course);
        }

        return [
            "formAbas" => $formabas,
            "form_pagar" => $formpagar,
        ];
    }

    /**
     * Function form_pagar_unico
     *
     * @param $course
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function form_pagar_unico($course, $koperepaydetalhe) {
        global $CFG, $PAGE, $OUTPUT;

        $formabas = "";
        $formpagar = "";

        $meios = payment_method::list_meios();

        $formabas .= "<div id='media-header'>";
        foreach ($meios as $meio) {
            if ($meio["enable"] && $meio["escolha"]) {
                /** @var IMeio $class */
                $class = "local_kopere_pay\\meios\\" . "{$meio["class"]}";

                $nome = $class::get_name();

                if (isset($meio["icon"])) {
                    $icon = $meio["icon"];
                } else {
                    $icon = $meio["class"];
                }
                $formabas .=
                    "<div class='media-tab' data-meio='{$meio["class"]}'>
                         <img  class='media-icon' src='{$CFG->wwwroot}/local/kopere_pay/assets/meios/{$icon}.svg' />
                         <span class='media-text'>{$nome["public_name"]}</span>
                     </div>";
            }
        }
        $formabas .= "</div>";

        $count = 0;

        if (config::get_key("form_accept_purchase")) {
            $formpediraceite = config::get_key("form_ask_accept");
            if (isset($formpediraceite[40])) {

                $data = [
                    "check-id" => "form_ask_accept",
                    "extra" => get_string("terms_of_service_prompt", "local_kopere_pay"),
                ];
                $formpagar .= $OUTPUT->render_from_template("local_kopere_pay/html/input-checkbox", $data);

                $formpagar .= message::warning(get_string("terms_of_service_required", "local_kopere_pay"));

                $PAGE->requires->js_call_amd('local_kopere_pay/enrollment', 'aceite');
            }
        }

        foreach ($meios as $meio) {
            if ($meio['enable'] && $meio['escolha']) {
                $count++;

                /** @var IMeio $class */
                $class = "local_kopere_pay\\meios\\" . "{$meio['class']}";
                $formpagar .=
                    "<div class='media-tab-area' id='meio-{$meio['class']}'>" .
                    $class::pay_button($koperepaydetalhe, $course) .
                    "</div>";
            }
        }

        return [
            'formAbas' => $formabas,
            'form_pagar' => $formpagar,
        ];
    }
}
