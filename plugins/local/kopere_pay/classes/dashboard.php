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
 * dashboard.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use local_kopere_dashboard\html\data_table;
use local_kopere_dashboard\output\layout;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\json;
use local_kopere_dashboard\util\message;
use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class dashboard
 */
class dashboard extends base {
    /**
     * Function start
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function start() {
        global $CFG, $OUTPUT, $PAGE;

        data_table::lang();

        $config = get_config("local_kopere_dashboard");
        if (!isset($config->form_monthly_fee)) {
            set_config("form_monthly_fee", 0, "local_kopere_dashboard");
            set_config("form_confirmar", 0, "local_kopere_dashboard");
            set_config("form_pedir_telefone", 0, "local_kopere_dashboard");
            set_config("form_pedirbirth", 0, "local_kopere_dashboard");
            set_config("form_pedir_celular", 0, "local_kopere_dashboard");
            set_config("form_pedir_endereco", 0, "local_kopere_dashboard");
            set_config("form_theme", "base", "local_kopere_dashboard");
            set_config("couponlength", 8, "local_kopere_dashboard");
            set_config("form_ask_accept", '', "local_kopere_dashboard");
        }

        $PAGE->set_title(get_string("dashboard_all_courses", "local_kopere_pay"));

        $tables = [];

        $tables[] = $this->create_table_context(
            get_string("dashboard_select_course", "local_kopere_pay"),
            [
                $this->header('#', 'id', 'width: 20px'),
                $this->header(get_string("dashboard_course_name", "local_kopere_pay"), 'fullname'),
                $this->header(get_string("dashboard_short_name", "local_kopere_pay"), 'shortname'),
                $this->header(get_string("payments_visivel", "local_kopere_pay"), 'visible'),
                $this->header(get_string("payments_valor", "local_kopere_pay"), 'price'),
                $this->header(get_string("payments_status", "local_kopere_pay"), 'status'),
                $this->header(get_string("dashboard_catalog_course", "local_kopere_pay"), 'portfolio'),
            ],
            config::get_key_int("form_monthly_fee") ?
                [$this->header(get_string("dashboard_charge_type", "local_kopere_pay"), 'charge')] : [],
            'view-ajax.php?classname=dashboard&method=load_all_courses',
            '?classname=course_detail&method=details&course={id}'
        );

        if (strpos($CFG->enrol_plugins_enabled, "cohort") !== false) {
            $tables[] = $this->create_table_context(
                get_string("dashboard_select_cohort", "local_kopere_pay"),
                [
                    $this->header('#', 'id', 'width: 20px'),
                    $this->header(get_string("dashboard_cohort_name", "local_kopere_pay"), 'name'),
                    $this->header(get_string('payments_visivel', "local_kopere_pay"), 'visible'),
                    $this->header(get_string("payments_valor", "local_kopere_pay"), 'price'),
                    $this->header(get_string("payments_status", "local_kopere_pay"), 'status'),
                ],
                config::get_key_int('form_monthly_fee') ?
                    [$this->header(get_string("dashboard_charge_type", "local_kopere_pay"), 'charge')] : [],
                'view-ajax.php?classname=dashboard&method=load_all_coorte',
                '?classname=course_detail&method=details&course=c{id}',
                message::info(
                    get_string(
                        "dashboard_manage_cohorts_html", "local_kopere_pay",
                        "{$CFG->wwwroot}/cohort/index.php?contextid=1&showall=1"
                    )
                )
            );
        }

        $a = (object) [
            'manageurl' => "{$CFG->wwwroot}/admin/settings.php?section=manageenrols",
            'moreurl' => 'https://moodle.eduardokraus.com/kopere-pay-agora-com-suporte-a-coortes',
        ];

        return $OUTPUT->render_from_template('local_kopere_pay/dashboard', [
            'tables' => $tables,
            'cohortdisabled' => strpos($CFG->enrol_plugins_enabled, "cohort") === false,
            'cohortdisabledtitle' => get_string("dashboard_cohort_integration_title", "local_kopere_pay"),
            'cohortdisabledmessage' => message::info(
                get_string('dashboard_cohort_integration_html', "local_kopere_pay", $a)
            ),
        ]);
    }

    /**
     * Function load_all_courses
     *
     * @return void
     * @throws \dml_exception
     */
    public function load_all_courses() {
        global $DB;

        $data = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.visible
               FROM {course} c
              WHERE c.id > 1"
        );

