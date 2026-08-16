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
 * catalog_course
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use coding_exception;
use context_system;
use core\notification;
use dml_exception;
use Exception;
use local_kopere_dashboard\html\button;
use local_kopere_dashboard\html\data_table;
use local_kopere_dashboard\output\layout;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_dashboard\vo\local_kopere_dashboard_pages;
use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_checkbox_select;
use local_kopere_pay\html\inputs\input_htmleditor;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_pay\util\course_util;
use moodle_exception;
use moodle_url;

/**
 * Class catalog_course
 *
 * @package local_kopere_pay
 */
class catalog_course {
    /**
     * Function dashboard
     *
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public function dashboard() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $PAGE->set_title(get_string("course_catalog_title", "local_kopere_pay"));

        $return = "<div class=\"kopere_dashboard-card\">";
        $return .= button::get_instance()
            ->set_info()
            ->add_tag("target='_blank'")
            ->set_link("{$CFG->wwwroot}/course-catalog")
            ->to_string(get_string("catalog_view_catalog", "local_kopere_pay"), true, true);

        if (!file_exists("{$CFG->dirroot}/course-catalog/index.php")) {
            $a = [
                "folder" => "course-catalog",
                "url" => "{$CFG->wwwroot}/course-catalog/",
            ];
            $intro = get_string("catalog_missing_folder_intro", "local_kopere_pay", $a);

            $return .= message::info($OUTPUT->render_from_template("local_kopere_pay/admin/catalog_missing_folder", [
                "intro" => $intro,
                "canautocreate" => is_writable($CFG->dirroot),
                "autocreateurl" => "?classname=catalog_course&method=instalacao",
                "instructionsurl" => "?classname=catalog_course&method=instrucoes",
            ]));
        } else {
            $return .= self::catalog_status();
            $return .= self::banner_form();

            $sql = "SELECT * FROM {local_kopere_pay_detail} WHERE status = 'aberto' AND portfolio = 'visivel'";
            $koperepaydetails = $DB->get_records_sql($sql);

            $allwebpages = [];
            foreach ($koperepaydetails as $koperepaydetalhe) {

                $course = course_util::find($koperepaydetalhe->course, false);
                if (!$course) {
                    continue;
                }

                /** @var local_kopere_dashboard_pages $webpages */
                $webpages = (object) [
                    'id' => $koperepaydetalhe->id,
                ];

                $priceint = str_replace(".", "", $koperepaydetalhe->price);
                $priceint = str_replace(",", ".", $priceint);
                $priceint = floatval("0{$priceint}");

                if (!$priceint) {
                    $webpages->cursoprice = get_string("webpages_free", "local_kopere_pay");
                } else {
                    $webpages->cursoprice = "R\$ {$koperepaydetalhe->price}";
                }

