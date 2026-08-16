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
 * transaction.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found
// phpcs:disable moodle.Commenting.InlineComment.NotCapital
namespace local_kopere_pay;

use coding_exception;
use dml_exception;
use local_kopere_dashboard\html\button;
use local_kopere_dashboard\html\data_table;
use local_kopere_pay\html\form;
use local_kopere_dashboard\html\table_header_item;
use local_kopere_dashboard\report\user_field;
use local_kopere_dashboard\util\datatable_search_util;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\export;
use local_kopere_dashboard\util\header;
use local_kopere_pay\shopping\pay_header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\meios\IMeio;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_history;
use local_kopere_pay\vo\local_kopere_pay_enrollment;

/**
 * Class transaction
 */
class transaction extends base {

    /**
     * Function dashboard
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public static function dashboard() {
        global $DB, $PAGE;

        $export = optional_param('export', false, PARAM_TEXT);
        $id = optional_param('course', 0, PARAM_TEXT);

        if ($id) {
            $course = course_util::find($id);

            $PAGE->set_title(get_string("transactions_of_course", "local_kopere_pay", $course->fullname));

            $return = menu::tabs(2);

            export::header($export, get_string("transactions_export_list_of_course", "local_kopere_pay", $course->fullname));

        } else {
            $PAGE->set_title(get_string("transactions_all", "local_kopere_pay"));
            $return = "";

            export::header($export, get_string("transactions_export_list", "local_kopere_pay"));
        }

        $return .= '<div class="kopere_dashboard-card table-responsive">';

        $table = new data_table();
        $table->set_is_export(true);
        $table->add_header('#', 'id', table_header_item::TYPE_INT);
        $table->add_header(get_string("username", "local_kopere_pay"), 'fullname');
        $table->add_header(get_string("useremail", "local_kopere_pay"), 'email');
        if (!$id) {
            $table->add_header(get_string("summary_table_course", "local_kopere_pay"), 'course');
        }
        $table->add_header(get_string("transaction_payment_method", "local_kopere_pay"), 'method');
        $table->add_header(get_string("payments_valor", "local_kopere_pay"), "value");
        $table->add_header(get_string("settings_pay_coupon", "local_kopere_pay"), 'coupon');
        $table->add_header(get_string("transaction_last_interaction", "local_kopere_pay"), 'last');
        $table->add_header(get_string("transaction_last_status", "local_kopere_pay"), 'status');

        if ($export) {
            $return .= $table->print_header("", true, true);
            $sql = (new transaction())->load_all_transaction(true);
            $result = $DB->get_records_sql($sql);
            $result = self::column_transaction($result);

            $return .= $table->set_row($result, "", true);
            $return .= $table->close(false, null, true);

            export::close();
        }

        $table->set_ajax_url("view-ajax.php?classname=transaction&method=load_all_transaction&course={$id}");
        $table->set_click_redirect(
            '?classname=transaction&method=detail&enrollment={id}&course={courseid}',
            [
                'id',
                'courseid',
            ]
        );
        $return .= $table->print_header("", true, true);

        if (!$id) {
            // If this is within the course, disable course order and Status.
            $return .= $table->close(
                true,
                [
                    "order" => [[7, "desc"]],
                    "columnDefs" => [
                        (object) ["targets" => [3, 8], "orderable" => false],
                    ],
                ],
                true
            );
            // 'order:[[7,"desc"]]', 'columnDefs: [{targets:[3,8],orderable:false}]'.
        } else {
            // If it is NOT within the course, disable order only for Status and hide the course.
            $return .= $table->close(
                true,
                [
                    "order" => [[6, "desc"]],
                    "columnDefs" => [
                        (object) ["targets" => [7], "orderable" => false],
                        (object) ["targets" => [3], "visible" => false],
                    ],
                ],
                true
            );
            // 'order:[[6,"desc"]]', 'columnDefs: [{targets:[7],orderable:false},{targets:[3],visible:false}]'.
        }

        $return .= "</div>";
        return $return;
    }

    /**
     * Function load_all_transaction
     *
     * @param $return
     * @return array|string|string[]
     * @throws coding_exception
     * @throws \Exception
     */
    public function load_all_transaction($return = false) {
        $course = optional_param("course", 0, PARAM_TEXT);
        $coursewhere = $course ? "AND kpm.course = '{$course}'" : '';

        $columns = [
            'kpm.id',
            'u.firstname',
            'u.email',
            'kpm.course',
            'kpm.method',
            'kpm.value',
            'kpm.lasttime',
            'kpm.coupon',
            'u.lastname',
        ];
        $search = new datatable_search_util($columns);

        $usernamefields = user_field::get_all_user_name_fields(true, 'u');
        $sql = "
               SELECT CONCAT(kpm.id, u.id) AS unik,
                      {[columns]},
                      {$usernamefields}
                 FROM {local_kopere_pay_enrollment} kpm
                 JOIN {user}                        u   ON kpm.userid = u.id
                WHERE u.id      > 1
                  AND u.deleted = 0
                  {$coursewhere}";
        if ($return) {
            return str_replace("{[columns]}", implode(", ", $columns), $sql);
        }

        $search->execute_sql_and_return(
            $sql, "GROUP BY kpm.id, u.firstname, u.email, u.lastname, {$usernamefields}",
            null,
            'local_kopere_pay\transaction::column_transaction',
            'kpm.id'
        );
        return '';
    }

