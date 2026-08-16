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
 * coupons.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use local_kopere_dashboard\html\data_table;
use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_base;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_pay\html\inputs\input_textarea;
use local_kopere_dashboard\html\table_header_item;
use local_kopere_dashboard\output\layout;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\html;
use local_kopere_dashboard\util\json;
use local_kopere_dashboard\util\message;
use local_kopere_dashboard\util\string_util;
use local_kopere_pay\shopping\pay_header;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\formater;

/**
 * Class coupons
 */
class coupons extends base {

    /**
     * Function dashboard
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws \Exception
     */
    public function dashboard() {
        global $OUTPUT, $PAGE;

        $id = optional_param("course", 0, PARAM_TEXT);
        $course = course_util::find($id);

        $PAGE->set_title(get_string("coupons_of_course", "local_kopere_pay", $course->fullname));

        $return = menu::tabs(3);
        $return .= "<div class=\"kopere_dashboard-card\">";

        $return .= $OUTPUT->render_from_template("local_kopere_pay/admin/coupons_actions", [
            "addurl" => "?classname=coupons&method=form_new&course={$id}",
            "importurl" => "?classname=coupons&method=form_import&course={$id}",
        ]);

        $table = new data_table();
        $table->add_header(get_string("settings_pay_coupon", "local_kopere_pay"), "uniquekey", null, null, 'width: 20px');
        $table->add_header(get_string("coupon_available_quantity", "local_kopere_pay"), "amount", table_header_item::TYPE_INT);
        $table->add_header(get_string("coupon_value_to_pay", "local_kopere_pay"), "value", table_header_item::TYPE_CURRENCY);
        $table->add_header(get_string("useremail", "local_kopere_pay"), "email");
        $table->add_header('', "actions");

        $table->set_ajax_url('view-ajax.php?classname=coupons&method=load_all_coupons_value&course=' . $id);
        $return .= $table->print_header("", true, true);
        $return .= $table->close(false, null, true);

        $return .= "</div>";
        return $return;
    }

