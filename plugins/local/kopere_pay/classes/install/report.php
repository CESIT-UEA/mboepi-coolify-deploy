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
 * report.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\install;

use dml_exception;
use local_kopere_dashboard\html\table_header_item;
use local_kopere_dashboard\report\user_field;
use local_kopere_dashboard\vo\local_kopere_dashboard_rcat;
use local_kopere_dashboard\vo\local_kopere_dashboard_reprt;
use stdClass;

/**
 * Class report
 */
class report {

    /**
     * Function create_categores
     *
     * @return void
     * @throws \Exception
     */
    public function create_categores() {
        $reportcat = local_kopere_dashboard_rcat::create_by_default();
        $reportcat->title = "Relatório de vendas";
        $reportcat->type = "sales";
        $reportcat->image = 'local/kopere_pay/assets/reports/sales.svg';
        $reportcat->enable = 1;
        $this->report_cat_insert($reportcat);
    }

    /**
     * Function create_reports
     *
     * @return void
     * @throws \Exception
     */
    public function create_reports() {
        global $DB;

        $reportcatid = $DB->get_field("local_kopere_dashboard_rcat", "id", ["type" => "sales"]);
        $DB->delete_records("local_kopere_dashboard_reprt", ["reportcatid" => $reportcatid]);

        $usernamefields = user_field::get_all_user_name_fields(true, "u");

        $report = local_kopere_dashboard_reprt::create_by_default();
        $report->reportcatid = $reportcatid;
        $report->reportkey = "sales-pay-1";
        $report->title = "Todas as vendas pelo Kopere Pay";
        $report->reportsql = "
               SELECT pm.id, pm.course, pm.method, pm.value, pm.lasttime, u.email,
                      {$usernamefields}
                 FROM {local_kopere_pay_enrollment} pm
                 JOIN {user} u ON pm.userid = u.id
                WHERE u.id > 1
                  AND u.deleted = 0
             ORDER BY u.firstname";
        $report->columns = [
            $this->add_header("#", "id", table_header_item::TYPE_INT),
            $this->add_header("[[courses_student_name]]", "fullname"),
            $this->add_header("E-mail", "email"),
            $this->add_header("Curso", "course"),
            $this->add_header("Meio", "method"),
            $this->add_header("Valor", "value"),
            $this->add_header("Última interação em", "last"),
            $this->add_header("Último Status", "status"),
        ];
        $report->foreach = "local_kopere_pay\\transaction::column_transaction_line";
        $report->columns = json_encode(["columns" => $report->columns]);
        $this->report_insert($report);

        $report = local_kopere_dashboard_reprt::create_by_default();
        $report->reportcatid = $reportcatid;
        $report->reportkey = "sales-pay-2";
        $report->title = "Todas as vendas pelo Kopere Pay com cupom de desconto";
        $report->reportsql = "
               SELECT pm.id, pm.course, pm.method, pm.value, pm.lasttime, pm.coupon, u.email,
                      {$usernamefields}
                 FROM {local_kopere_pay_enrollment} pm
                 JOIN {user} u ON pm.userid = u.id
                WHERE u.id > 1
                  AND u.deleted = 0
                  AND pm.coupon LIKE '%__'
             ORDER BY u.firstname";
        $report->columns = [
            $this->add_header("#", "id", table_header_item::TYPE_INT),
            $this->add_header("[[courses_student_name]]", "fullname"),
            $this->add_header("E-mail", "email"),
            $this->add_header("Curso", "course"),
            $this->add_header("Meio", "method"),
            $this->add_header("Valor", "value"),
            $this->add_header("Cupom", "coupon"),
            $this->add_header("Última interação em", "last"),
            $this->add_header("Último Status", "status"),
        ];
        $report->foreach = "local_kopere_pay\\transaction::column_transaction_line";
        $report->columns = json_encode(["columns" => $report->columns]);
        $this->report_insert($report);

        $report = local_kopere_dashboard_reprt::create_by_default();
        $report->reportcatid = $reportcatid;
        $report->reportkey = "sales-pay-3";
        $report->title = "Todas as vendas pagas pelo Kopere Pay";
        $report->reportsql = "
               SELECT pm.id, pm.course, pm.method, pm.value, pm.lasttime, pm.coupon, u.email,
                      {$usernamefields}
                 FROM {local_kopere_pay_enrollment} pm
                 JOIN {user} u ON pm.userid = u.id
                WHERE u.id > 1
                  AND u.deleted = 0
                  AND pm.status = 'paid'
             ORDER BY u.firstname";
        $report->columns = [
            $this->add_header("#", "id", table_header_item::TYPE_INT),
            $this->add_header("[[courses_student_name]]", "fullname"),
            $this->add_header("E-mail", "email"),
            $this->add_header("Curso", "course"),
            $this->add_header("Meio", "method"),
            $this->add_header("Valor", "value"),
            $this->add_header("Cupom", "coupon"),
            $this->add_header("Última interação em", "last"),
            $this->add_header("Último Status", "status"),
        ];
        $report->foreach = "local_kopere_pay\\transaction::column_transaction_line";
        $report->columns = json_encode(["columns" => $report->columns]);
        $this->report_insert($report);
    }

    /**
     * Function report_cat_insert
     *
     * @param $reportcat
     * @return void
     * @throws dml_exception
     */
    private function report_cat_insert($reportcat) {
        global $DB;

        $koperereportcatexist = $DB->record_exists("local_kopere_dashboard_rcat", ["type" => $reportcat->type]);
        if (!$koperereportcatexist) {
            $DB->insert_record("local_kopere_dashboard_rcat", $reportcat);
        }
    }

    /**
     * Function report_insert
     *
     * @param $report
     * @return void
     * @throws dml_exception
     */
    private function report_insert($report) {
        global $DB;

        $koperereportsexist = $DB->record_exists("local_kopere_dashboard_reprt", ["reportkey" => $report->reportkey]);
        if (!$koperereportsexist) {
            $DB->insert_record("local_kopere_dashboard_reprt", $report);
        }
    }

    /**
     * Function add_header
     *
     * @param        $title
     * @param null $key
     * @param string $type
     * @param null $funcao
     * @param null $styleheader
     * @param null $stylecol
     *
     * @return stdClass
     */
    private function add_header(
        $title, $key = null, $type = table_header_item::TYPE_TEXT, $funcao = null,
        $styleheader = null, $stylecol = null
    ) {
        $column = new stdClass();
        $column->key = $key;
        $column->type = $type;
        $column->title = $title;
        $column->funcao = $funcao;
        $column->style_header = $styleheader;
        $column->style_col = $stylecol;

        return $column;
    }

    /**
     * Function atualiza
     *
     * @return void
     */
    public static function atualiza() {
        global $USER, $CFG;

        $plugin = new stdClass();
        require_once("{$CFG->dirroot}/local/kopere_pay/version.php");

        $p = [
            "nome" => fullname($USER),
            "email" => @$USER->email,
            "site" => $CFG->wwwroot,
            "moodle_release" => $CFG->release,
            "kopere_versao" => @$plugin->release,
            "kopere_license" => @$plugin->kopere_license,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://license.eduardokraus.com/admin/validaKopere");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, "KopereDashboard/4.0");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($p, '', '&'));

        curl_exec($ch);
        curl_close($ch);
    }
}