                $sql = "SELECT * FROM {local_kopere_dashboard_pages} WHERE visible = 1 AND courseid = :course";
                $enable = config::get_key("builder_enable_{$koperepaydetalhe->course}");
                if ($enable) {
                    $title = config::get_key("builder_title_{$koperepaydetalhe->course}");
                    $webpages->link = "{$CFG->wwwroot}/local/kopere_pay/view.php?id={$koperepaydetalhe->course}";
                    $webpages->title =
                        "<a target='_blank'
                            href='?classname=course_detail&method=details&course={$koperepaydetalhe->course}'
                            >{$title}</a>";

                    $webpages->offprice = config::get_key("builder_offprice_{$koperepaydetalhe->course}");
                    $webpages->cursoprice = "{$webpages->cursoprice} ({$webpages->offprice})";

                    $link = "{$CFG->wwwroot}/local/kopere_pay/view.php?id={$koperepaydetalhe->course}";
                    $webpages->local = "<a target='_blank' href='{$link}'>" . get_string("go_to_page", "local_kopere_pay") . "</a>";

                } else if ($localkoperedashboardpagess = $DB->get_records_sql($sql, ["course" => $koperepaydetalhe->course])) {
                    /** @var local_kopere_dashboard_pages $localkoperedashboardpages */
                    foreach ($localkoperedashboardpagess as $localkoperedashboardpages) {
                        $webpages->title =
                            "<a target='_blank'
                                href='?classname=course_detail&method=details&course={$koperepaydetalhe->course}'
                                >{$localkoperedashboardpages->title}</a>";

                        $link = "?classname=webpages&method=page_details&id={$localkoperedashboardpages->id}";
                        $webpages->local =
                            "<a target='_blank' href='{$link}'>" . get_string("go_to_page", "local_kopere_pay") . "</a>";

                        $fs = get_file_storage();
                        $file = $fs->get_file(
                            context_system::instance()->id, "local_kopere_dashboard", 'webpage_image',
                            $localkoperedashboardpages->id, '/', 'webpage_image.img'
                        );
                        if ($file && isset($file->get_filename()[3])) {
                            $webpages->imagem = moodle_url::make_pluginfile_url(
                                $file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), "/",
                                $file->get_filename()
                            );
                        }

                        if (isset($webpages->imagem)) {
                            $webpages->imagem = "<img src='{$webpages->imagem}' style='max-width:200px;max-height:100px;'>";
                        } else {
                            $webpages->imagem = get_string("catalog_no_image", "local_kopere_pay");
                        }
                        $allwebpages[] = $webpages;
                    }
                    continue;
                } else {
                    $sql = "SELECT * FROM {course} WHERE id = :course LIMIT 1";
                    if ($course = $DB->get_record_sql($sql, ["course" => $koperepaydetalhe->course])) {
                        $webpages->title =
                            "<a target='_blank'
                                href='?classname=course_detail&method=details&course={$koperepaydetalhe->course}'
                                >{$course->fullname}</a>";
                        $webpages->local = get_string("no_page", "local_kopere_pay");
                    } else {
                        continue;
                    }
                }

                if (isset($webpages->imagem)) {
                    $webpages->imagem = "<img src='{$webpages->imagem}' style='max-width:200px;max-height:100px;'>";
                } else {
                    $webpages->imagem = get_string("catalog_no_image", "local_kopere_pay");
                }

                $allwebpages[] = $webpages;
            }

            $table = new data_table();
            $table->add_header(get_string("catalog_table_title", "local_kopere_pay"), 'title');
            $table->add_header(get_string("catalog_table_has_page", "local_kopere_pay"), "local");
            $table->add_header(get_string("payments_valor", "local_kopere_pay"), 'cursoprice');

            $return .= $table->print_header("", true, true);
            $return .= $table->set_row($allwebpages, "", true);
            $return .= $table->close(false, null, true);
        }

        $return .= "</div>";
        return $return;
    }

    /**
     * Function banner_form
     *
     * @return string
     * @throws Exception
     */
    private static function banner_form() {
        global $CFG, $PAGE;

        $form = new form("?classname=catalog_course&method=save_banner");
        $form->return .= "<div class=\"kopere_dashboard-card\">";
        $form->return .= "<h3>" . get_string("catalog_banner_title", "local_kopere_pay") . "</h3>";

        if (in_array($CFG->theme, ["eadtraining", "eadflix", "boost_magnific", "degrade"])) {
            $PAGE->requires->js_call_amd("theme_{$CFG->theme}/settings", "minicolors", ["catalog_banner_color"]);
        } else if (in_array($CFG->theme, ["nice"])) { // phpcs:disable
        } else {
            $form->return .= '<div class="alert alert-warning alert-block">' .
                get_string("catalog_banner_theme", "local_kopere_pay") .
                '</div>';
        }

        if (config::get_key_int("catalog_banner_enabled") === false) {
            set_config("catalog_banner_enabled", 1, "local_kopere_dashboard");

            $bannerfile = "{$CFG->dirroot}/local/kopere_pay/pix/catalog_banner-default.png";
            if (file_exists($bannerfile)) {
                $context = context_system::instance();
                $fs = get_file_storage();

                try {
                    $fs->create_file_from_pathname([
                        "contextid" => $context->id,
                        "component" => "local_kopere_pay",
                        "filearea" => "catalog_banner",
                        "itemid" => 0,
                        "filepath" => "/",
                        "filename" => "deuault.png",
                    ], $bannerfile);
                } catch (Exception) { // phpcs:disable
                }
            }
        }

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title(get_string("catalog_banner_enable", "local_kopere_pay"))
                ->set_checked_by_config("catalog_banner_enabled")
                ->set_description(get_string("catalog_banner_enable_desc", "local_kopere_pay"))
        );

        $color = config::get_key("catalog_banner_color");
        if (!$color) {
            $color = self::get_catalog_banner_default_color();
        }

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("catalog_banner_color", "local_kopere_pay"))
                ->set_name("catalog_banner_color")
                ->set_value($color)
                ->set_description(get_string("catalog_banner_color_desc", "local_kopere_pay"))
        );

        $form->add_input(
            input_htmleditor::new_instance()
                ->set_title(get_string("catalog_banner_content", "local_kopere_pay"))
                ->set_value_by_config("catalog_banner_title", self::get_catalog_banner_default_title())
                ->set_description(get_string("catalog_banner_content_desc", "local_kopere_pay"))
        );

        $input = "<input type='file' name='catalog_banner_file' id='catalog_banner_file' accept='image/*'>";
        $form->print_row(
            get_string("catalog_banner_image", "local_kopere_pay"),
            $input,
            "catalog_banner_file",
            get_string("catalog_banner_image_desc", "local_kopere_pay")
        );

        $banner = self::get_catalog_banner_data(false);
        if ($banner) {
            $image = htmlspecialchars($banner->image, ENT_COMPAT);
            $preview = "<div style='margin-top:10px'>";
            $preview .= "<div><strong>" . get_string("catalog_banner_current_image", "local_kopere_pay") . "</strong></div>";
            $preview .= "<img src='{$image}' style='max-width:100%;height:auto;max-height:220px'>";
            $preview .= "</div>";
            $form->print_row("", $preview, "catalog_banner_preview");

            $delete = "<label><input type='checkbox' name='catalog_banner_delete' value='1'> " .
                get_string("catalog_banner_delete", "local_kopere_pay") . "</label>";
            $form->print_row(
                "",
                $delete,
                "catalog_banner_delete",
                get_string("catalog_banner_delete_desc", "local_kopere_pay")
            );
        }

        $form->create_submit_input(get_string("savechanges"));
        $form->return .= "</div>";

        return $form->close_and_return();
    }

    /**
     * Function save_banner
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws \moodle_exception
     */
    public function save_banner() {
        global $CFG;

        require_sesskey();
        require_once($CFG->libdir . "/filelib.php");

        set_config("catalog_banner_enabled", optional_param("catalog_banner_enabled", 0, PARAM_INT), "local_kopere_dashboard");
        set_config("catalog_banner_color", optional_param("catalog_banner_color", "", PARAM_TEXT), "local_kopere_dashboard");
        set_config("catalog_banner_title", optional_param("catalog_banner_title", "", PARAM_RAW), "local_kopere_dashboard");

        $context = context_system::instance();
        $fs = get_file_storage();

        if (optional_param("catalog_banner_delete", 0, PARAM_INT)) {
            $fs->delete_area_files($context->id, "local_kopere_pay", "catalog_banner", 0);
        }

        if (!empty($_FILES["catalog_banner_file"]["tmp_name"]) && is_uploaded_file($_FILES["catalog_banner_file"]["tmp_name"])) {
            $filename = clean_param($_FILES["catalog_banner_file"]["name"], PARAM_FILE);
            $mimetype = mimeinfo("type", $filename);

            if (strpos($mimetype, "image/") !== 0) {
                throw new moodle_exception("catalog_banner_invalid_file", "local_kopere_pay");
            }

            $fs->delete_area_files($context->id, "local_kopere_pay", "catalog_banner", 0);
            $fs->create_file_from_pathname([
                "contextid" => $context->id,
                "component" => "local_kopere_pay",
                "filearea" => "catalog_banner",
                "itemid" => 0,
                "filepath" => "/",
                "filename" => $filename,
            ], $_FILES["catalog_banner_file"]["tmp_name"]);
        }

        message::schedule_message_success(get_string("catalog_banner_saved", "local_kopere_pay"));
        header::location("?classname=catalog_course&method=dashboard");
    }

    /**
     * Function get_catalog_banner_data
     *
     * @param bool $onlyenabled
     * @return null|object
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_catalog_banner_data($onlyenabled = true) {
        if ($onlyenabled && !config::get_key_int("catalog_banner_enabled")) {
            return null;
        }

        $context = context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            "local_kopere_pay",
            "catalog_banner",
            0,
            "filename",
            false
        );

        if (!$files) {
            return null;
        }

        $file = reset($files);
        if (!$file) {
            return null;
        }

        $image = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        $color = config::get_key("catalog_banner_color");
        if (!preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color)) {
            $color = self::get_catalog_banner_default_color();
        }

        $title = config::get_key("catalog_banner_title");
        if (!$title) {
            $title = self::get_catalog_banner_default_title();
        }

        return (object) [
            "image" => $image->out(false),
            "color" => $color,
            "title" => $title,
        ];
    }

    /**
     * Function get_catalog_banner_default_color
     *
     * @return string
     * @throws \dml_exception
     */
    private static function get_catalog_banner_default_color() {
        return get_config("theme_boost", "brandcolor") ?: "#1E90FF";
    }

    /**
     * Function get_catalog_banner_default_title
     *
     * @return string
     * @throws coding_exception
     */
    private static function get_catalog_banner_default_title() {
        return '<h2 class="local-kopere-pay-catalog-banner-title">' .
            get_string("course_catalog_title", "local_kopere_pay") .
            '</h2>
<div class="local-kopere-pay-catalog-banner-description">
    ' . get_string("course_catalog_default_description", "local_kopere_pay") . '
</div>';
    }

    /**
     * Function catalog_status
     *
     * @return string
     * @throws \coding_exception
     */
    public static function catalog_status() {
        global $CFG;

        $instaledfile = file_get_contents("{$CFG->dirroot}/course-catalog/index.php");
        $koperepayfile = file_get_contents("{$CFG->dirroot}/local/kopere_pay/course-catalog/index.php");
        preg_match('/\*\s+@version\s+(\d+)/', $instaledfile, $instaledfileversion);
        preg_match('/\*\s+@version\s+(\d+)/', $koperepayfile, $koperepayfileversion);

        if (!isset($instaledfileversion[1])) {
            $instaledfileversion = [1 => ""];
        }

        if ($instaledfileversion[1] != $koperepayfileversion[1]) {
            if (is_writable("{$CFG->dirroot}/course-catalog/")) {
                $info =
                    "<p><a href='?classname=catalog_course&method=instalacao'>
                        Clique aqui para criar automaticamente a pasta</a></p>";
            } else {
                $info = get_string(
                    'catalog_create_folder_permission_html', "local_kopere_pay", '?classname=catalog_course&method=instrucoes'
                );
            }
            return message::danger(
                get_string(
                    'catalog_version_update_required_html', "local_kopere_pay",
                    (object) ['installed' => $instaledfileversion[1], 'current' => $koperepayfileversion[1], 'info' => $info]
                )
            );
        }

        return "";
    }

    /**
     * Function instalacao
     *
     * @throws Exception
     */
    public function instalacao() {
        global $CFG, $PAGE;

        mkdir("{$CFG->dirroot}/course-catalog/");

        $htaccess = file_get_contents("{$CFG->dirroot}/local/kopere_pay/course-catalog/.htaccess");
        $htaccessdestino = "{$CFG->dirroot}/course-catalog/.htaccess";
        file_put_contents($htaccessdestino, $htaccess);

        $index = file_get_contents("{$CFG->dirroot}/local/kopere_pay/course-catalog/index.php");
        $indexdestino = "{$CFG->dirroot}/course-catalog/index.php";
        file_put_contents($indexdestino, $index);

        $PAGE->set_title(get_string("catalog_installation_instructions", "local_kopere_pay"));
        $return = "";

        $erro = false;
        $return .= "<div class=\"kopere_dashboard-card\">";

        if (!file_exists($htaccessdestino)) {
            $return .= message::danger(get_string("catalog_error_creating_file", "local_kopere_pay", $htaccessdestino));
            $erro = true;
        }
        if (!file_exists($indexdestino)) {
            $return .= message::danger(get_string("catalog_error_creating_file", "local_kopere_pay", $indexdestino));
            $erro = true;
        }

        if (!$erro) {
            header::location("?classname=catalog_course&method=dashboard");
        }

        $return .= message::info(
            get_string(
                'catalog_create_folder_permission_html', "local_kopere_pay", '?classname=catalog_course&method=instrucoes'
            )
        );

        $return .= "</div>";
        return $return;
    }

    /**
     * Function instrucoes
     *
     * @throws coding_exception
     * @throws \Exception
     */
    public function instrucoes() {
        global $CFG, $PAGE;

        $PAGE->set_title(get_string("catalog_installation_instructions", "local_kopere_pay"));

        $return = "<div class=\"kopere_dashboard-card\">";

        if (!file_exists("{$CFG->dirroot}/course-catalog/index.php")) {
            $a = (object) [
                "source" => "{$CFG->dirroot}/local/kopere_pay/course-catalog/",
                "destination" => "{$CFG->dirroot}/course-catalog/",
            ];
            $return .= get_string("catalog_manual_instructions_html", "local_kopere_pay", $a);
        } else {
            header::location("?classname=catalog_course&method=dashboard");
        }

        $return .= "</div>";
        return $return;
    }
}
