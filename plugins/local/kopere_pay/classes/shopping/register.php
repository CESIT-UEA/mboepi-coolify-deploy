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
 * register.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\shopping;

use coding_exception;
use dml_exception;
use Exception;
use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_base;
use local_kopere_pay\html\inputs\input_checkbox_select;
use local_kopere_pay\html\inputs\input_email;
use local_kopere_pay\html\inputs\input_password;
use local_kopere_pay\html\inputs\input_select;
use local_kopere_pay\html\inputs\input_text;
use local_kopere_pay\html\inputs\input_textarea;
use local_kopere_dashboard\util\config;
use local_kopere_dashboard\util\header;
use local_kopere_dashboard\util\message;
use local_kopere_pay\util\email_event;
use local_kopere_pay\util\profile_field;
use local_kopere_pay\util\enrollment_util;
use local_kopere_pay\validate\cpf;
use local_kopere_pay\validate\date;
use local_kopere_pay\validate\phone;
use local_kopere_pay\vo\local_kopere_pay_detail;
use moodle_exception;
use profilefield_database\vo\profilefield_database_data;
use stdClass;

/**
 * Class register
 *
 * @package local_kopere_pay\shopping
 */
class register {

    /**
     * Function init
     *
     * @param $course
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return string
     * @throws Exception
     */
    public function init($course, $koperepaydetalhe) {
        global $USER, $iframe, $OUTPUT;

        if (isset($USER->id) && $USER->id > 1) {
            header::location("?id={$koperepaydetalhe->course}&pagar=1&iframe={$iframe}");
        }

        $resume = new resume();

        $data = [
            "show_extra" => isset($koperepaydetalhe->extra[10]) ? true : false,
            "extra" => $koperepaydetalhe->extra,
            "course" => $koperepaydetalhe->course,
            "resume" => $resume->create($koperepaydetalhe),
            "extra-config" => enrollment_util::hide_order_details($koperepaydetalhe),
        ];

        return $OUTPUT->render_from_template("local_kopere_pay/shopping/register-init", $data);
    }