    /**
     * Function column_transaction
     *
     * @param $result
     * @return mixed
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function column_transaction($result) {
        $course = optional_param("course", 0, PARAM_TEXT);

        foreach ($result as $key => $row) {
            $result[$key] = self::column_transaction_line($row, $course);
        }

        return $result;
    }

    /**
     * Function column_transaction_line
     *
     * @param $row
     * @param $course
     * @return mixed
     * @throws dml_exception
     */
    public static function column_transaction_line($row, $course = null) {
        global $DB;
        if (file_exists(__DIR__ . "/meios/{$row->method}.php")) {
            require_once(__DIR__ . "/meios/{$row->method}.php");

            /** @var IMeio $class */
            $class = "local_kopere_pay\\meios\\{$row->method}";

            $sql = "SELECT * FROM {local_kopere_pay_history} WHERE enrollmentid = :enrollmentid ORDER BY id DESC LIMIT 1";
            $historico = $DB->get_record_sql($sql, ['enrollmentid' => $row->id]);

            $row->status = $class::get_status($historico);
            $row->method = $class::get_name()['public_name'];
        } else {
            $row->status = "";
            $row->method = "";
        }
        $row->fullname = fullname($row);
        $row->last = userdate($row->lasttime, "%d/%m/%Y %H:%M");
        $row->courseid = $row->course;
        if (!$course) {
            $row->course = $DB->get_field('course', 'fullname', ['id' => $row->course]);
        }

        return $row;
    }

