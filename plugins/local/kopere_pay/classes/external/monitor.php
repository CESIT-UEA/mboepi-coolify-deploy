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
 * monitor.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\external;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
global $CFG;
require_once("{$CFG->libdir}/externallib.php");

use context_system;
use Exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * Dashboard monitor tiles (AJAX).
 */
class monitor extends external_api {

    /**
     * Function info_parameters
     *
     * @return external_function_parameters
     */
    public static function info_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Function info_is_allowed_from_ajax
     *
     * @return true
     */
    public static function info_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Function info_returns
     *
     * @return external_single_structure
     * @throws Exception
     */
    public static function info_returns() {
        return new external_single_structure([
            "html" => new external_value(PARAM_RAW, get_string("monitor_return_html_desc", "local_kopere_pay"), VALUE_OPTIONAL),
        ]);
    }

    /**
     * Function info
     *
     * @return array
     * @throws Exception
     */
    public static function info() {
        global $DB, $OUTPUT;

        self::validate_parameters(self::info_parameters(), []);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability("local/kopere_pay:view", $context);

        $enrollments = $DB->get_field_select("local_kopere_pay_enrollment", "COUNT(*)", "");
        $enrollmentspagas = $DB->get_field_select("local_kopere_pay_enrollment", "COUNT(*)", "status LIKE 'paid'");
        $coupons = $DB->get_field_select("local_kopere_pay_coupon", "COUNT(*)", "amount > 0");

        $cards = [
            [
                "bg" => "#a992e2",
                "label" => get_string("monitor_enrollments", "local_kopere_pay"),
                "url" => "?classname=transaction&method=dashboard",
                "value" => $enrollments,
            ],
            [
                "bg" => "#55badf",
                "label" => get_string("monitor_paid_enrollments", "local_kopere_pay"),
                "url" => "?classname=transaction&method=dashboard",
                "value" => $enrollmentspagas,
            ],
            [
                "bg" => "#ec6f5a",
                "label" => get_string("monitor_coupons", "local_kopere_pay"),
                "url" => "?classname=dashboard&method=start",
                "value" => $coupons,
            ],
        ];

        return [
            "html" => $OUTPUT->render_from_template("local_kopere_pay/admin/monitor_info", [
                "cards" => $cards,
            ]),
        ];
    }
}
