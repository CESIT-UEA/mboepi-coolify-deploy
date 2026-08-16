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
 * hook_callbacks.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use coding_exception;
use core\hook\output\before_http_headers;
use dml_exception;
use local_kopere_dashboard\util\config;
use moodle_exception;

/**
 * Class hook_callbacks
 */
class hook_callbacks {
    /**
     * Function before_http_headers
     *
     * @param before_http_headers $hook
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $CFG;

        $configkoperepay = get_config("local_kopere_pay");
        if (@$configkoperepay->enrolredirect || @$configkoperepay->enrolredirect_page) {
            if (strpos($_SERVER['REQUEST_URI'], "/enrol/index") !== false) {
                $id = optional_param("id", 0, PARAM_INT);

                global $DB;
                if (@$configkoperepay->enrolredirect_page) {
                    $localkoperedashboardpages =
                        $DB->get_record('local_kopere_dashboard_pages', ['courseid' => $id], '*', IGNORE_MULTIPLE);
                    if ($localkoperedashboardpages) {
                        redirect("{$CFG->wwwroot}/local/kopere_dashboard/?p={$localkoperedashboardpages->link}");
                    }
                }
                $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id, 'status' => 'aberto']);
                if ($koperepaydetalhe) {
                    $enable = config::get_key("builder_enable_{$id}");
                    if ($enable) {
                        redirect("{$CFG->wwwroot}/local/kopere_pay/view.php?id={$id}");
                    } else {
                        redirect("{$CFG->wwwroot}/local/kopere_pay/?id={$id}");
                    }
                }
            }
        }
    }
}
