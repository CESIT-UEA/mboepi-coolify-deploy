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
 * settings_kopere.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$settings->add(
    new admin_setting_heading("kopere_pay_title", get_string("settings_heading_title", "local_kopere_pay"), "")
);

$settings->add(
    new admin_setting_configcheckbox(
        "local_kopere_pay/enrolredirect_page",
        get_string("settings_enrolredirect_page", "local_kopere_pay"),
        get_string("settings_enrolredirect_page_desc", "local_kopere_pay", [
            "url" => "{$CFG->wwwroot}/local/kopere_dashboard/open-internal.php?classname=webpages&method=dashboard",
        ]),
        1
    )
);

$settings->add(
    new admin_setting_configcheckbox(
        "local_kopere_pay/enrolredirect",
        get_string("settings_enrolredirect", "local_kopere_pay"),
        get_string("settings_enrolredirect_desc", "local_kopere_pay"),
        1
    )
);
