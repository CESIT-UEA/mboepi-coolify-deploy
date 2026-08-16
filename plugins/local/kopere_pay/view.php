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
 * View File
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing

use core\output\notification;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\course;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_pay\util\course_util;

require_once('../../config.php');

$id = required_param("id", PARAM_TEXT);
$course = course_util::find($id, false);
if (!$course) {
    throw new Exception(get_string("course_not_found", "local_kopere_pay"));
}

$koperepaydetalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $id]);

$context = context_system::instance();

// Use `QUERY_STRING` because you could have a million parameters here.
$PAGE->set_url(new moodle_url("/local/kopere_pay/view.php?{$_SERVER["QUERY_STRING"]}"));
$PAGE->set_context($context);
$PAGE->set_pagetype('my-index');
$PAGE->add_body_class("kopere_pay_view");

$enable = config::get_key("builder_enable_{$id}");
if (!$enable) {
    if (has_capability('local/kopere_pay:manage', $context)) {
        redirect(
            new moodle_url("/local/kopere_pay/open.php?classname=builder&method=creator&course={$id}"),
            get_string("course_not_configured", "local_kopere_pay"), null,
            notification::NOTIFY_ERROR
        );
    } else {
        redirect(new moodle_url("/course/view.php?id={$id}"));
    }
}

if (!has_capability('local/kopere_pay:manage', $context)) {
    if (enroll_util::enrolled($course, $USER)) {
        redirect(new moodle_url("/course/view.php?id={$id}"));
    }
}

$titulo = config::get_key("builder_title_{$id}");
$PAGE->set_title($titulo);

echo $OUTPUT->header();

$youtubeid = $imagemcurso = false;
switch (config::get_key("builder_what_{$id}")) {
    case "youtube":
        if (preg_match(
            '/youtu(\.be|be\.com)\/(watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]{11})/i',
            config::get_key("builder_youtube_{$id}"), $output
        )) {
            $youtubeid = $output[3];
        }
        break;
    case "imagemcurso":
        if ($id[0] != "c") {
            $imagemcurso = course::overview_image($course->id);
        }
        break;
}

$whatsapp = config::get_key("builder_whatsapp_{$id}");
if (isset($whatsapp[9])) {
    $whatsapp = "https://wa.me/55" . preg_replace('/\D/', '', $whatsapp);
}

$pages = [];
for ($i = 0; $i < 5; $i++) {
    $pages[] = [
        "page_id" => $i,
        "titulo" => config::get_key("builder_aba_title_{$id}_{$i}"),
        "titulolongo" => config::get_key("builder_aba_long_title_{$id}_{$i}"),
        "conteudo" => config::get_key("builder_aba_content_{$id}_{$i}"),
    ];
}

$replaceto = "<div class=\"d-flex justify-content-between\">
    <span class=\"text-white\">$1</span>
    <span class=\"badge badge-secondary\">$2</span>
</div>";
$coursedetails = preg_replace(
    '/(.*?)=>(.*?)\n/',
    $replaceto,
    config::get_key("builder_coursedetails_{$id}") . "\n"
);

$enrollmentdo = false;
if (isset($USER->id) && $USER->id > 1 && !$course->isCoorte) {
    if (enroll_util::enrolled($course, $USER)) {
        $enrollmentdo = true;
    }
}

$showprice = !(get_config("local_kopere_dashboard", "hide_zero_summary") && $koperepaydetalhe->price <= .5);

$data = [
    "topo" => config::get_key("builder_header_{$id}"),
    "titulo" => $titulo,
    "imagem_curso" => $imagemcurso,
    "is_enrolled" => $enrollmentdo,
    "curso_link" => "{$CFG->wwwroot}/course/view.php?id={$id}",
    "current-price" => $koperepaydetalhe->price,
    "showprice" => $showprice,
    "off-price" => config::get_key("builder_offprice_{$id}"),
    "comprar" => [
        "link" => "{$CFG->wwwroot}/local/kopere_pay/?id={$id}",
        "tags" => [
            'sandbox="allow-scripts allow-popups allow-forms allow-same-origin allow-modals allow-presentation"',
            'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"',
            'frameborder="0"',
            "allowfullscreen",
            'width="100%" height="520"',
            'id="comprar-iframe"',
        ],
    ],
    "whatsapp-link" => $whatsapp,
    "whatsapp-numero" => config::get_key("builder_whatsapp_{$id}"),
    "coursedetails" => $coursedetails,
    "paginas" => $pages,

    "youtube" => [
        "id" => $youtubeid,
        "tags" => [
            'sandbox="allow-scripts allow-popups allow-forms allow-same-origin allow-modals allow-presentation"',
            'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"',
            'frameborder="0"',
            "allowfullscreen",
            'style="position:absolute;top:0;left:0;bottom:0;right:0;width:100%;height:100%;"',
            'id="youtube-video"',
        ],
    ],
    "admin" => [
        "has_capability" => has_capability('local/kopere_pay:manage', $context),
        "edit" => "{$CFG->wwwroot}/local/kopere_pay/open.php?classname=builder&method=creator&course={$id}",
    ],
];
echo $OUTPUT->render_from_template('local_kopere_pay/page-builder', $data);
$PAGE->requires->js_call_amd('local_kopere_pay/page-builder', "init", []);
echo $OUTPUT->footer();
