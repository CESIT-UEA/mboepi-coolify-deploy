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
 * terms.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState

ob_start();

require_once("../../config.php");
require_once("lib.php");
define("OPEN_INTERNAL", true);

$id = optional_param("id", 0, PARAM_TEXT);

$PAGE->set_url(new moodle_url("/local/kopere_pay/terms.php"));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagetype("admin-setting");
$PAGE->set_pagelayout(get_config("local_kopere_dashboard", "form_theme"));

$PAGE->requires->jquery();
$PAGE->set_title(get_string("terms_of_service", "local_kopere_pay"));
$PAGE->set_heading(get_string("terms_of_service", "local_kopere_pay"));

echo $OUTPUT->header();

echo $OUTPUT->render_from_template("local_kopere_pay/terms", [
    "termshtml" => get_config("local_kopere_dashboard", "form_ask_accept"),
]);

echo $OUTPUT->footer();
