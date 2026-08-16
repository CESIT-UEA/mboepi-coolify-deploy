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
 * MeioManual.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class MeioManual
 */
class MeioManual implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        return [
            'name' => 'Manual',
            'public_name' => 'Manual',
            'class' => 'MeioManual',
            'enable' => self::is_enable(),
            'mensalidade' => self::is_mensal(),
            'escolha' => false,
        ];
    }

    /**
     * Function is_enable
     *
     * @return false
     */
    public static function is_enable() {
        return false;
    }

    /**
     * Function is_mensal
     *
     * @return false
     */
    public static function is_mensal() {
        return false;
    }

    /**
     * @param form $form
     * @param \stdClass $koperepaydetalhe
     */
    public static function details_edit_form(Form $form, $koperepaydetalhe) {
    }

    /**
     * @param form $form
     *
     * @return void
     */
    public static function edit(form $form) {
    }

    /**
     * @param local_kopere_pay_detail $detalhe
     * @param                    $course
     *
     * @return string
     */
    public static function pay_button($detalhe, $course) {
        return '';
    }

    /**
     * @param     $course
     * @param     $user
     * @param int $enrollmentid
     */
    public static function completed($course, $user) {
        $enrollmentid = optional_param('matricula', 0, PARAM_INT);
    }

    /**
     * @param $course
     * @return mixed
     */
    public static function enroll($course) {
    }

    /**
     * Function returned
     *
     * @return void
     */
    public static function returned() {
    }

    /**
     * @param array $receive
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($receive) {
        global $DB, $CFG;

        $user = $DB->get_record('user', ['id' => $receive['userid']]);
        $link = "<a href=\"{$CFG->wwwroot}/user/profile.php?id={$user->id}\" target=\"_blank\">" . fullname($user) . "</a>";

        if ($receive['status'] == 'unenrol') {
            return "Matrícula removida manualmente por <strong>{$link}</strong>!";
        } else {
            return "Matrícula adicionada manualmente por <strong>{$link}</strong>!";
        }
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {

    }
}