    /**
     * Function load_all_coupons_value
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public function load_all_coupons_value() {
        global $DB, $CFG, $OUTPUT;

        $cursosid = optional_param("course", 0, PARAM_TEXT);

        $coupons = $DB->get_records("local_kopere_pay_coupon", ["course" => $cursosid, "type" => "value"]);

        $returncoupons = [];
        foreach ($coupons as $coupon) {
            $coupon->actions = $OUTPUT->render_from_template("local_kopere_pay/admin/coupon_action_delete", [
                "deleteurl" => "?classname=coupons&method=delete&couponid={$coupon->id}&course={$cursosid}",
                "iconurl" => "{$CFG->wwwroot}/local/kopere_dashboard/assets/dashboard/img/actions/delete.svg",
            ]);

            if (!strlen($coupon->email)) {
                $coupon->email = get_string("not_defined", "local_kopere_pay");
            }

            $returncoupons[] = $coupon;
        }

        json::encode($returncoupons);
    }

    /**
     * Function form_new
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public function form_new() {
        global $DB, $PAGE;

        $course = optional_param("course", 0, PARAM_TEXT);
        $couponlength = get_config("local_kopere_dashboard", "couponlength");
        $couponlength = $couponlength == null ? 8 : $couponlength;

        $PAGE->set_title(get_string("coupon_creating_new", "local_kopere_pay"));

        $return = menu::tabs(3);
        $return .= "<div class=\"kopere_dashboard-card\">";

        if (form::check_post()) {
            $cadastrar = true;

            $uniquekey = optional_param("key", "", PARAM_TEXT);
            if (strlen($uniquekey) == 0) {
                $uniquekey = string_util::generate_random_string($couponlength);
            }
            $uniquekey = strtoupper($uniquekey);

            $coupon = $DB->get_record("local_kopere_pay_coupon", ["uniquekey" => $uniquekey, "course" => $course]);
            if ($coupon) {
                $return .= message::danger(get_string("coupon_already_exists_html", "local_kopere_pay", $uniquekey));
                $cadastrar = false;
            } else if (strlen($uniquekey) != $couponlength) {
                $return .= message::danger(get_string("coupon_must_have_length_html", "local_kopere_pay", $couponlength));
                $cadastrar = false;
            }

            $value = optional_param("value", '', PARAM_TEXT);
            if (strlen($value) == 0) {
                $return .= message::danger(get_string("coupon_value_cannot_be_empty", "local_kopere_pay"));
                $cadastrar = false;
            }

            $amount = optional_param("amount", '', PARAM_INT);
            if ($amount < 1) {
                $return .= message::danger(get_string("coupon_quantity_must_be_positive", "local_kopere_pay"));
                $cadastrar = false;
            }

            if ($cadastrar) {
                $coupon = (object) [
                    "course" => $course,
                    "uniquekey" => $uniquekey,
                    "email" => optional_param("email", '', PARAM_EMAIL),
                    "amount" => $amount,
                    "value" => formater::number_formater($value),
                    "type" => optional_param("type", "value", PARAM_TEXT),
                    "time" => time(),
                ];

                $DB->insert_record("local_kopere_pay_coupon", $coupon);

                message::schedule_message_success(get_string("coupon_created_success_html", "local_kopere_pay", $uniquekey));
                header::location('?classname=coupons&method=dashboard&course=' . optional_param("course", '', PARAM_TEXT));
            }
        }

        $form = new form('?classname=coupons&method=form_new');

        $form->create_hidden_input("course", $course);
        $form->create_hidden_input("type", "value");

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("coupon_key", "local_kopere_pay"))
                ->set_name("key")
                ->set_class('text-uppercase')
                ->add_extras("minlength='{$couponlength}' maxlength='{$couponlength}'")
                ->set_style('width: ' . ($couponlength * 15) . "px")
                ->set_description(get_string("coupon_key_description_html", "local_kopere_pay", $couponlength))
        );

        $form->add_input(
            input_text::new_instance()
                ->set_type("number")
                ->set_title(get_string("summary_table_quantity", "local_kopere_pay"))
                ->set_name("amount")
                ->set_required()
                ->add_validator(input_base::VAL_INT)
                ->set_description(get_string("coupon_quantity_description", "local_kopere_pay"))
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("payments_valor", "local_kopere_pay"))
                ->set_name("value")
                ->set_required()
                ->add_validator(input_base::VAL_VALOR)
                ->set_description(get_string("coupon_value_description", "local_kopere_pay"))
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("useremail", "local_kopere_pay"))
                ->set_name("email")
                ->add_validator(input_base::VAL_EMAIL)
                ->set_description(get_string("coupon_email_description", "local_kopere_pay"))
        );

        $form->create_submit_input(get_string("savechanges"));
        $return .= $form->close_and_return();

        $return .= "</div>";
        return $return;
    }

    /**
     * Function form_import
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \Exception
     */
    public function form_import() {
        global $DB, $PAGE;

        $course = optional_param("course", 0, PARAM_TEXT);
        $couponlength = get_config("local_kopere_dashboard", "couponlength");
        $couponlength = $couponlength == null ? 8 : $couponlength;

        if (form::check_post()) {
            $coupons = optional_param("coupons", '', PARAM_TEXT);
            $value = optional_param("value", '', PARAM_TEXT);
            $amount = optional_param("amount", '', PARAM_INT);

            $lista = explode("\n", $coupons);

            $erros = [];
            foreach ($lista as $cuponitem) {
                $cupon = strtoupper(html::retira_caracteres_nao_ascii($cuponitem));

                if (strlen($cupon) > 2) {
                    $erro = '';

                    $coupon = (object) [
                        "course" => $course,
                        "key" => strtoupper($cupon),
                        'email' => '',
                        'amount' => $amount,
                        "value" => formater::number_formater($value),
                        'type' => optional_param('type', '', PARAM_TEXT),
                        'time' => time(),
                    ];

                    if (strlen($cupon) != $couponlength) {
                        $erro = 'Precisa ter ' . $couponlength . ' caracteres';
                    }

                    if ($erro == '') {
                        try {
                            $DB->insert_record("local_kopere_pay_coupon", $coupon);
                        } catch (dml_exception) {
                            $erros[] = '"' . $cuponitem . '" Motivo: Falha ao inserir no banco de dados';
                        }
                    } else {
                        $erros[] = '"' . $cuponitem . '" Motivo: ' . $erro;
                    }
                }
            }

            if (count($erros) >= 1) {
                $a = (object) [
                    'count' => count($erros),
                    'errors' => implode('<br/>', $erros),
                ];
                message::schedule_message_danger(get_string('coupon_import_errors_html', "local_kopere_pay", $a));
            }

            message::schedule_message_success(get_string("coupons_registered", "local_kopere_pay"));
            header::location('?classname=coupons&method=dashboard&course=' . $course);
        }

        $PAGE->set_title(get_string("coupon_creating_new", "local_kopere_pay"));

        $return = menu::tabs(3);
        $return .= "<div class=\"kopere_dashboard-card\">";

        $return .= message::info(get_string("coupon_put_one_per_line_html", "local_kopere_pay", $couponlength));

        $form = new form('?classname=coupons&method=form_import');

        $form->create_hidden_input('course', $course);
        $form->create_hidden_input('type', "value");

        $form->add_input(
            input_textarea::new_instance()
                ->set_title(get_string("coupon_list", "local_kopere_pay"))
                ->set_name('coupons')
                ->set_required()
                ->set_description(get_string("coupon_list_description", "local_kopere_pay", $couponlength))
        );

        $form->add_input(
            input_text::new_instance()
                ->set_type("number")
                ->set_title(get_string("summary_table_quantity", "local_kopere_pay"))
                ->set_name('amount')
                ->set_required()
                ->add_validator(input_base::VAL_INT)
                ->set_description(get_string('coupon_quantity_available_description', "local_kopere_pay"))
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("payments_valor", "local_kopere_pay"))
                ->set_name("value")
                ->set_required()
                ->add_validator(input_base::VAL_VALOR)
                ->set_description(get_string('coupon_value_pay_description', "local_kopere_pay"))
        );

