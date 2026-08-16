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
 * r.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
// phpcs:disable moodle.PHP.ForbiddenFunctions.Found

use local_kopere_pay\meios\IMeio;
use local_kopere_pay\payment_method;

ob_start();
define('AJAX_SCRIPT', false);

require_once('../../config.php');

$PAGE->set_context(context_system::instance());

$meio = optional_param("meio", false, PARAM_TEXT);

// Bug do PagSeuro que limita a URL de retorno a poucos caracteres.
if ($meio == "ps") {
    $meio = "MeioPagseguro";
} else if ($meio == "psr") {
    $meio = "MeioPagseguroRecorrente";
}

$time = time();
$pathlog = "{$CFG->dataroot}/kopere_pay/logs";
if (!file_exists($pathlog)) {
    mkdir($pathlog, 0777, true);
}
file_put_contents("{$pathlog}/{$time}.txt", print_r([
    '$_GET' => $_GET,
    '$_POST' => $_POST,
    '$_SERVER' => $_SERVER,
    'php://input' => file_get_contents('php://input'),
], 1));

try {
    payment_method::list_meios();

    if ($meio) {
        /** @var IMeio $class */
        $class = "local_kopere_pay\\meios\\{$meio}";
        $class::returned();

        http_response_code(200);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    exit;
}
