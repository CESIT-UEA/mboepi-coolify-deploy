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
 * builder.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use Exception;
use local_kopere_dashboard\util\header;
use local_kopere_pay\util\editor;
use local_kopere_dashboard\html\button;
use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_base;
use local_kopere_pay\html\inputs\input_checkbox_select;
use local_kopere_pay\html\inputs\input_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_pay\html\inputs\input_textarea;
use local_kopere_dashboard\util\config;
use local_kopere_pay\util\course_util;
use local_kopere_pay\util\update_checker;

/**
 * Class builder
 *
 * @package local_kopere_pay
 */
class builder {

    /**
     * Function creator
     *
     * @throws Exception
     */
    public function creator() {
        global $DB, $CFG, $PAGE, $OUTPUT;

        $id = optional_param("course", 0, PARAM_TEXT);
        $course = course_util::find($id);

        $koperepaydetalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $id]);
        if (!$koperepaydetalhe) {
            header::location("?classname=course_detail&method=edit&course={$id}");
        }

        $PAGE->set_title("Detalhes de \"{$course->fullname}\"");

        $return = menu::tabs(4);

        if (!$CFG->autologinguests) {
            $return .= $OUTPUT->render_from_template("local_kopere_pay/not-autologinguests", []);
        }

        $fetch = (new update_checker())->fetch();
        if (!in_array($CFG->theme, ["boost_magnific", "degrade", "eadtraining"])) {
            $options = [];
            if (isset($fetch->updates->theme_boost_magnific)) {
                $options = [
                    "theme_boost_magnific" => json_encode(
                        '{"name":"Boost Magnific","component":"theme_boost_magnific","version":"' .
                        $fetch->updates->theme_boost_magnific[0]->version . '"}'
                    ),
                    "theme_degrade" => json_encode(
                        '{"name":"Degrade Theme","component":"theme_degrade","version":"' .
                        $fetch->updates->theme_degrade[0]->version . '"}'
                    ),
                ];
            }
            $return .= $OUTPUT->render_from_template("local_kopere_pay/not-theme", $options);
        }

        $previewtext = get_string("builder_preview_page", "local_kopere_pay");
        $return .= "<a href=\"{$CFG->wwwroot}/local/kopere_pay/view.php?id={$id}\"
         class=\"btn btn-primary mb-3\" target=\"preview\">{$previewtext}</a>";

        $redirect = urlencode("classname=builder&method=criador&course={$id}");
        $form = new form("?classname=settings&method=save&redirect={$redirect}");
        $form->return .= "<div class=\"kopere_dashboard-card\">";

        $form->create_hidden_input("tyni_editor_config", (new editor())->tyni_editor_config());

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title(get_string("builder_enable_page", "local_kopere_pay"))
                ->set_value_by_config("builder_enable_{$id}", 1)
                ->set_required()
        );

        if (!config::get_key("builder_title_{$id}")) {
            set_config("builder_title_{$id}", $course->fullname, "local_kopere_dashboard");
        }
        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("builder_public_course_title", "local_kopere_pay"))
                ->set_value_by_config("builder_title_{$id}")
                ->set_required()
        );

        if (!config::get_key("builder_header_{$id}")) {
            set_config(
                "builder_header_{$id}",
                get_string("builder_header_default_html", "local_kopere_pay", $course->fullname),
                "local_kopere_dashboard"
            );
        }
        $form->add_input(
            input_textarea::new_instance()
                ->set_title(get_string("builder_public_course_description", "local_kopere_pay"))
                ->set_name("builder_header_{$id}")
                ->set_value_by_config("builder_header_{$id}")
                ->set_style("height:400px")
        );
        $PAGE->requires->js_call_amd("local_kopere_pay/page-builder", "createEditor", ["builder_header_{$id}"]);

        $form->print_row(get_string("builder_purchase_price", "local_kopere_pay"), $koperepaydetalhe->price);

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("builder_old_price_label", "local_kopere_pay"))
                ->set_value_by_config("builder_offprice_{$id}")
                ->add_validator(input_base::VAL_VALOR)
                ->set_style("width:100px")
        );

        $values = [
            [
                "key" => "nada",
                "value" => get_string("builder_show_option_none", "local_kopere_pay"),
            ],
            [
                "key" => "youtube",
                "value" => get_string("builder_show_option_youtube", "local_kopere_pay"),
            ],
        ];

        if ($id[0] != "c") {
            $values[] = [
                "key" => "imagemcurso",
                "value" => get_string("builder_show_option_courseimage", "local_kopere_pay"),
            ];
        }

        $form->add_input(
            input_select::new_instance()
                ->set_title(get_string("builder_show_before_purchase", "local_kopere_pay"))
                ->set_value_by_config("builder_what_{$id}")
                ->set_values($values)
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("builder_youtube_promo_link", "local_kopere_pay"))
                ->set_value_by_config("builder_youtube_{$id}")
                ->set_style("max-width:500px")
        );

        $form->return .= "</div>";
        $form->return .= "<div class=\"kopere_dashboard-card\">";

        $value = config::get_key("builder_coursedetails_{$id}");
        if (!$value) {
            $html = get_string("builder_course_details_default", "local_kopere_pay");
            set_config("builder_coursedetails_{$id}", $html, "local_kopere_dashboard");
        }

        $coursedetails = config::get_key("builder_coursedetails_{$id}");
        $coursedetails = str_replace("\\n", "\n", $coursedetails);
        $form->add_input(
            input_textarea::new_instance()
                ->set_title(get_string("builder_course_info_below_price", "local_kopere_pay"))
                ->set_name("builder_coursedetails_{$id}")
                ->set_value($coursedetails)
        );

        $form->add_input(
            input_text::new_instance()
                ->set_title(get_string("builder_whatsapp_contact", "local_kopere_pay"))
                ->set_value_by_config("builder_whatsapp_{$id}")
                ->add_validator(input_base::VAL_CELPHONE)
                ->add_mask(input_base::MASK_CELULAR)
                ->set_style("width:150px")
        );

        $form->return .= "</div>";

        $titulos = [
            ["Visão geral", "Visão geral"],
            ["Conteúdo", "Conteúdo do curso"],
            ["Professores", "Professores"],
            ["", ""],
            ["", ""],
        ];
        foreach ($titulos as $key => $default) {
            $num = $key + 1;
            $form->return .= "<div class='kopere_dashboard-card'>";
            $form->add_input(
                input_text::new_instance()
                    ->set_title("Título curto da Aba {$num}")
                    ->set_value_by_config("builder_aba_title_{$id}_{$key}", $default[0])
                    ->set_description("Deixe em branco para não mostrar está aba")
            );

            $form->add_input(
                input_text::new_instance()
                    ->set_title("Título longo da aba da Aba {$num}")
                    ->set_value_by_config("builder_aba_long_title_{$id}_{$key}", $default[1])
            );

            $form->add_input(
                input_textarea::new_instance()
                    ->set_title("Conteúdo da aba")
                    ->set_name("builder_aba_content_{$id}_{$key}")
                    ->set_value_by_config("builder_aba_content_{$id}_{$key}")
                    ->set_style("height:400px")
            );
            $PAGE->requires->js_call_amd("local_kopere_pay/page-builder", "createEditor", ["builder_aba_content_{$id}_{$key}"]);

            $form->return .= "</div>";
        }

        $form->return .= "<div class='kopere_dashboard-card'>";
        $form->create_submit_input(get_string("savechanges"));
        $return .= $form->close_and_return();
        $return .= "</div>";

        if (config::get_key("builder_enable_{$id}") != null) {
            $return .= button::info("Baixar está página", "?classname=builder&method=baixar&course={$id}", "", false, true);
        }
        $return .= button::info("Enviar JSON", "?classname=builder&method=restore&course={$id}", "", false, true);

        $return .= "</div>";

        return $return;
    }

    /**
     * Function baixar
     *
     * @throws Exception
     */
    public function baixar() {
        global $DB;

        $id = optional_param("course", 0, PARAM_TEXT);

        $configs = $DB->get_records_sql(
            "
                    SELECT name, value
                      FROM {config_plugins}
                     WHERE plugin LIKE 'local_kopere_dashboard'
                       AND (name LIKE 'builder_%\\_{$id}' OR name LIKE 'builder_aba%\\_{$id}\\_%')"
        );

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="dados.json"');
        ob_clean();
        echo json_encode($configs, JSON_PRETTY_PRINT);

        die();
    }

    /**
     * Function restore
     *
     * @throws Exception
     */
    public function restore() {
        global $DB;

        $return = "";

        $id = optional_param("course", 0, PARAM_TEXT);
        $course = course_util::find($id);

        $koperepaydetalhe = $DB->get_record("local_kopere_pay_detail", ["course" => $id]);
        if (!$koperepaydetalhe) {
            header::location("?classname=course_detail&method=edit&course={$id}");
        }

        $PAGE->set_title("Detalhes de \"{$course->fullname}\"");

        $return = menu::tabs(4);
        $return .= "<div class=\"kopere_dashboard-card\">";

        $form = new form("?classname=builder&method=restore_fim&course={$id}");

        $form->add_input(
            input_textarea::new_instance()
                ->set_name("json")
                ->set_title("Copie o JSON aqui")
                ->set_required()
        );

        $form->create_submit_input(get_string("savechanges"));
        $return .= $form->close_and_return();
        $return .= "</div>";

        return $return;
    }

    /**
     * Function restore_fim
     *
     * @throws Exception
     */
    public function restore_fim() {
        $id = optional_param("course", 0, PARAM_TEXT);
        $json = optional_param("json", 0, PARAM_RAW);

        $dados = json_decode($json);

        if (!$dados) {
            throw new Exception("JSON inválido");
        }

        foreach ($dados as $dado) {
            if (strpos($dado->name, "aba") > 1) {
                $dado->name = preg_replace('/(builder_aba.*_)\w+(_\d+)$/', '$1{id}$2', $dado->name);
            } else {
                $dado->name = preg_replace('/(builder.*_)\w+$/', '$1{id}', $dado->name);
            }
            $dado->name = str_replace("{id}", $id, $dado->name);

            set_config($dado->name, $dado->value, "local_kopere_dashboard");
        }

        header::location("?classname=builder&method=creator&course={$id}");
    }
}
