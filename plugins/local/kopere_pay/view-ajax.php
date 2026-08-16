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
 * Ajax dispatcher for legacy Kopere Pay dashboard tables.
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing

define("AJAX_SCRIPT", true);

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/lib.php");

$context = context_system::instance();
require_login();

$classname = optional_param("classname", "dashboard", PARAM_ALPHANUMEXT);
$method = optional_param("method", "start", PARAM_ALPHANUMEXT);

try {
    echo local_kopere_pay_dispatch($classname, $method, $context);
} catch (Throwable $exception) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(400);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "error" => true,
        "message" => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
