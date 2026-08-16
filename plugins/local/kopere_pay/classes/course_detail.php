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
 * course_detail.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_base;
use local_kopere_pay\html\inputs\input_htmleditor;
use local_kopere_pay\html\inputs\input_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\output\layout;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\meios\IMeio;
use local_kopere_pay\util\profile_field;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class course_detail
 */
class course_detail extends base {

    /**
     * Function details
     *
     * @return string
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \dml_exception
     */
    public function details() {
        global $DB, $CFG, $OUTPUT, $PAGE;

        $id = optional_param('course', 0, PARAM_TEXT);
        $course = course_util::find($id);

        $PAGE->set_title(get_string("course_detail_breadcrumb", "local_kopere_pay", $course->fullname));

        $return = menu::tabs(0);
        $return .= "<div class=\"kopere_dashboard-card course_detail-details\">";

        /** @var local_kopere_pay_detail $koperepaydetalhe */
        $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id]);

        if (!enroll_util::status_enrol_manual($course)) {
            $url = "{$CFG->wwwroot}/enrol/instances.php?id={$course->id}";
            $return .= message::danger(get_string('manual_enrolment_disabled_html', "local_kopere_pay", $url));
        } else if ($koperepaydetalhe == null) {
            $return .= message::warning(get_string("course_without_defined_data", "local_kopere_pay"));
        } else if ($koperepaydetalhe->status == 'fechado') {
            $return .= message::warning(get_string("course_closed_for_enrolment", "local_kopere_pay"));
        } else {
            $data = [
                "url_enrollment" => "{$CFG->wwwroot}/local/kopere_pay/?id={$koperepaydetalhe->course}",
                "url_page" => "{$CFG->wwwroot}/local/kopere_pay/view.php?id={$koperepaydetalhe->course}",
                "builder_enable" => config::get_key("builder_enable_{$koperepaydetalhe->course}"),
            ];
            $return .= $OUTPUT->render_from_template('local_kopere_pay/course_detail/form-enrollment', $data);

            if (!$CFG->autologinguests) {
                $return .= $OUTPUT->render_from_template("local_kopere_pay/not-autologinguests", []);
            }
            if (!in_array($CFG->theme, ["boost_magnific", "degrade", "eadtraining"])) {
                $return .= $OUTPUT->render_from_template("local_kopere_pay/not-theme", []);
            }

            $modelos = [
                [
                    "buttons" => [
                        'modelo1-01.svg',
                        'modelo1-02.svg',
                        'modelo1-03.svg',
                        'modelo1-04.svg',
                        'modelo1-05.svg',
                        'modelo1-06.svg',
                    ],
                ],
                [
                    "buttons" => [
                        'modelo2-01.svg',
                        'modelo2-02.svg',
                        'modelo2-03.svg',
                        'modelo2-04.svg',
                        'modelo2-05.svg',
                        'modelo2-06.svg',
                        'modelo2-07.svg',
                        'modelo2-08.svg',
                    ],
                ],
                [
                    "buttons" => [
                        'modelo3-01.svg',
                        'modelo3-02.svg',
                        'modelo3-03.svg',
                        'modelo3-04.svg',
                        'modelo3-05.svg',
                        'modelo3-06.svg',
                        'modelo3-07.svg',
                        'modelo3-08.svg',
                        'modelo3-09.svg',
                        'modelo3-10.svg',
                    ],
                ],
            ];
            $data["modelos"] = $modelos;
            $PAGE->requires->strings_for_js(["copy", "copied"], "local_kopere_pay");
            $PAGE->requires->js_call_amd('local_kopere_pay/buttons', 'init', []);
            $return .= $OUTPUT->render_from_template('local_kopere_pay/course_detail/buttons', $data);
        }

        $return .= "</div>";
        return $return;
    }

    /**
     * Function changue
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function changue() {
        global $DB, $PAGE;

        $courseid = optional_param('course', 0, PARAM_TEXT);
        $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $courseid]);

        $PAGE->set_title('Kopere Pay', '?classname=dashboard&method=start');
        $return = "";

        if (!config::get_key_int('form_monthly_fee')) {
            header::location("?classname=dashboard&method=start");
        }

        $type = optional_param("type", false, PARAM_TEXT);
        if ($type) {
            if ($type == 'mensalidade') {
                $koperepaydetalhe->charge = 'mensalidade';
                $DB->update_record('local_kopere_pay_detail', $koperepaydetalhe);

                header::location("?classname=course_detail&method=edit&course={$courseid}");
            } else if ($type == "unico") {
                $koperepaydetalhe->charge = "unico";
                $DB->update_record("local_kopere_pay_detail", $koperepaydetalhe);

                header::location("?classname=course_detail&method=edit&course={$courseid}");
            }
        }

        $return .= message::info(
            get_string(
                'course_charge_monthly_info_html', "local_kopere_pay",
                "?classname=course_detail&method=changue&course={$courseid}&type=mensalidade"
            )
        );

        $return .= message::info(
            get_string(
                "course_charge_one_time_info_html", "local_kopere_pay",
                "?classname=course_detail&method=changue&course={$courseid}&type=unico"
            )
        );

        $return .= "</div>";
        return $return;
    }

    /**
     * Function edit
     *
     * @return string
     * @throws \coding_exception
     */
    public function edit() {
        global $DB, $CFG, $PAGE;

        $formmensalidade = config::get_key_int('form_monthly_fee');

        $id = optional_param('course', 0, PARAM_TEXT);
        $course = course_util::find($id);

        $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id]);

        $PAGE->set_title(get_string("course_editing_details_of", "local_kopere_pay", $course->fullname));

        $return = menu::tabs(1);
        $return .= "<div class=\"kopere_dashboard-card course_detail-edit\">";

        if (!enroll_util::status_enrol_manual($course)) {
            $url = "{$CFG->wwwroot}/enrol/instances.php?id={$course->id}";
            $return .= message::danger(get_string('manual_enrolment_disabled_html', "local_kopere_pay", $url));
        } else if ($koperepaydetalhe) {

            if (config::get_key_int('form_monthly_fee')) {
                $a = (object) [
                    'charge' => $koperepaydetalhe->charge,
                    'url' => '?classname=course_detail&method=changue&course=' . $course->id,
                ];
                $return .= message::info(
                    get_string('course_current_charge_html', "local_kopere_pay", $a)
                );
            }

            $form = new form('?classname=course_detail&method=edit_save');

            $form->create_hidden_input('course', $id);

            $values = [
                ["key" => 'aberto', "value" => get_string('course_status_open_for_enrolment', "local_kopere_pay")],
                ["key" => 'fechado', "value" => get_string('course_status_closed_for_enrolment', "local_kopere_pay")],
            ];
            $form->add_input(
                input_select::new_instance()
                    ->set_title(get_string("settings_status", "local_kopere_pay"))
                    ->set_name('status')
                    ->set_values($values)
                    ->set_value($koperepaydetalhe->status)
                    ->set_description(get_string('course_status_description', "local_kopere_pay"))
            );

            $form->add_input(
                input_text::new_instance()
                    ->set_title(get_string("settings_value", "local_kopere_pay"))
                    ->set_name('price')
                    ->set_value($koperepaydetalhe->price)
                    ->set_required()
                    ->add_validator(input_base::VAL_VALOR)
                    ->set_description(get_string('course_price_description', "local_kopere_pay"))
            );
            if (formater::price_to_float($koperepaydetalhe->price) <= 1) {
                $form->return .= message::warning(
                    get_string('course_price_below_minimum_warning', "local_kopere_pay")
                );
            }

            $values = [
                ['invisivel', get_string('catalog_hidden_in_catalog', "local_kopere_pay")],
                ['visivel', get_string('catalog_visible_in_catalog', "local_kopere_pay")],
            ];
            $form->add_input(
                input_select::new_instance()
                    ->set_title(get_string("dashboard_catalog_course", "local_kopere_pay"))
                    ->set_value($koperepaydetalhe->portfolio)
                    ->set_name('portfolio')
                    ->set_description(
                        get_string(
                            'course_catalog_description_html', "local_kopere_pay",
                            '?classname=catalog_course&method=dashboard'
                        )
                    )
                    ->set_values($values, 0, 1)
            );

            if ($koperepaydetalhe->charge == 'mensalidade') {
                $form->add_input(
                    input_text::new_instance()
                        ->set_type("number")
                        ->set_title(get_string('course_monthly_charge_months_title', "local_kopere_pay"))
                        ->set_name('days')
                        ->set_value($koperepaydetalhe->days)
                        ->add_validator(input_base::VAL_INT)
                        ->add_extras('min="0" max="24"')
                        ->set_description(
                            get_string('course_monthly_charge_months_description', "local_kopere_pay")
                        )
                );
            } else {
                $form->add_input(
                    input_text::new_instance()
                        ->set_type("number")
                        ->set_title(get_string('course_unenrol_days_title', "local_kopere_pay"))
                        ->set_name('days')
                        ->set_value($koperepaydetalhe->days)
                        ->add_validator(input_base::VAL_INT)
                        ->set_description(
                            get_string('course_unenrol_days_description', "local_kopere_pay")
                        )
                );
                $form->add_input(
                    input_text::new_instance()
                        ->set_title(get_string('course_unenrol_date_title', "local_kopere_pay"))
                        ->set_name('date')
                        ->set_value($koperepaydetalhe->date)
                        ->add_mask(input_base::MASK_DATA)
                        ->set_description(
                            get_string('course_unenrol_date_description', "local_kopere_pay")
                        )
                );
            }

            $expiredaction = [
                0 => get_string('course_expired_action_unenrol', "local_kopere_pay"),
                1 => get_string('course_expired_action_keep', "local_kopere_pay"),
                2 => get_string('course_expired_action_disable', "local_kopere_pay"),
                3 => get_string('course_expired_action_disable_and_remove_roles', "local_kopere_pay"),
            ];
            if ($course->isCoorte) {
                $value = get_config('enrol_cohort', 'unenrolaction');
                $a = (object) [
                    'action' => $expiredaction[intval($value)],
                    'url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingscohort',
                ];
                $form->print_row(
                    get_string('course_expired_action_title', "local_kopere_pay"),
                    get_string('course_expired_action_info_html', "local_kopere_pay", $a)
                );
            } else {
                $value = get_config('enrol_manual', 'expiredaction');
                $a = (object) [
                    'action' => $expiredaction[intval($value)],
                    'url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsmanual',
                ];
                $form->print_row(
                    get_string('course_expired_action_title', "local_kopere_pay"),
                    get_string('course_expired_action_info_html', "local_kopere_pay", $a)
                );
            }
            $form->add_input(
                input_text::new_instance()
                    ->set_type("number")
                    ->set_title(get_string('course_students_limit_title', "local_kopere_pay"))
                    ->set_name('students')
                    ->add_validator(input_base::VAL_INT)
                    ->set_description(
                        get_string('course_students_limit_description', "local_kopere_pay")
                    )
            );

            $extrafields = get_config("local_kopere_dashboard", "extra_fields");
            if (isset($extrafields[1])) {
                $sql = "
                    SELECT id,name
                      FROM {user_info_category}
                     WHERE name NOT LIKE :categoryname
                       AND id   NOT IN ({$extrafields})
                  ORDER BY sortorder ASC";
                $categorys = $DB->get_records_sql($sql, ["categoryname" => profile_field::$categoryname]);
            } else {
                $categorys = $DB->get_records_select(
                    "user_info_category",
                    "name NOT LIKE :categoryname",
                    ["categoryname" => profile_field::$categoryname],
                    "sortorder ASC", "id,name"
                );
            }
            $categorys = array_merge([["id" => 0, "name" => get_string("none_category", "local_kopere_pay")]], (array) $categorys);
            $form->add_input(
                input_select::new_instance()
                    ->set_title('Campo extra de perfil')
                    ->set_value(@$koperepaydetalhe->fieldcategory)
                    ->set_name('fieldcategory')
                    ->set_description(
                        get_string('course_profile_extra_field_description', "local_kopere_pay")
                    )
                    ->set_values($categorys, "id", "name")
            );

            $form->add_input(
                input_htmleditor::new_instance()
                    ->set_title(get_string("course_extra_message_title", "local_kopere_pay"))
                    ->set_name('extra')
                    ->set_value($koperepaydetalhe->extra)
            );

            $listameios = payment_method::list_meios();
            foreach ($listameios as $meio) {
                if ($meio['enable'] && $meio['escolha']) {
                    /** @var IMeio $class */
                    $class = "local_kopere_pay\\meios\\" . "{$meio['class']}";
                    $class::details_edit_form($form, $koperepaydetalhe);
                }
            }

            $form->create_submit_input(get_string('savechanges'));

            $return .= $form->close_and_return();
        } else {

            $type = optional_param("type", false, PARAM_TEXT);
            if (!$formmensalidade) {
                $type = 'unico';
            }

            if ($type) {
                $data = new \stdClass();
                $data->course = $id;
                $data->date = "";
                $data->students = 0;
                $data->days = 0;
                $data->price = "0,00";
                $data->status = "aberto";

                if ($type == "mensalidade") {
                    $data->charge = "mensalidade";
                    $DB->insert_record("local_kopere_pay_detail", $data);

                    header::location("?classname=course_detail&method=edit&course={$id}");
                } else if ($type == "unico") {
                    $data->charge = "unico";
                    $DB->insert_record("local_kopere_pay_detail", $data);

                    header::location("?classname=course_detail&method=edit&course={$id}");
                }
            }

            $return .= message::info(
                get_string(
                    "course_charge_monthly_info_compact_html", "local_kopere_pay",
                    "?classname=course_detail&method=edit&course={$id}&type=mensalidade"
                )
            );

            $return .= message::info(
                get_string(
                    "course_charge_one_time_info_compact_html", "local_kopere_pay",
                    "?classname=course_detail&method=edit&course={$id}&type=unico"
                )
            );
        }

        $return .= "</div>";
        return $return;
    }

    /**
     * Function edit_save
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function edit_save() {
        global $DB;

        $cursosid = optional_param('course', 0, PARAM_TEXT);

        $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $cursosid]);
        $detalhe = local_kopere_pay_detail::create_by_object($koperepaydetalhe);

        if ($detalhe->charge == 'mensalidade') {
            if ($detalhe->days < 2) {
                $detalhe->days = 2;
            }
            if ($detalhe->days > 24) {
                $detalhe->days = 24;
            }
        }

        $DB->update_record('local_kopere_pay_detail', $detalhe);

        message::schedule_message_success(get_string("saved_successfully", "local_kopere_pay"));
        header::location("?classname=course_detail&method=edit&course={$cursosid}");
    }
}