        $form->create_submit_input(get_string("coupons_create", "local_kopere_pay"));
        $return .= $form->close_and_return();

        $return .= "</div>";
        return $return;
    }

    /**
     * Function delete
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public function delete() {
        global $DB, $PAGE;

        $PAGE->set_title(get_string("coupon_delete", "local_kopere_pay"));
        $return = "";
        $return .= "<div class=\"kopere_dashboard-card\">";

        $couponid = optional_param('couponid', 0, PARAM_INT);
        $course = optional_param('course', 0, PARAM_TEXT);

        $coupons = $DB->get_record("local_kopere_pay_coupon", ['id' => $couponid]);
        pay_header::notfound_null($coupons, get_string("coupon_not_found", "local_kopere_pay"));

        $status = optional_param("status", false, PARAM_TEXT);
        if ($status) {
            $DB->delete_records("local_kopere_pay_coupon", ['id' => $couponid]);

            message::schedule_message_success(get_string("coupon_deleted_successfully", "local_kopere_pay"));
            header::location('?classname=coupons&method=dashboard&course=' . $course);
        } else {
            $urlyes = "?classname=coupons&method=delete&couponid={$coupons->id}&course=$course&status=sim";
            $urlno = "?classname=coupons&method=dashboard&course=$course";
            $return .= "<div class=\"kopere_dashboard-card\">
                      <p>Deseja realmente excluir o Cupom <strong>{$coupons->uniquekey}</strong>?</p>
                      <div>
                          <a class=\"btn btn-danger\" href=\"{$urlyes}\">Sim</a> -
                          <a class=\"btn btn-primary\" href=\"{$urlno}\">Não</a>
                      </div>
                  </div>";
        }

        $return .= "</div>";
        return $return;
    }
}
