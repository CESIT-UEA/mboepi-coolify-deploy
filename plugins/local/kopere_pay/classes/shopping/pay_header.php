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
 * pay_header.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\shopping;

use context_system;
use local_kopere_dashboard\util\end_util;
use local_kopere_dashboard\util\header;

/**
 * Class pay_header
 */
class pay_header {

    /**
     * Function notfound_null
     *
     * @param $testparam
     * @param $printtext
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function notfound_null($testparam, $printtext) {
        if ($testparam == null) {
            self::notfound($printtext);
        }
    }

    /**
     * Function notfound
     *
     * @param $printtext
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function notfound($printtext) {
        global $PAGE, $OUTPUT;

        if (!AJAX_SCRIPT) {
            header('HTTP/1.0 404 Not Found');
        }
        if (defined(KOPERE_FORM_MATRICULA) && KOPERE_FORM_MATRICULA) {
            if (!$PAGE->requires->is_head_done()) {
                $PAGE->set_context(context_system::instance());
                $PAGE->set_pagetype("admin-setting");
                $PAGE->set_pagelayout("standard");
                $PAGE->set_title("Erro");
                $PAGE->set_heading("Erro");
                echo $OUTPUT->header();
            }

            $mustachedata = ["message" => $printtext];
            echo $OUTPUT->render_from_template("local_kopere_pay/shopping/notfound", $mustachedata);

            echo $OUTPUT->footer();
            die;
        } else {
            header::notfound($printtext);
        }
    }
}
