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
 * settings.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use coding_exception;
use dml_exception;
use Exception;
use koperedashboard_pages\service\webpages_service;
use local_kopere_dashboard\html\button;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\string_util;
use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_base;
use local_kopere_pay\html\inputs\input_checkbox_select;
use local_kopere_pay\html\inputs\input_htmleditor;
use local_kopere_pay\html\inputs\input_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_dashboard\util\message;
use local_kopere_pay\util\profile_field;
use moodle_url;

/**
 * Class settings
 */
class settings {

    /**
     * Function form
     *
     * @throws Exception
     */
    public function form() {
        global $PAGE;

        $PAGE->set_title("Configurações das matrículas");

        $redirect = urlencode("classname=dashboard&method=start");
        $form = new form("?classname=settings&method=save&redirect={$redirect}");

        $this->extra_fields($form);
        $this->settings_system($form);
        $this->settings_form($form);

        $form->create_submit_input(get_string("savechanges"));
        return $form->close_and_return();
    }

    /**
     * Function extra_fields
     *
     * @param form $form
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function extra_fields($form) {
        global $DB, $CFG;

        $form->return .= "<div class=\"kopere_dashboard-card\">";
        $form->return .= "<h3>Campos extras do formulário de matrícula</h3>";

        $form->return .= message::warning("Lembre-se: Pedir muitos dados diminui a chance do aluno preencher a matrícula.");

        $messagesupport = [];
        $support = [
            "text" => false,
            "textarea" => false,
            "checkbox" => false,
            "menu" => false,
            "datetime" => false,
            "url" => false,
            "database" => false,
        ];
        foreach ($support as $key => $v) {
            if (file_exists("{$CFG->dirroot}/user/profile/field/{$key}/field.class.php")) {
                $messagesupport[] = "<strong>" . get_string("pluginname", "profilefield_{$key}") . "</strong>";
            }
        }
        $messagesupport = implode(", ", $messagesupport);
        $form->return .= message::info(
            "Apenas suporta os campos {$messagesupport} e não tem suporte ao campo <strong>Social</strong>"
        );
        if (!file_exists("{$CFG->dirroot}/user/profile/field/database/field.class.php")) {
            $form->return .= message::warning(
                "O Kopere pay agora tem suporte ao
                    <a href='https://moodle.org/plugins/profilefield_database' target='_blank'>User Field DB</a>"
            );
        }

        $form->return .= button::get_instance()
            ->set_primary()
            ->set_target("_blank")
            ->set_link("{$CFG->wwwroot}/user/profile/index.php")
            ->to_string("Gerenciar campos extras", true, true);

        $categorys = $DB->get_records_select(
            "user_info_category",
            "name NOT LIKE :categoryname",
            ["categoryname" => profile_field::$categoryname],
            "sortorder ASC"
        );
        if (count($categorys) >= 0) {

            $extrafields = get_config("local_kopere_dashboard", "extra_fields");
            $extrafields = explode(",", $extrafields);

            $html = "<select id='extra_fields' name='extra_fields[]' multiple>";
            foreach ($categorys as $category) {

                $html .= "<option value=\"{$category->id}\"";

                if (in_array($category->id, $extrafields)) {
                    $html .= ' selected="selected"';
                }

                $html .= ">{$category->name}</option>";
            }

            $html .= "</select>";
            $form->print_row(
                "Categorias que deve aparecer no Formulário de matrícula",
                $html, "extra_fields",
                "Selecione quais categorias devem aparecer no formulário de matrícula."
            );

        }
        $form->return .= "</div>";
    }

    /**
     * Function settings_system
     *
     * @param $form
     * @return void
     * @throws Exception
     */
    private function settings_system($form) {
        $form->return .= "<div class=\"kopere_dashboard-card\">";
        $form->return .= "<h3>Configurações do Sistema</h3>";

        $form->add_input(
            input_text::new_instance()
                ->set_title("Senha padrão para cadastro")
                ->add_extras("maxlength='40'")
                ->set_style("width: 220px")
                ->set_value_by_config("default_password")
                ->set_description(
                    "Se você definir um valor neste campo, todos os novos cadastros usarão esta senha padrão inicialmente.
                    É importante informar aos usuários que alterem suas senhas após
                    o primeiro acesso para garantir a segurança de suas contas."
                )
        );

        $form->add_input(
            input_text::new_instance()
                ->set_type("number")
                ->set_title("Comprimento do Cupom de desconto")
                ->set_value_by_config("couponlength")
                ->set_required()
                ->add_validator(input_base::VAL_INT)
                ->set_description("Comprimento do cupom de desconto. Alterando este valor, os coupons atuais serão inválidos!")
        );

        $form->add_input(
            input_select::new_instance()
                ->set_title("Thema da Matrícula")
                ->set_values(webpages_service::list_themes())
                ->set_value_by_config("form_theme")
                ->set_description("Selecione o thema que deve ser usado para a página de matrícula!")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Ocultar resumo quando preço é zero")
                ->set_value_by_config("hide_zero_summary")
                ->set_description("Caso o preço é zero reais o resumo deve ser ocultado?")
        );

        $form->add_input(
            input_htmleditor::new_instance()
                ->set_title("Termos do Serviço")
                ->set_name("form_ask_accept")
                ->set_value_by_config("form_ask_accept")
        );

        $form->return .= button::get_instance()
            ->set_info()
            ->set_link("?classname=settings&method=sugestao&num=0&usar=1&sesskey=" . sesskey())
            ->to_string("Limpar termo de aceite", false, true);

        $form->return .= button::get_instance()
            ->set_primary()
            ->add_class("ml-1")
            ->set_link("?classname=settings&method=sugestao&num=1")
            ->to_string("Sugestão de termo de aceite 1", false, true);
        $form->return .= button::get_instance()
            ->set_primary()
            ->add_class("ml-1")
            ->set_link("?classname=settings&method=sugestao&num=2")
            ->to_string("Sugestão de termo de aceite 2", false, true);
        $form->return .= button::get_instance()
            ->set_primary()
            ->add_class("ml-1")
            ->set_link("?classname=settings&method=sugestao&num=3")
            ->to_string("Sugestão de termo de aceite 3", false, true);

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Pedir aceite também antes da compra do curso?")
                ->set_value_by_config("form_accept_purchase")
                ->set_description("Se marcado, pedirá o check do aceite também antes da compra.")
        );