    /**
     * Function show
     *
     * @param $course
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws Exception
     */
    public function show($course, $koperepaydetalhe) {
        global $DB, $CFG, $USER, $OUTPUT, $iframe;

        $config = get_config("local_kopere_dashboard");

        // Student registration.
        $cadastrar = true;
        if (isset($USER->id) && $USER->id > 2) {
            $isvalid = $this->validate($USER, $config, $koperepaydetalhe, false);
            if ($isvalid && !form::check_post()) {
                header::location("?id={$koperepaydetalhe->course}&pagar=1&iframe={$iframe}");
            }
            $cadastrar = false;
        }

        $user = $USER;
        if ($user->id == 1) {
            $user = (object) [
                "profile" => [],
                "email" => "",
                "phone1" => "",
                "phone2" => "",
            ];
        }

        if (form::check_post()) {
            if ($this->cadastro($config, $cadastrar, $koperepaydetalhe)) {
                header::location("?id={$koperepaydetalhe->course}&pagar=1&iframe={$iframe}");
            }
        }

        $form = new form("?id={$koperepaydetalhe->course}&cadastro=1&iframe={$iframe}");
        echo $form->return;
        $form->return = "";

        $formhtml = $form->add_input_return(
            input_text::new_instance()
                ->set_title(get_string("registration_full_name", "local_kopere_pay"))
                ->set_name("nome")
                ->set_value(fullname($user))
                ->set_required()
                ->add_extras('autocomplete="name"')
                ->add_validator(input_base::VAL_NOME)
        );

        if ($config->form_confirmar && $cadastrar) {
            $formhtml .= "<div class='registration-display-flex'>";
            $formhtml .= $form->add_input_return(
                input_email::new_instance()
                    ->set_title(get_string("registration_email", "local_kopere_pay"))
                    ->set_name("email")
                    ->set_value($user->email)
                    ->set_required()
                    ->add_extras('autocomplete="username"')
                    ->add_validator(input_base::VAL_EMAIL)
            );
            $formhtml .= $form->add_input_return(
                input_email::new_instance()
                    ->set_title(get_string("registration_email_confirm", "local_kopere_pay"))
                    ->set_name("email2")
                    ->set_value($user->email)
                    ->set_required()
                    ->add_validator(input_base::VAL_EMAIL)
                    ->add_extras('data-rule-equalTo="#email"')
            );
            $formhtml .= "</div>";
        } else {
            $formhtml .= $form->add_input_return(
                input_email::new_instance()
                    ->set_title(get_string("registration_email", "local_kopere_pay"))
                    ->set_name("email")
                    ->set_value($user->email)
                    ->set_required()
                    ->add_extras('autocomplete="username"')
                    ->add_validator(input_base::VAL_EMAIL)
            );
        }

        if ($config->form_pedircpf && $config->form_pedirbirth) {
            $formhtml .= "<div class='registration-display-flex'>";
            $formhtml .= $form->add_input_return(
                input_text::new_instance()
                    ->set_title(get_string("registration_cpf", "local_kopere_pay"))
                    ->set_name("cpf")
                    ->set_value(@$user->profile["cpf"])
                    ->add_validator(input_base::VAL_CPF)
                    ->add_mask(input_base::MASK_CPF)
                    ->set_required()
            );
            $formhtml .= $form->add_input_return(
                input_text::new_instance()
                    ->set_title(get_string("registration_birthdate", "local_kopere_pay"))
                    ->set_name("birth")
                    ->set_value(@$user->profile["birth"])
                    ->add_mask(input_base::MASK_DATA)
                    ->set_required()
            );
            $formhtml .= "</div>";
        } else {
            if (@$config->form_pedircpf) {
                $formhtml .= $form->add_input_return(
                    input_text::new_instance()
                        ->set_title(get_string("registration_cpf", "local_kopere_pay"))
                        ->set_name("cpf")
                        ->set_value(@$user->profile["cpf"])
                        ->add_validator(input_base::VAL_CPF)
                        ->add_mask(input_base::MASK_CPF)
                        ->set_required()
                );
            }
            if (@$config->form_pedirbirth) {
                $formhtml .= $form->add_input_return(
                    input_text::new_instance()
                        ->set_title(get_string("registration_birthdate", "local_kopere_pay"))
                        ->set_name("birth")
                        ->set_value(@$user->profile["birth"])
                        ->add_mask(input_base::MASK_DATA)
                        ->set_required()
                );
            }
        }

        if (@$config->form_pedir_telefone && @ $config->form_pedir_celular) {
            $formhtml .= "<div class='registration-display-flex'>";
            $formhtml .= $form->add_input_return(
                input_text::new_instance()
                    ->set_title(get_string("registration_phone_landline", "local_kopere_pay"))
                    ->set_name("telefone")
                    ->add_extras('autocomplete="tel"')
                    ->set_value(@$user->phone1)
                    ->add_validator(input_base::VAL_PHONE)
                    ->add_mask(input_base::MASK_PHONE)
            );
            $formhtml .= $form->add_input_return(
                input_text::new_instance()
                    ->set_title(get_string("registration_phone_mobile", "local_kopere_pay"))
                    ->set_name("celular")
                    ->set_value(@$user->phone2)
                    ->set_required()
                    ->add_extras('autocomplete="mobile"')
                    ->add_validator(input_base::VAL_CELPHONE)
                    ->add_mask(input_base::MASK_CELULAR)
            );
            $formhtml .= "</div>";
        } else {
            if (@$config->form_pedir_telefone) {
                $formhtml .= $form->add_input_return(
                    input_text::new_instance()
                        ->set_title(get_string("registration_phone_landline", "local_kopere_pay"))
                        ->set_name("telefone")
                        ->add_extras('autocomplete="tel"')
                        ->set_value($user->phone1)
                        ->add_validator(input_base::VAL_PHONE)
                        ->add_mask(input_base::MASK_PHONE)
                );
            }
            if (@$config->form_pedir_celular) {
                $formhtml .= $form->add_input_return(
                    input_text::new_instance()
                        ->set_title(get_string("registration_phone_mobile", "local_kopere_pay"))
                        ->set_name("celular")
                        ->set_value($user->phone2)
                        ->add_extras('autocomplete="mobile"')
                        ->add_validator(input_base::VAL_CELPHONE)
                        ->add_mask(input_base::MASK_CELULAR)
                        ->set_required()
                );
            }
        }

        if (!$user->id) {
            if (!isset($config->default_password[5])) {
                if (@$config->form_confirmar && $cadastrar) {
                    $formhtml .= "<div class='registration-display-flex'>";
                    $formhtml .= $form->add_input_return(
                        input_password::new_instance()
                            ->set_title(get_string("registration_password", "local_kopere_pay"))
                            ->set_name("senha")
                            ->add_validator(input_base::VAL_PASSWORD)
                            ->set_required()
                    );
                    $formhtml .= $form->add_input_return(
                        input_password::new_instance()
                            ->set_title(get_string("registration_password_confirm", "local_kopere_pay"))
                            ->set_name("senha2")
                            ->add_validator(input_base::VAL_PASSWORD)
                            ->add_extras('data-rule-equalTo="#senha"')
                            ->set_required()
                    );

                    $formhtml .= "</div>";
                } else {
                    $formhtml .= $form->add_input_return(
                        input_password::new_instance()
                            ->set_title(get_string("registration_password", "local_kopere_pay"))
                            ->set_name("senha")
                            ->set_required()
                            ->add_validator(input_base::VAL_PASSWORD)
                    );
                }
            }
        }

        // Campos extras.
        $extrafields = get_config("local_kopere_dashboard", "extra_fields");
        if ($extrafields) {

            if (isset($koperepaydetalhe->fieldcategory) && $koperepaydetalhe->fieldcategory) {
                $extrafields = explode(",", $extrafields);
                $extrafields[] = $koperepaydetalhe->fieldcategory;
                $extrafields = array_map('intval', $extrafields);
                $extrafields = implode(",", $extrafields);
            }

            $sql = "SELECT * FROM {user_info_category} WHERE id IN({$extrafields}) ORDER BY sortorder ASC";
            $categorys = $DB->get_records_sql($sql);
            foreach ($categorys as $category) {

                $sql = "SELECT * FROM {user_info_field} WHERE categoryid = :categoryid ORDER BY sortorder ASC";
                $infofields = $DB->get_records_sql($sql, ["categoryid" => $category->id]);

                $formhtmlinput = "";
                foreach ($infofields as $infofield) {
                    $input = null;

                    if ($infofield->shortname == "birth" || $infofield->shortname == "cpf") {
                        continue;
                    }

                    if ($infofield->datatype == "text") {
                        $input = input_text::new_instance()
                            ->set_title($infofield->name)
                            ->set_name($infofield->shortname)
                            ->set_value(@$user->profile[$infofield->shortname])
                            ->add_extras('autocomplete="mobile"');
                    } else if ($infofield->datatype == "textarea") {
                        $input = input_textarea::new_instance()
                            ->set_title($infofield->name)
                            ->set_name($infofield->shortname)
                            ->set_value(@$user->profile[$infofield->shortname]);

                    } else if ($infofield->datatype == "checkbox") {
                        $input = input_checkbox_select::new_instance()
                            ->set_title($infofield->name)
                            ->set_name($infofield->shortname)
                            ->set_value(@$user->profile[$infofield->shortname]);

                    } else if ($infofield->datatype == "menu") {

                        $values = [["key" => '', "value" => get_string("select_placeholder", "local_kopere_pay")]];
                        foreach (explode("\n", $infofield->param1) as $value) {
                            $values[] = ["key" => $value, "value" => $value];
                        }

                        $input = input_checkbox_select::new_instance()
                            ->set_title($infofield->name)
                            ->set_name($infofield->shortname)
                            ->set_value(@$user->profile[$infofield->shortname])
                            ->set_values($values, "value");
                    } else if ($infofield->datatype == "datetime") {
                        $input = input_text::new_instance()
                            ->set_title($infofield->name)
                            ->set_name($infofield->shortname)
                            ->set_value(@$user->profile[$infofield->shortname])
                            ->set_class(input_base::MASK_DATA);
                    } else if ($infofield->datatype == "url") {
                        $input = input_text::new_instance()
                            ->set_title($infofield->name)
                            ->set_name($infofield->shortname)
                            ->set_value(@$user->profile[$infofield->shortname])
                            ->set_class(input_base::VAL_URL);
                    } else if ($infofield->datatype == "database") {

                        if (file_exists("{$CFG->dirroot}/user/profile/field/database/field.class.php")) {
                            $options = [];
                            $fielddatas = $DB->get_records(
                                "profilefield_database_data",
                                ["categoryid" => $infofield->param1], "data0 ASC", "id,data0"
                            );
                            /** @var profilefield_database_data $fielddata */
                            foreach ($fielddatas as $fielddata) {
                                $options[] = ["key" => $fielddata->id, "value" => $fielddata->data0];
                            }

                            $input = input_select::new_instance()
                                ->set_title($infofield->name)
                                ->set_name($infofield->shortname)
                                ->set_value(@$user->profile[$infofield->shortname])
                                ->set_values($options);
                        }
                    } else {
                        $formhtmlinput .= $infofield->datatype . "<br>";
                    }

                    if ($input) {
                        if ($infofield->required) {
                            $input->set_required();
                        }
                        $formhtmlinput .= $form->add_input_return($input);
                    }
                }

                if (isset($formhtmlinput[5])) {
                    $formhtml .= "<fieldset><legend>{$category->name}</legend>{$formhtmlinput}</fieldset>";
                }
            }
        }

        $formpediraceite = config::get_key("form_ask_accept");
        if (isset($formpediraceite[40])) {
            $data = [
                "check-id" => "form_ask_accept",
                "extra" => get_string("terms_of_service_prompt", "local_kopere_pay"),
            ];
            $formhtml .= $OUTPUT->render_from_template("local_kopere_pay/html/input-checkbox", $data);
        }

        $html = "<input class='btn btn-success bt-submit botao' type=\"submit\" value='" .
            get_string("registration_submit", "local_kopere_pay") . "' />";
        $formhtml .= $form->print_row_return("", $html, "btsubmit");

        $data = [
            "show_extra" => isset($koperepaydetalhe->extra[10]),
            "extra" => $koperepaydetalhe->extra,
            "course" => $koperepaydetalhe->course,
            "formhtml" => $formhtml,
            "resume" => (new resume())->create($koperepaydetalhe),
            "extra-config" => enrollment_util::hide_order_details($koperepaydetalhe),
        ];

        echo $OUTPUT->render_from_template("local_kopere_pay/shopping/register-show", $data);

        echo $form->close_and_return();
    }

