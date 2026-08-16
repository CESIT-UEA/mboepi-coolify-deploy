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
 * MeioGratis.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios;

use local_kopere_pay\html\form;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\header;
use local_kopere_pay\util\coupon_util;
use local_kopere_pay\util\formater;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\util\send_event;
use local_kopere_pay\util\timeend_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use local_kopere_pay\vo\local_kopere_pay_history;
use local_kopere_pay\vo\local_kopere_pay_enrollment;

/**
 * Class MeioGratis
 */
class MeioGratis implements IMeio {

    /**
     * Function get_name
     *
     * @return array
     */
    public static function get_name() {
        return [
            'name' => 'Gratis',
            'public_name' => 'Gratis',
            'class' => 'MeioGratis',
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
     */
    public static function edit(form $form) {
    }

    /**
     * @param $detalhe
     * @param $course
     */
    public static function pay_button($detalhe, $course) {
    }

    /**
     * @param     $course
     * @param     $user
     * @param int $enrollmentid
     */
    public static function completed($course, $user) {
        $enrollmentid = optional_param('enrollment', 0, PARAM_INT);
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
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function returned() {
        global $DB, $USER, $CFG;

        $enroll = optional_param('enroll', false, PARAM_INT);
        $id = optional_param('id', false, PARAM_TEXT);

        $coupontext = '';
        if ($coupon = coupon_util::get_cupom($id)) {
            $coupontext = $coupon->uniquekey;
        }

        if ($enroll) {
            /** @var local_kopere_pay_detail $koperepaydetalhe */
            $koperepaydetalhe = $DB->get_record('local_kopere_pay_detail', ['course' => $id]);

            if ($coupon && $coupon->value < 0.50) {
                $koperepaydetalhe->price = 0;
            }

            if (formater::price_to_float($koperepaydetalhe->price) == 0) {

                if ($id[0] == 'c') {
                    enroll_util::cohort_enrol($id, $USER->id);
                } else {
                    $course = $DB->get_record('course', ['id' => $koperepaydetalhe->course]);

                    $timeend = timeend_util::calculate_day($koperepaydetalhe);
                    enroll_util::enrol($course, $USER, time(), $timeend);
                }

                $enrollmentid = local_kopere_pay_enrollment::add_enrollment($USER->id, $id, 'MeioGratis', $coupontext, "0,00");
                local_kopere_pay_history::add_history($enrollmentid, [], []);

                enrollment_util::changue_status($enrollmentid, enrollment_util::PAID);
                send_event::kopere_pay_pago($course, $USER);

                if ($id[0] == 'c') {
                    header::location("{$CFG->wwwroot}/my");
                } else {
                    header::location("{$CFG->wwwroot}/course/view.php?id={$koperepaydetalhe->course}");
                }
            } else {
                if ($id[0] == 'c') {
                    header::location("{$CFG->wwwroot}/my");
                } else {
                    header::location("{$CFG->wwwroot}/course/view.php?id={$koperepaydetalhe->course}");
                }
            }
        }
    }

    /**
     * @param local_kopere_pay_history $historico
     *
     * @return string
     * @throws \Exception
     */
    public static function get_status($historico) {
        return "Matrícula adicionada gratuitamente!";
    }

    /**
     * Function ajax
     *
     * @return void
     */
    public static function ajax() {

    }
}