    /**
     * Function detail
     *
     * @return string
     * @throws \Exception
     */
    public function detail() {
        global $DB, $CFG, $PAGE;

        $enrollmentid = optional_param('enrollment', 0, PARAM_INT);

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid]);
        pay_header::notfound_null($enrollment, get_string('transaction_enrolment_not_found', "local_kopere_pay"));

        $PAGE->set_title(get_string("transaction_details_reference", "local_kopere_pay", "M-{$enrollment->id}"));
        $return = menu::tabs(2);

        $user = $DB->get_record('user', ['id' => $enrollment->userid]);
        $course = course_util::find($enrollment->course);

        $return .= '<div class="row">
                  <div class="col-lg-6">
                      <div class="kopere_dashboard-card">';

        $return .= "<h2>" . get_string("transaction_details_title", "local_kopere_pay") . "</h2>";

        $form = new form(false);

        $form->print_row(get_string('transaction_reference_code', "local_kopere_pay"), 'M-' . $enrollment->id);

        if ($enrollment->coupon) {
            $form->print_row(get_string('settings_pay_coupon', "local_kopere_pay"), strtoupper($enrollment->coupon));
        }
        $form->print_row('Valor', 'R$ ' . $enrollment->value);

        /** @var IMeio $class */
        $class = "local_kopere_pay\\meios\\{$enrollment->method}";

        $form->print_row('Meio de pagamento', $class::get_name()['public_name']);

        $link = "<a href='?classname=courses&method=details&courseid={$enrollment->course}'
                    target='_blank'>{$course->fullname}</a>";
        $form->print_row(get_string('summary_table_course', "local_kopere_pay"), $link);

        if (enroll_util::enrolled($course, $user)) {
            $form->print_row(
                get_string('transaction_enrolment_status', "local_kopere_pay"),
                '<span style="color: #35c659">Matriculado</span> - ' .
                $form->return .= button::delete(
                    get_string('transaction_remove_enrolment', "local_kopere_pay"),
                    "?classname=transaction&method=enrol&status=remove&enrollment={$enrollment->id}", "", false, true
                )
            );
        } else {
            $form->print_row(
                get_string('transaction_enrolment_status', "local_kopere_pay"),
                get_string('transaction_not_enrolled_html', "local_kopere_pay") . ' - ' .
                $form->return .= button::add(
                    get_string('transaction_add_enrolment', "local_kopere_pay"),
                    "?classname=transaction&method=enrol&status=add&enrollment={$enrollment->id}", "", false, true
                )
            );
        }

        $form->return .= '        </div>
                  </div>
                  <div class="col-lg-6">
                      <div class="kopere_dashboard-card">';

        $form->return .= "<h2>" . get_string("transaction_buyer_details_title", "local_kopere_pay") . "</h2>";

        $link = "<a href='?classname=users&method=details&userid={$user->id}' target='_blank'>" . fullname($user) . "</a>";
        $form->print_row('Nome', $link);
        $form->print_row('E-mail', $user->email);
        if (strlen($user->phone1) > 4) {
            $form->print_row('Telefone', $user->phone1);
        }
        if (strlen($user->phone2) > 4) {
            $form->print_row('Celular', $user->phone2);
        }

        $profile = (array) profile_user_record($user->id);
        if (isset($profile['cpf'])) {
            $form->print_row('CPF', $profile['cpf']);
        }
        if (isset($profile['birth'])) {
            $form->print_row('Data de Nascimento', $profile['birth']);
        }

        $link = button::edit(
            get_string('transaction_view_user_profile', "local_kopere_pay"),
            "{$CFG->wwwroot}/user/profile.php?id={$user->id}", "", false, true
        );
        $form->print_row('', $link);

        $return .= $form->close_and_return();

        $return .= '        </div>
                  </div>
              </div>
              <div class="kopere_dashboard-card">';

        $return .= "<h2>" . get_string("transaction_status_history_title", "local_kopere_pay") . "</h2>";

        $historicos = $DB->get_records('local_kopere_pay_history', ['enrollmentid' => $enrollment->id], 'id ASC');

        $return .= "
            <table class='table table-hover' width='100%'>
                <tr class=''>
                    <th class='text-center'>" . get_string("transaction_history_at", "local_kopere_pay") . "</th>
                    <th class='text-center'>" . get_string('payments_status', "local_kopere_pay") . "</th>
                </tr>";
        /** @var local_kopere_pay_history $historico */
        foreach ($historicos as $historico) {
            $userdate = userdate($historico->time, "%d/%m/%Y %H:%M");
            $return .= "
                <tr>
                    <td>{$userdate}</td>
                    <td>{$class::get_status($historico)}</td>
                </tr>";
        }
        $return .= "</table>";

        $return .= '</div>';
        return $return;
    }

    /**
     * Function enrol
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public function enrol() {
        global $DB;

        $enrollmentid = optional_param('enrollment', 0, PARAM_INT);
        $status = optional_param('status', false, PARAM_TEXT);

        /** @var local_kopere_pay_enrollment $enrollment */
        $enrollment = $DB->get_record('local_kopere_pay_enrollment', ['id' => $enrollmentid]);
        pay_header::notfound_null($enrollment, get_string('transaction_enrolment_not_found', "local_kopere_pay"));

        $user = $DB->get_record('user', ['id' => $enrollment->userid]);
        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $enrollment->course]);

        if ($enrollment->course[0] == 'c') {
            if ($status == 'add') {
                enroll_util::cohort_enrol($enrollment->course, $user->id);
                enrollment_util::changue_status($enrollment, enrollment_util::PAID);

                $returned = ['status' => 'enrol'];
                message::schedule_message_success(get_string("transaction_enrolment_added", "local_kopere_pay"));
            } else {
                enroll_util::cohort_unenrol($enrollment->course, $user->id);
                enrollment_util::changue_status($enrollment, enrollment_util::WAITING);

                $returned = ['status' => 'unenrol'];
                message::schedule_message_success(get_string("transaction_enrolment_removed", "local_kopere_pay"));
            }
        } else {
            $course = $DB->get_record('course', ['id' => $enrollment->course]);

            if ($status == 'add') {
                $timeend = timeend_util::calculate_day($detalhe);
                enroll_util::enrol($course, $user, time(), $timeend);
                enrollment_util::changue_status($enrollment, enrollment_util::PAID);

                $returned = ['status' => 'enrol'];
                message::schedule_message_success(get_string("transaction_enrolment_added", "local_kopere_pay"));
            } else {
                enroll_util::unenrol($course, $user);
                enrollment_util::changue_status($enrollment, enrollment_util::WAITING);

                $returned = ['status' => 'unenrol'];
                message::schedule_message_success(get_string("transaction_enrolment_removed", "local_kopere_pay"));
            }
        }

        $returned['modo'] = 'manual';
        $returned['userid'] = $user->id;
        local_kopere_pay_history::add_history($enrollment->id, null, $returned);

        header::location("?classname=transaction&method=detail&enrollment={$enrollment->id}&course={$enrollment->course}");
    }
}