    /**
     * Function validate
     *
     * @param $user
     * @param $config
     * @param $koperepaydetalhe
     * @param $duplicado
     * @param bool $isprint
     *
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws \Exception
     */
    public function validate(&$user, $config, $koperepaydetalhe, $duplicado, $isprint = true) {
        global $CFG, $DB;

        $returned = [];

        if (empty($user->firstname) || empty($user->lastname)) {
            $returned[] = get_string("registration_full_name_error", "local_kopere_pay");
        }

        // Checa se e-mail esta vazio.
        if (empty($user->email)) {
            $returned[] = get_string("registration_email_required_error", "local_kopere_pay");
        }

        // Checa e-mail.
        if ($duplicado) {
            if ($config->form_confirmar) {
                if (optional_param("email", "email1", PARAM_TEXT) != optional_param("email2", "email2", PARAM_TEXT)) {
                    $returned[] = get_string("registration_email_mismatch_error", "local_kopere_pay");
                }

                if (optional_param("senha", "senha1", PARAM_TEXT) != optional_param("senha2", "senha2", PARAM_TEXT)) {
                    $returned[] = get_string("registration_password_mismatch_error", "local_kopere_pay");
                }
            }

            if (!isset($config->default_password[5])) {
                // Checa a senha.
                if (!empty($user->password)) {
                    if (!check_password_policy($user->password, $errmsg)) {
                        $returned[] = "* {$errmsg}";
                    }
                    if (strlen($user->password) < 6) {
                        $returned[] = get_string("registration_password_min_length_error", "local_kopere_pay");
                    }
                    if ($user->password == "123456") {
                        $returned[] = get_string("registration_password_common_error", "local_kopere_pay");
                    }
                }
            }

            if (!validate_email($user->email)) {
                $returned[] = "* " . get_string("invalidemail");
            } else {
                // Check if the username already exists.
                $usernametest = $DB->record_exists(
                    "user",
                    [
                        "username" => $user->username,
                        "mnethostid" => $CFG->mnet_localhost_id,
                    ]
                );
                if ($usernametest) {
                    $returned[] = get_string("registration_email_already_exists_error", "local_kopere_pay");
                } else {

                    // Check if the email already exists.
                    $emailtest = $DB->record_exists(
                        "user",
                        [
                            "email" => $user->email,
                            "mnethostid" => $CFG->mnet_localhost_id,
                        ]
                    );
                    if ($emailtest) {
                        $returned[] = "* " . get_string("emailexists");
                    }
                }
            }

            // Checa se os caracteres.
            if ($user->username !== $this->clean_field($user->username, "username")) {
                $returned[] = get_string("registration_email_invalid_chars_error", "local_kopere_pay");
            }
        }

        if (isset($user->profile)) {
            $user->profile = profile_field::profile_user_record_post($koperepaydetalhe, $user->profile);
        } else {
            $user->profile = profile_field::profile_user_record_post($koperepaydetalhe);
        }

        if ($config->form_pedircpf) {
            $defalt = '';
            if (isset($user->profile["cpf"])) {
                $defalt = $user->profile["cpf"];
            } else if (isset($user->profile["CPF"])) {
                $defalt = $user->profile["CPF"];
            } else if (isset($user->profile["Cpf"])) {
                $defalt = $user->profile["Cpf"];
            }
            $cpf = optional_param("cpf", $defalt, PARAM_TEXT);

            if (!cpf::validate($cpf)) {
                $returned[] = get_string("registration_cpf_error", "local_kopere_pay");
            }
        }

        if (@$config->form_pedirbirth) {
            $defalt = '';
            if (isset($user->profile["birth"])) {
                $defalt = $user->profile["birth"];
            }

            $birth = optional_param("birth", $defalt, PARAM_TEXT);
            if (!date::validate($birth)) {
                $returned[] = get_string("registration_birthdate_error", "local_kopere_pay");
            }
        }

        if (@$config->form_pedir_celular) {
            if (!phone::validate_celphone($user->phone2)) {
                $returned[] = get_string("registration_phone_mobile_invalid_error", "local_kopere_pay");
            }
        }

        if (isset($koperepaydetalhe->fieldcategory)) {
            $infofields = profile_field::get_all_user_info_fields($koperepaydetalhe->fieldcategory);
            if ($infofields) {
                foreach ($infofields as $infofield) {
                    if ($infofield->required) {
                        switch ($infofield->datatype) {
                            // Checkbox does not need validation.
                            case "text":
                            case "textarea":
                            case "menu":
                            case "datetime":
                            case "url":
                                if (!isset($user->profile[$infofield->shortname])) {
                                    $returned[] = get_string("validation_fill_field_1", "local_kopere_pay", $infofield->name);
                                } else if (!isset($user->profile[$infofield->shortname][1])) {
                                    $returned[] = get_string("validation_fill_extra_field", "local_kopere_pay", $infofield->name);
                                }
                                break;
                        }
                    }
                }
            }
        }

        $formpediraceite = get_config("local_kopere_dashboard", "form_ask_accept");
        if (isset($formpediraceite[40]) && form::check_post()) {
            $formpediraceite = optional_param("form_ask_accept", 0, PARAM_INT);
            if (!$formpediraceite) {
                $returned[] = get_string("terms_of_service_required", "local_kopere_pay");
            }
        }

        if (!isset($returned[0])) {
            return true;
        } else {
            if ($isprint) {
                echo message::danger("<br>" . implode("<br>", $returned));
            }
        }

        return false;
    }

