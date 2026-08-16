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
 * install.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_bi\install\reports;
use local_kopere_pay\install\report;

/**
 * Function xmldb_local_kopere_pay_install
 *
 * @return true
 * @throws dml_exception
 */
function xmldb_local_kopere_pay_install() {
    global $DB, $CFG;

    set_config("sortorder", 301, "local_kopere_pay");

    set_config("form_monthly_fee", 0, "local_kopere_dashboard");
    set_config("form_confirmar", 0, "local_kopere_dashboard");
    set_config("couponlength", 8, "local_kopere_dashboard");
    set_config("form_ask_accept", '', "local_kopere_dashboard");
    set_config("form_theme", "base", "local_kopere_dashboard");
    set_config("form_pedircpf", 0, "local_kopere_dashboard");
    set_config("kopere_pay-pagseguro-sandbox", 1, "local_kopere_dashboard");
    set_config("kopere_pay-pagseguro-token-sandbox", "434F7D42877347F5BB1399EFBED546FD", "local_kopere_dashboard");

    if ($CFG->dbtype == "mysqli") {
        $sql = "ALTER TABLE {local_kopere_pay_enrollment} auto_increment = 10000";
        $DB->execute($sql);
    }

    // Load report pages.
    $pagefiles = glob(__DIR__ . "/files/page-*.json");
    foreach ($pagefiles as $pagefile) {
        reports::from_file($pagefile);
    }

    report::atualiza();

    return true;
}
