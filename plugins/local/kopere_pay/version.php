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
 * version.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$plugin->version = 20260701400;
$plugin->release = '8.0.6';
$plugin->requires = 2021041900;
$plugin->maturity = MATURITY_STABLE;
$plugin->component = "local_kopere_pay";

$plugin->kopere_license = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJVU1VBUklPX0lEIjoiNTQzIiwiVVNVQVJJT19OT01FIjoiUEFESUMiLCJVU1VBUklPX0VNQUlMIjoiY3RpYy5ob3N0aW5nZXJAdWVhLmVkdS5iciIsIlZFTkRBX0lEIjoiMzE5NCIsIlBST0RVVE9fSUQiOiI0IiwiUFJPRFVUT19USVRVTE8iOiJJbnRlZ3JhXHUwMGU3XHUwMGUzbyBjb21wbGV0YSBkbyBTb2Z0d2FyZSBNb29kbGVcdTIxMjIgY29tIEFzYWFzIFwvIFBhZ1NlZ3VybyBcLyBFZlx1MDBlZCBcLyBDaWVsbyJ9.hrZnxXg8U-elOgUGJUALXS3KyXa9rzjiGnofA-FScr8';

$plugin->dependencies = [
    "local_kopere_dashboard" => 2026071400,
    "local_kopere_bi" => 2026071400,
];
