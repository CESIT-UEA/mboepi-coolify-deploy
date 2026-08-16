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
 * index.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState

use core\session\manager;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\payment_method;
use local_kopere_pay\meios\IMeio;
use local_kopere_pay\shopping\complete;
use local_kopere_pay\shopping\form;
use local_kopere_pay\util\course_util;

ob_start();

require_once('../../config.php');
require_once('lib.php');
define('OPEN_INTERNAL', true);
define('KOPERE_FORM_MATRICULA', true);

if (manager::is_loggedinas()) {
    echo message::danger(get_string("logged_in_as_warning", "local_kopere_pay"));
} else {
    try {
        $id = optional_param("id", 0, PARAM_TEXT);
        $iframe = optional_param("iframe", 0, PARAM_INT);
        $billingtype = optional_param("billing_type", "", PARAM_TEXT);

        $PAGE->set_url(new moodle_url("/local/kopere_pay/", ["id" => $id]));
        $PAGE->set_context(context_system::instance());
        $PAGE->set_pagelayout("standard");
        $PAGE->set_pagetype('course-view');
        $PAGE->set_pagelayout(get_config("local_kopere_dashboard", "form_theme"));
        $PAGE->add_body_class("local_kopere_pay_enrollment");

        $PAGE->requires->jquery();

        $PAGE->requires->strings_for_js([
            "registration_phone_mobile_invalid_error",
            "registration_phone_invalid_error",
            "registration_cep_invalid_error",
            "registration_cpf_error",
            "registration_email_invalid_error",
            "registration_password_min_length_error",
            "registration_full_name_error",
            "registration_cnpj_error",
            "registration_form_missing",
            "registration_form_n_missing",
        ], "local_kopere_pay");

        $PAGE->requires->js_call_amd('local_kopere_pay/enrollment', "init");
        $PAGE->requires->js_call_amd('local_kopere_pay/enrollment', "form");

        payment_method::list_meios();

        if ($iframe) {
            $PAGE->set_pagelayout("embedded");
        }

        $course = course_util::find($id);

        if (optional_param("testeemail", false, PARAM_INT)) {
            $seuemail = optional_param('your-email', false, PARAM_EMAIL);
            $coupon = optional_param("coupon", "", PARAM_TEXT);

            $userteste = $DB->get_record("user", ["email" => $seuemail]);
            if ($userteste) {
                $params = [
                    "id" => $id,
                    "pagar" => 1,
                    "billing_type" => $billingtype,
                    "iframe" => $iframe,
                    "coupon" => $coupon,
                ];
                $SESSION->wantsurl = (new moodle_url("/local/kopere_pay/", $params))->out(false);
                header::location("{$CFG->wwwroot}/login/?username={$seuemail}&coupon={$coupon}");
            } else {
                $params = [
                    "id" => $id,
                    "cadastro" => 1,
                    "email" => $seuemail,
                    "billing_type" => $billingtype,
                    "iframe" => $iframe,
                    "coupon" => $coupon,
                ];
                header::location((new moodle_url("/local/kopere_pay/", $params))->out(false));
            }

        } else if (optional_param("enroll", false, PARAM_INT)) {
            require_login();
            $meio = optional_param("meio", '', PARAM_TEXT);

            if ($meio) {
                /** @var IMeio $class */
                $class = "\\local_kopere_pay\\meios\\{$meio}";
                $class::enroll($course);
            }
        } else if (optional_param("completed", false, PARAM_INT)) {
            (new complete())->show($course, $id);
        } else {
            $form = new form();
            $form->show();
        }

    } catch (Exception $e) {
        echo message::danger($e->getMessage());
    }
}