    /**
     * Function cadastro
     *
     * @param $config
     * @param $cadastrar
     * @param local_kopere_pay_detail $koperepaydetalhe
     *
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function cadastro($config, $cadastrar, $koperepaydetalhe) {
        global $CFG, $DB, $USER;

        $newuser = new stdClass();

        $nome = optional_param("nome", '', PARAM_TEXT);
        $email = optional_param("email", '', PARAM_TEXT);
        $password = optional_param("senha", '', PARAM_TEXT);
        if (isset($config->default_password[5])) {
            $password = $config->default_password;
        }

        $phone1 = optional_param("telefone", '', PARAM_TEXT);
        $phone2 = optional_param("celular", '', PARAM_TEXT);

        $nomes = explode(' ', $nome);
        $firstname = $nomes[0];
        array_shift($nomes);
        $lastname = implode(' ', $nomes);

        $newuser->firstname = $firstname;
        $newuser->lastname = $lastname;
        $newuser->username = strtolower($email);
        $newuser->email = strtolower($email);
        $newuser->address = '.';
        $newuser->city = '.';
        $newuser->country = "BR";
        $newuser->phone1 = $phone1;
        $newuser->phone2 = $phone2;

        if ($cadastrar) {
            $newuser->auth = "manual";
            $newuser->password = $password;

            $newuser->confirmed = 1;
            $newuser->mnethostid = $CFG->mnet_localhost_id;
        }

        if (!$this->validate($newuser, $config, $koperepaydetalhe, $cadastrar)) {
            return false;
        }

        require_once("{$CFG->dirroot}/user/lib.php");
        require_once("{$CFG->libdir}/classes/user.php");
        require_once("{$CFG->libdir}/moodlelib.php");

        if ($cadastrar) {
            try {
                $newuser->id = user_create_user($newuser);
            } catch (Exception $e) {
                echo message::danger(get_string("registration_create_user_error", "local_kopere_pay", $e->getMessage()));

                return false;
            }
        } else {
            try {
                $newuser->id = $USER->id;
                user_update_user($newuser, false);
            } catch (Exception $e) {
                echo message::danger(get_string("registration_update_user_error", "local_kopere_pay", $e->getMessage()));

                return false;
            }
        }

        if (!empty($config->form_pedircpf)) {
            $cpf = optional_param("cpf", '', PARAM_TEXT);
            profile_field::add_field("cpf", "CPF", $newuser->id, $cpf);
        }
        if (!empty($config->form_pedirbirth)) {
            $birth = optional_param("birth", '', PARAM_TEXT);
            profile_field::add_field("birth", get_string("birthdate", "local_kopere_pay"), $newuser->id, $birth);
        }

        foreach (profile_field::get_all_user_info_fields($koperepaydetalhe->fieldcategory) as $infofield) {
            $campo = optional_param($infofield->shortname, '', PARAM_TEXT);
            profile_field::add_field($infofield->shortname, $infofield->name, $newuser->id, $campo);
        }

        if ($cadastrar) {
            $user = $DB->get_record("user", ["id" => $newuser->id], '*', IGNORE_MULTIPLE);

            $USER->tmp_password = $password;

            email_event::user_created($user, $password);

            complete_user_login($user);
        }

        return true;
    }

    /**
     * Function clean_field
     *
     * @param $data
     * @param $field
     *
     * @return mixed
     * @throws coding_exception
     */
    public function clean_field($data, $field) {
        if (empty($data) || empty($field)) {
            return $data;
        }

        $data = clean_param($data, PARAM_TEXT);

        return $data;
    }
}
