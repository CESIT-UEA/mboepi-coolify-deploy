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
 * Kopere Dashboard menu integration.
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use context;
use local_kopere_bi\vo\local_kopere_bi_page;
use local_kopere_dashboard\api\subplugin_manager;
use local_kopere_dashboard\util\message;
use local_kopere_pay\shopping\form;
use local_kopere_pay\shopping\register;
use local_kopere_pay\util\course_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use moodle_url;

/**
 * Class menu
 */
class menu {
    /**
     * Return Kopere Dashboard menu definition.
     *
     * @param context $context
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \core\exception\moodle_exception
     */
    public static function get_definition(context $context): array {
        global $DB, $PAGE;

        static $koperedashboardmonitorsend = false;

        if (!get_config("local_kopere_dashboard", "monitor")) {
            if (!$koperedashboardmonitorsend) {
                $PAGE->requires->js_call_amd("local_kopere_pay/monitor", "init");
                $koperedashboardmonitorsend = true;
            }
        }

        $course = optional_param("course", 0, PARAM_INT);

        $urls = [
            self::url("dashboard", "start"),
            self::url("transaction", "dashboard"),
            self::url("payment_method", "dashboard"),
            self::url("settings", "form"),
            self::url("catalog_course", "dashboard"),
        ];

        $children = [];

        $children[] = [
            "title" => get_string("dashboard_all_courses", "local_kopere_pay"),
            "url" => self::url("dashboard", "start"),
            "icon" => "school",
        ];

        if ($course) {
            $courseparams = ["course" => $course];

            $urls[] = self::url("course_detail", "details", $courseparams);
            $urls[] = self::url("course_detail", "edit", $courseparams);
            $urls[] = self::url("coupons", "dashboard", $courseparams);
            $urls[] = self::url("builder", "creator", $courseparams);

            $children[] = [
                "title" => get_string("menu_course_details", "local_kopere_pay"),
                "url" => self::url("course_detail", "details", $courseparams),
                "icon" => "school",
            ];

            $children[] = [
                "title" => get_string("tab_edit", "local_kopere_pay"),
                "url" => self::url("course_detail", "edit", $courseparams),
                "icon" => "edit",
            ];

            if ($DB->get_dbfamily() == "mysql") {
                $urls[] = self::url("transaction", "dashboard", $courseparams);

                $children[] = [
                    "title" => get_string("transactions_title", "local_kopere_pay"),
                    "url" => [
                        self::url("transaction", "dashboard", $courseparams),
                        self::url("transaction", "detail", $courseparams),
                    ],
                    "icon" => "family_history",
                ];
            }

            $children[] = [
                "title" => get_string("tab_coupons", "local_kopere_pay"),
                "url" => [
                    self::url("coupons", "dashboard", $courseparams),
                    self::url("coupons", "form_new", $courseparams),
                    self::url("coupons", "form_import", $courseparams),
                ],
                "icon" => "percent_discount",
            ];

            $children[] = [
                "title" => get_string("tab_pagebuilder", "local_kopere_pay"),
                "url" => [
                    self::url("builder", "creator", $courseparams),
                    self::url("builder", "restore", $courseparams),
                ],
                "icon" => "article_shortcut",
            ];
        }

        $children[] = [
            "title" => get_string("transactions_all", "local_kopere_pay"),
            "url" => self::url("transaction", "dashboard"),
            "icon" => "account_balance",
        ];

        $children[] = [
            "title" => get_string("payment_methods_title", "local_kopere_pay"),
            "url" => [
                self::url("payment_method", "dashboard"),
                self::url("payment_method", "edit"),
            ],
            "icon" => "add_card",
        ];

        $children[] = [
            "title" => get_string("settings", "local_kopere_pay"),
            "url" => [
                self::url("settings", "form"),
                self::url("settings", "sugestao"),
                ],
            "icon" => "settings",
        ];

        $children[] = [
            "title" => get_string("course_catalog_title", "local_kopere_pay"),
            "url" => self::url("catalog_course", "dashboard"),
            "icon" => "list",
        ];

        return [
            "category" => subplugin_manager::CAT_FINANCIAL,
            "items" => [
                [
                    "title" => get_string("payments_title", "local_kopere_pay"),
                    "description" => get_string("payments_title_desc", "local_kopere_pay"),
                    "url" => $urls,
                    "icon" => "dashboard",
                    "capability" => "local/kopere_pay:view",
                    "children" => $children,
                ],
            ],
        ];
    }

