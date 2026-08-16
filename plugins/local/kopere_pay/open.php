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
 * Main Kopere Pay dispatcher.
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/lib.php");

global $PAGE;

$context = context_system::instance();
require_login();
require_capability("local/kopere_pay:view", $context);

$classname = optional_param("classname", "dashboard", PARAM_ALPHANUMEXT);
$method = optional_param("method", "start", PARAM_ALPHANUMEXT);

parse_str($_SERVER["QUERY_STRING"] ?? "", $params);
$params["classname"] = $classname;
$params["method"] = $method;

$PAGE->set_url(new moodle_url("/local/kopere_pay/open.php", $params));
$PAGE->add_body_class("local-kopere_dashboard");
$PAGE->add_body_class("local-kopere-pay");
$PAGE->set_context($context);

local_kopere_pay_dispatch_and_render($classname, $method, $context);
