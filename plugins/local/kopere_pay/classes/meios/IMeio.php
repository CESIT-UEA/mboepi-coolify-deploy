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
 * IMeio.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_history;

/**
 *
 */
interface IMeio {

    /**
     * Function get_name
     *
     * @return mixed
     */
    public static function get_name();

    /**
     * Function is_enable
     *
     * @return mixed
     */
    public static function is_enable();

    /**
     * Function is_mensal
     *
     * @return mixed
     */
    public static function is_mensal();

    /**
     * Function details_edit_form
     *
     * @param \kopere_pay\html\form $form
     * @param $koperepaydetalhe
     * @return mixed
     */
    public static function details_edit_form(Form $form, $koperepaydetalhe);

    /**
     * @param form $form
     *
     * @return void
     */
    public static function edit(form $form);

    /**
     * @param local_kopere_pay_detail $detalhe
     * @param \stdClass $course
     *
     * @return string
     */
    public static function pay_button($detalhe, $course);

    /**
     * @param     $course
     * @param     $user
     */
    public static function completed($course, $user);

    /**
     * @param $course
     * @return mixed
     */
    public static function enroll($course);

    /**
     * Function returned
     *
     * @return mixed
     */
    public static function returned();

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     */
    public static function get_status($historico);

    /**
     * Function ajax
     *
     * @return mixed
     */
    public static function ajax();
}