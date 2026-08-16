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
 * local_kopere_pay_history.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\vo;

/**
 * Class local_kopere_pay_history
 *
 * @package local_kopere_pay\vo
 */
class local_kopere_pay_history extends \stdClass {
    /** @var int */
    public $id;

    /** @var int */
    public $enrollmentid;

    /** @var string */
    public $send;

    /** @var string */
    public $receive;

    /** @var string */
    public $error;

    /** @var int */
    public $time;

    /**
     * Function create_by_object
     *
     * @param $item
     * @return local_kopere_pay_history
     * @throws \coding_exception
     */
    public static function create_by_object($item) {
        $return = new local_kopere_pay_history();

        $return->id = $item->id;
        $return->enrollmentid = optional_param('enrollmentid', $item->enrollmentid, PARAM_INT);
        $return->method = optional_param('method', $item->method, PARAM_TEXT);
        $return->send = optional_param('send', $item->send, PARAM_TEXT);
        $return->receive = optional_param('receive', $item->receive, PARAM_TEXT);
        $return->error = optional_param('error', $item->error, PARAM_TEXT);
        $return->time = optional_param('time', $item->time, PARAM_INT);

        return $return;
    }

    /**
     * Function create_by_default
     *
     * @return local_kopere_pay_history
     * @throws \coding_exception
     */
    public static function create_by_default() {
        $return = new local_kopere_pay_history();

        $return->id = optional_param('id', 0, PARAM_INT);
        $return->enrollmentid = optional_param('enrollmentid', 0, PARAM_INT);
        $return->method = optional_param('method', '', PARAM_TEXT);
        $return->send = optional_param('send', '', PARAM_TEXT);
        $return->receive = optional_param('receive', '', PARAM_TEXT);
        $return->error = optional_param('error', '', PARAM_TEXT);
        $return->time = optional_param('time', 0, PARAM_INT);

        return $return;
    }

    /**
     * Function add_history
     *
     * @param $enrollmentid
     * @param $send
     * @param $receive
     * @param $error
     * @return void
     */
    public static function add_history($enrollmentid, $send, $receive, $error = null) {
        global $DB;

        if (!is_string($send)) {
            $send = json_encode($send);
        }
        if (!is_string($receive)) {
            $receive = json_encode($receive);
        }
        if (!is_string($error)) {
            $error = json_encode($error);
        }

        $historico = new \stdClass();
        $historico->enrollmentid = $enrollmentid;
        $historico->send = $send;
        $historico->receive = $receive;
        $historico->error = $error;
        $historico->time = time();
        $DB->insert_record('local_kopere_pay_history', $historico);

        $enrollment = new \stdClass();
        $enrollment->id = $enrollmentid;
        $enrollment->lasttime = time();
        $DB->update_record('local_kopere_pay_enrollment', $enrollment);
    }
}
