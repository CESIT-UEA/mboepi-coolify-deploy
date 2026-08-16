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
 * courses.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\catalog;

use coding_exception;
use context_system;
use dml_exception;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\course;
use local_kopere_dashboard\util\enroll_util;
use local_kopere_dashboard\util\html;
use local_kopere_dashboard\util\string_util;
use local_kopere_dashboard\util\webpages_util;
use local_kopere_dashboard\vo\local_kopere_dashboard_pages;
use local_kopere_pay\catalog_course;
use local_kopere_pay\util\course_util;
use local_kopere_pay\vo\local_kopere_pay_detail;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Class courses
 *
 * @package local_kopere_pay\catalog
 */
class courses {
    /**
     * Function show
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws \core\exception\moodle_exception
     * @throws \Exception
     */
    public static function show() {
        global $PAGE, $OUTPUT, $DB, $CFG, $USER;

        $PAGE->set_context(context_system::instance());
        $PAGE->set_pagetype('my-index');
        $PAGE->set_url(new moodle_url("/course-catalog/"));
        $PAGE->set_pagelayout(get_config("local_kopere_dashboard", 'webpages_theme'));
        $PAGE->set_title(get_string("course_catalog_title", "local_kopere_pay"));
        $PAGE->set_heading(get_string("course_catalog_title", "local_kopere_pay"));

        $PAGE->navbar->add(get_string("course_catalog_title", "local_kopere_pay"), new moodle_url("/course-catalog/"));

        $PAGE->add_body_class("kopere-dashboard-pages");
        $PAGE->add_body_class("kopere-dashboard-pages-{$CFG->theme}");

        $header = $OUTPUT->header();
        if ($banner = catalog_course::get_catalog_banner_data()) {
            $catalogbanner = $OUTPUT->render_from_template('local_kopere_pay/catalog_banner', $banner);

            $needle = '<div id="topofscroll"';
            if (strpos($header, $needle) !== false) {
                $header = str_replace($needle, "{$catalogbanner}{$needle}", $header);
                echo $header;
            } else {
                echo $header;
                echo $catalogbanner;
            }
        } else {
            echo $header;
        }

        $data = ["menus" => []];

        $sql = "SELECT * FROM {local_kopere_pay_detail} WHERE status = 'aberto' AND portfolio = 'visivel'";
        $koperepaydetails = $DB->get_records_sql($sql);

        $menu = (object) ['webpages' => []];
        /** @var local_kopere_pay_detail $koperepaydetalhe */
        foreach ($koperepaydetails as $koperepaydetalhe) {

            $course = course_util::find($koperepaydetalhe->course, false);
            if (!$course) {
                continue;
            }

            /** @var local_kopere_dashboard_pages $webpages */
            $webpages = new stdClass();

            $priceint = str_replace(".", "", $koperepaydetalhe->price);
            $priceint = str_replace(",", ".", $priceint);
            $priceint = floatval("0{$priceint}");

            if (!$priceint) {
                $webpages->cursoprice = get_string("price_free", "local_kopere_pay");
            } else {
                $webpages->cursoprice = "R$ " . $koperepaydetalhe->price;
            }

            $sql = "SELECT * FROM {course} WHERE id = :course LIMIT 1";
            if ($course = $DB->get_record_sql($sql, ["course" => $koperepaydetalhe->course])) {
                $webpages->title = $course->fullname;
                $webpages->text = false;

                $webpages->link = "{$CFG->wwwroot}/local/kopere_pay/?id={$koperepaydetalhe->course}";
            } else {
                continue;
            }

            $enroled = false;
            if (enroll_util::enrolled($koperepaydetalhe->course, $USER)) {
                $webpages->link = "{$CFG->wwwroot}/course/view.php?id={$koperepaydetalhe->course}";
                $webpages->access = get_string("access_course", "local_kopere_pay");
                $enroled = true;
            } else {
                $webpages->access = "Mais detalhes";
            }

            if (!$enroled) {
                $enable = config::get_key("builder_enable_{$koperepaydetalhe->course}");
                if ($enable) {
                    $webpages->link = "{$CFG->wwwroot}/local/kopere_pay/view.php?id={$koperepaydetalhe->course}";
                    $webpages->title = config::get_key("builder_title_{$koperepaydetalhe->course}");
                    $webpages->offprice = config::get_key("builder_offprice_{$koperepaydetalhe->course}");

                    $webpages->text = config::get_key("builder_header_{$koperepaydetalhe->course}");
                    $webpages->text = preg_replace('/<h\d.*?<\/h\d>/s', '', $webpages->text);
                    $webpages->text = string_util::trunc($webpages->text, 60);
                } else {
                    $sql = "
                        SELECT *
                          FROM {local_kopere_dashboard_pages}
                         WHERE visible = 1
                           AND courseid = '{$koperepaydetalhe->course}'
                         LIMIT 1";
                    /** @var local_kopere_dashboard_pages $localkoperedashboardpages */
                    $localkoperedashboardpages = $DB->get_record_sql($sql);
                    if ($localkoperedashboardpages) {
                        $webpages->title = $localkoperedashboardpages->title;
                        $webpages->text =
                            html::truncate_text(strip_tags($localkoperedashboardpages->text), 800);

                        $fs = get_file_storage();
                        $file = $fs->get_file(
                            context_system::instance()->id, "local_kopere_dashboard", 'webpage_image',
                            $localkoperedashboardpages->id, '/', 'webpage_image.img'
                        );
                        if ($file && isset($file->get_filename()[3])) {
                            $webpages->background = moodle_url::make_pluginfile_url(
                                $file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), "/",
                                $file->get_filename()
                            );
                        }

                        $webpages->link = "{$CFG->wwwroot}/local/kopere_dashboard/?p={$localkoperedashboardpages->link}";
                    }
                }
            }

            if (!isset($webpages->background)) {
                $webpages->background = course::overview_image($koperepaydetalhe->course);
            }

            $menu->webpages[] = $webpages;
        }
        $data['menus'][] = $menu;

        echo $OUTPUT->render_from_template('local_kopere_pay/catalog_index', $data);

        webpages_util::analytics();
        echo $OUTPUT->footer();
    }
}