        foreach ($data as $course) {
            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $course->id]);

            $course->visible_mustache = $this->render_visible_badge($course->visible);

            if ($detalhe) {
                $course->price = $detalhe->price;
                $course->status = $detalhe->status;
                $course->status_mustache = $this->render_status_badge($detalhe->status);
                $course->charge = $detalhe->charge;
                $course->portfolio = ($detalhe->status == "aberto" && $detalhe->portfolio == "visivel") ?
                    get_string('dashboard_visible_in_catalog_html', "local_kopere_pay") :
                    get_string('dashboard_hidden_in_catalog_html', "local_kopere_pay");
            } else {
                $notdefined = get_string("dashboard_not_defined", "local_kopere_pay");

                $course->price = $notdefined;
                $course->price_mustache = $this->render_not_defined_badge($notdefined);

                $course->status = $notdefined;
                $course->status_mustache = $this->render_not_defined_badge($notdefined);

                $course->charge = $notdefined;
                $course->charge_mustache = $this->render_not_defined_badge($notdefined);

                $course->portfolio = $notdefined;
                $course->portfolio_mustache = $this->render_not_defined_badge($notdefined);
            }
        }

        json::encode($data);
    }

    /**
     * Function load_all_coorte
     *
     * @return void
     * @throws \dml_exception
     */
    public function load_all_coorte() {
        global $DB;

        $data = $DB->get_records_sql("SELECT c.id, c.name, c.visible FROM {cohort} c");

        foreach ($data as $course) {
            /** @var local_kopere_pay_detail $detalhe */
            $detalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $course->id]);

            $course->visible_mustache = $this->render_visible_badge($course->visible);

            if ($detalhe) {
                $course->price = $detalhe->price;
                $course->status = $detalhe->status;
                $course->status_mustache = $this->render_status_badge($detalhe->status);
                $course->charge = $detalhe->charge;
            } else {
                $notdefined = get_string("dashboard_not_defined", "local_kopere_pay");

                $course->price = $notdefined;
                $course->price_mustache = $this->render_not_defined_badge($notdefined);

                $course->status = $notdefined;
                $course->status_mustache = $this->render_not_defined_badge($notdefined);

                $course->charge = $notdefined;
                $course->charge_mustache = $this->render_not_defined_badge($notdefined);
            }
        }

        json::encode($data);
    }

    /**
     * Create a table header context.
     *
     * @param string $title Header title.
     * @param string $key Data key.
     * @param string $style Header CSS style.
     * @return array
     */
    private function header($title, $key, $style = '') {
        return [
            'title' => $title,
            'key' => $key,
            'style' => $style,
            'hasstyle' => $style !== '',
        ];
    }

    /**
     * Create the table context and register DataTables JS.
     *
     * @param string $title Table title.
     * @param array $headers Main headers.
     * @param array $extraheaders Optional extra headers.
     * @param string $ajaxurl Ajax URL.
     * @param string $clickurl Click redirect URL.
     * @param string $messagehtml Optional message HTML.
     * @return array
     */
    private function create_table_context($title, array $headers, array $extraheaders, $ajaxurl, $clickurl, $messagehtml = '') {
        global $PAGE;

        $headers = array_merge($headers, $extraheaders);
        $tableid = 'kopere_pay_' . uniqid();

        $columns = [];
        $columndefs = [];

        foreach ($headers as $key => $header) {
            $columns[] = (object) ['data' => $header['key']];
            $columndefs[] = $header['key'] === 'id' ?
                (object) ['render' => 'numberRenderer', 'targets' => $key] :
                (object) ['render' => 'default', 'targets' => $key];
        }

        $PAGE->requires->js_call_amd('local_kopere_dashboard/dataTables_init', 'init', [
            $tableid, [
                'autoWidth' => false,
                'columns' => $columns,
                'columnDefs' => $columndefs,
                'export_title' => false,
                'ajax' => (object) [
                    'url' => str_replace('view.php', 'view-ajax.php', $ajaxurl),
                    'type' => 'POST',
                ],
            ],
        ]);

        $PAGE->requires->js_call_amd('local_kopere_dashboard/dataTables_init', 'click', [$tableid, ['id'], $clickurl]);

        return [
            'tableid' => $tableid,
            'title' => $title,
            'messagehtml' => $messagehtml,
            'hasmessage' => $messagehtml !== '',
            'headers' => $headers,
        ];
    }

    /**
     * Render visible column badge.
     *
     * @param mixed $visible Visible value.
     * @return string
     * @throws \coding_exception
     */
    private function render_visible_badge($visible) {
        if ($visible === null || $visible === '') {
            return $this->render_not_defined_badge(get_string('dashboard_not_defined', 'local_kopere_pay'));
        }

        if ((int) $visible === 1) {
            return '<span class="badge badge-success">' . s(get_string('visible', 'local_kopere_dashboard')) . '</span>';
        }

        return '<span class="badge badge-danger">' . s(get_string('invisible', 'local_kopere_dashboard')) . '</span>';
    }

    /**
     * Render status column badge.
     *
     * @param string $status Status value.
     * @return string
     * @throws \coding_exception
     */
    private function render_status_badge($status) {
        if ($status === 'aberto') {
            return '<span class="badge badge-success">' .
                s(get_string('course_status_open_for_enrolment', 'local_kopere_pay')) . '</span>';
        }

        if ($status === 'fechado') {
            return '<span class="badge badge-danger">' .
                s(get_string('course_status_closed_for_enrolment', 'local_kopere_pay')) . '</span>';
        }

        return $this->render_not_defined_badge(get_string('dashboard_not_defined', 'local_kopere_pay'));
    }

    /**
     * Render not defined badge.
     *
     * @param string $label Label text.
     * @return string
     */
    private function render_not_defined_badge($label) {
        return '<span class="badge badge-danger">' . s($label) . '</span>';
    }
}
