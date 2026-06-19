<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External functions (Web Services) registration for qtype_codejudge.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // Queues a new grading request for asynchronous AI processing.
    'qtype_codejudge_queue_grading' => [
        'classname'     => \qtype_codejudge\external\queue_grading::class,
        'methodname'    => 'execute',
        'description'   => 'Creates a grading record and queues it for asynchronous AI evaluation.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    // Returns the current status and result of a queued grading record.
    'qtype_codejudge_check_grading_status' => [
        'classname'     => \qtype_codejudge\external\check_status::class,
        'methodname'    => 'execute',
        'description'   => 'Returns the evaluation status and result (score + feedback) for a grading record.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    // Tests the currently configured AI provider connection.
    'qtype_codejudge_test_connection' => [
        'classname'     => \qtype_codejudge\external\test_connection::class,
        'methodname'    => 'execute',
        'description'   => 'Tests the connection to the configured AI provider and returns success or error details.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],

];