        $form->return .= "</div>";
    }

    /**
     * Function form
     *
     * @throws Exception
     */
    public function sugestao() {
        global $DB, $CFG, $SITE, $PAGE;

        $PAGE->set_title("Configurações das matrículas");
        $return = "<div class=\"kopere_dashboard-card\">";

        $num = optional_param("num", 1, PARAM_INT);
        $data = [
            "site_moodle" => $SITE->fullname,
            "site_admin" => $CFG->supportemail,
            "site_data" => userdate(time()),
        ];

        if (optional_param("usar", false, PARAM_INT)) {
            require_sesskey();

            if ($num == 0) {
                unset_config("form_ask_accept", "local_kopere_dashboard");
            } else {
                $value = get_string("acceptance_terms_template_{$num}_html", "local_kopere_pay", $data);
                set_config("form_ask_accept", $value, "local_kopere_dashboard");
            }

            $sql = "DELETE FROM {tiny_autosave} WHERE elementid = 'form_ask_accept'";
            $DB->execute($sql);

            redirect(
                new moodle_url("/local/kopere_dashboard/view.php?classname=settings&method=form"),
                "Termo alterado com sucesso!"
            );
        }

        $return .= button::get_instance()
            ->set_primary()
            ->set_link("?classname=settings&method=sugestao&num={$num}&usar=1&sesskey=" . sesskey())
            ->to_string("Gostei deste termo e quero usa-lo", true, true);

        $template = get_string("acceptance_terms_template_{$num}_html", "local_kopere_pay", $data);
        $return .= str_replace("\\n", "\n", $template);

        $return .= "</div>";
        return $return;
    }

    /**
     * Function settings_form
     *
     * @param form $form
     *
     * @throws Exception
     */
    private function settings_form($form) {
        $form->return .= "<div class=\"kopere_dashboard-card\">";
        $form->return .= "<h3>Configurações do Formulário de matrícula</h3>";

        $form->return .= message::warning("Lembre-se: Pedir muitos dados diminui a chance do aluno preencher a matrícula.");

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Habilitar Assinaturas")
                ->set_checked_by_config("form_monthly_fee")
                ->set_description("Se marcado, o sistema habilitará escolha entre assinatura e pagamento único.")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Pedir CPF")
                ->set_checked_by_config("form_pedircpf")
                ->set_description("Se marcado, o sistema pedirá CPF no formulário de matrícula.")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Pedir Data e Nascimento")
                ->set_checked_by_config("form_pedirbirth")
                ->set_description("Se marcado, o sistema pedirá a Data de Nascimento no formulário de matrícula.")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Pedir Telefone")
                ->set_checked_by_config("form_pedir_telefone")
                ->set_description("Se marcado, o sistema pedirá o preenchimento do Telefone fixo (não obrigatório).")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Pedir Celular")
                ->set_checked_by_config("form_pedir_celular")
                ->set_description("Se marcado, o sistema pedirá o preenchimento obrigatório do Telefone Celular.")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Confirmar Email e senha")
                ->set_checked_by_config("form_confirmar")
                ->set_description("Se marcado, o sistema colocará um campo extra para confirmar Senha e confirmar e-mail.")
        );

        $form->add_input(
            input_checkbox_select::new_instance()
                ->set_title("Pedir Endereço")
                ->set_checked_by_config("form_pedir_endereco")
                ->set_description(
                    "
                        Se marcado, o sistema pedirá o preenchimento obrigatório do Endereço.<br>
                        <strong>PS: O endereço será pedido ao sistema de pagamento para preenchimento.
                            Não será pedido no Cadastro do Moodle.</strong>"
                )
        );

        $form->return .= message::warning("Lembre-se: Pedir muitos dados diminui a chance do aluno preencher a matrícula.");

        $form->return .= "</div>";
    }

    /**
     * Function save
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function save() {

        require_sesskey();

        $post = string_util::clear_all_params(null, null, PARAM_RAW);
        foreach ($post as $keyname => $value) {
            $save = true;
            switch ($keyname) {
                case "POST":
                case "action":
                case "redirect":
                    $save = false;
                    break;
                case "kopere_pay-meiodeposito-conta":
                case "formulario_pedir_aceite":
                    if (!input_htmleditor::$editorhtml) {
                        $value = "<div style=\"white-space:break-spaces;\">{$value}</div>";
                    }
                    break;
            }

            if ($save) {
                if (is_array($value)) {
                    set_config($keyname, implode(",", $value), "local_kopere_dashboard");
                } else {
                    set_config($keyname, $value, "local_kopere_dashboard");
                }
            }
        }

        message::schedule_message_success(get_string("setting_saved", "local_kopere_dashboard"));

        $redirect = optional_param("redirect", false, PARAM_TEXT);
        if ($redirect) {
            header::location("?{$redirect}");
        }
    }
}