    /**
     * Build legacy dashboard URL.
     *
     * @param string $classname
     * @param string $method
     * @param array $params
     * @return moodle_url
     * @throws \core\exception\moodle_exception
     */
    private static function url(string $classname, string $method, array $params = []): moodle_url {
        return new moodle_url("/local/kopere_pay/open.php", array_merge([
            "classname" => $classname,
            "method" => $method,
        ], $params));
    }

    /**
     * Function tabs
     *
     * @param $selected
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \core\exception\moodle_exception
     */
    public static function tabs($selected) {
        global $DB, $OUTPUT;

        $course = optional_param("course", 0, PARAM_TEXT);
        // Active.

        $menus = [
            [
                "url" => self::url("course_detail", "details", ["course" => $course]),
                "label" => get_string("tab_details", "local_kopere_pay"),
            ],
            [
                "url" => self::url("course_detail", "edit", ["course" => $course]),
                "label" => get_string("tab_edit", "local_kopere_pay"),
            ],
            [
                "url" => self::url("transaction", "dashboard", ["course" => $course]),
                "label" => get_string("transactions_title", "local_kopere_pay"),
            ],
            [
                "url" => self::url("coupons", "dashboard", ["course" => $course]),
                "label" => get_string("tab_coupons", "local_kopere_pay"),
            ],
            [
                "url" => self::url("builder", "creator", ["course" => $course]),
                "label" => get_string("tab_pagebuilder", "local_kopere_pay"),
            ],
        ];

        $sql = "
            SELECT kbp.*
              FROM {local_kopere_bi_page} kbp
              JOIN {local_kopere_bi_cat}  kbc ON kbc.id = kbp.cat_id
             WHERE kbc.refkey = 'koperepay'";
        $reports = $DB->get_records_sql($sql);
        /** @var local_kopere_bi_page $report */
        foreach ($reports as $report) {
            $menus[] = [
                "url" => new moodle_url(
                    "/local/kopere_bi/", ["classname" => "dashboard", "method" => "preview", "page_id" => $report->id]
                ),
                "label" => get_string("tab_reports", "local_kopere_pay", $report->title),
            ];
        }

        $tabs = [];
        foreach ($menus as $key => $menu) {
            $tabs[] = [
                "url" => $menu["url"],
                "label" => $menu["label"],
                "active" => ($key == $selected),
            ];
        }
        return $OUTPUT->render_from_template("local_kopere_pay/admin/tabs", [
            "tabs" => $tabs,
        ]);
    }

    /**
     * Function payment_buttons
     *
     * @param $id
     * @return string
     * @throws \dml_exception
     * @throws \coding_exception
     * @throws \Exception
     */
    public static function payment_buttons($id) {
        global $USER, $DB, $PAGE;

        $PAGE->requires->jquery();
        require_once(__DIR__ . "/../lib.php");
        $course = course_util::find($id);

        /** @var local_kopere_pay_detail $detalhe */
        $detalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $id]);
        if ($detalhe == null || $detalhe->status == "fechado") {
            return message::danger(get_string("course_without_enrolment_options", "local_kopere_pay"));
        }

        $register = new register();
        $form = new form();

        $returned = "<div class=\"enrollment\">";

        if (!isset($USER->id) || $USER->id < 2) {
            $returned .= $register->init($course, $detalhe);
        } else {
            $returned .= $form->form_pagar($course, $detalhe);
        }

        return $returned . "</div>";
    }
}
