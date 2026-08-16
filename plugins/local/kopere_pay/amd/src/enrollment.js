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
 * enrollment.js
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "local_kopere_pay/maskedinput", "local_kopere_pay/validate"], function ($) {
    return {
        init: function () {
            $("#media-header .media-tab").click(function () {
                $("#media-header .media-tab").removeClass("active");

                var meio = $(this)
                    .addClass("active")
                    .attr("data-meio");

                $(".media-tab-area").hide();
                $("#meio-" + meio).show();
            });

            var firstChild = $("#media-header .media-tab:first-child");
            firstChild.addClass("active");
            var meio = firstChild.attr("data-meio");
            $("#meio-" + meio).show();
        },

        aceite: function () {
            $(".botao-meio button").prop("disabled", true);
            $(".bloco-aceite").show(200);

            $("#form_ask_accept").change(function () {
                if ($(this).is(":checked")) {
                    $(".botao-meio button").prop("disabled", false);
                    $(".well2 .alert").hide(300);
                } else {
                    $(".botao-meio button").prop("disabled", true);
                    $(".well2 .alert").show(300);
                }
            });
        },

        form: function () {
            // mackInputs()
            $("input.mask_phone").mask("(99) 9999-9999");
            $("input.mask_celphone").mask("(99) 9 9999-9999");
            $("input.mask_cep").mask("99999-999");
            $("input.mask_cpf,input.val_cpf").mask("999.999.999-99");
            $("input.mask_cnpj").mask("99.999.999/9999-99");
            $("input.mask_datahora").mask("99/99/9999 99:99");
            $("input.mask_data").mask("99/99/9999");
            $("input.mask_relatorioData").mask("9999-99");
            $("input.mask_int").keyup(function () {
                var text = $(this).val();
                $(this).val(text.replace(/[^\d]/, ""));
            });
            $("input.mask_float").keyup(function () {
                var text = $(this).val();
                $(this).val(text.replace(/[^\d,]/, ""));
            });
            $("input.mask_valor").keyup(function () {
                var text = $(this).val();
                text = text.replace(/[^\d]/, "");
                text = parseInt(text).toString();
                if (text == "NaN")
                    text = "";
                text = text.substr(0, text.length - 2) + "," + text.substr(text.length - 2, text.length);
                $(this).val(text);
            });

            function loadValidateAll() {
                $("form.validate").validate({
                    invalidHandler: function (e, validator) {
                        var errors = validator.numberOfInvalids();
                        if (errors) {
                            var message = M.util.get_string("registration_form_n_missing", "local_kopere_pay").replace("{errors}", errors);
                            if (errors == 1) {
                                message = M.util.get_string("registration_form_missing", "local_kopere_pay");
                            }
                            $("div.displayErroForm span").html(message);
                            $("div.displayErroForm").show();
                        } else {
                            $("div.displayErroForm").hide();
                        }
                    }
                });

                $.validator.addMethod("phoneVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        return value.match(/^\([1-9]{2}\)\ [0-9]{4}\-[0-9]{4}$/);
                    }
                    return true;

                }, M.util.get_string("registration_phone_invalid_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_phone = {phoneVal: true};

                $.validator.addMethod("celphoneVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        return value.match(/^\([1-9]{2}\)\ 9\ [0-9]{4}\-[0-9]{4}$/);
                    }
                    return true;

                }, M.util.get_string("registration_phone_mobile_invalid_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_celphone = {celphoneVal: true};

                $.validator.addMethod("cepVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        return value.match(/^[0-9]{5}\-[0-9]{3}$/);
                    }
                    return true;
                }, M.util.get_string("registration_cep_invalid_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_cep = {cepVal: true};

                $.validator.addMethod("cpfVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        return value.match(/^[0-9]{3}\.[0-9]{3}\.[0-9]{3}\-[0-9]{2}$/);
                    }
                    return true;
                }, M.util.get_string("registration_cpf_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_cpf = {cpfVal: true};

                $.validator.addMethod("emailVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        return value.match(/^.*@.*\..*$/);
                    }
                    return true;
                }, M.util.get_string("registration_email_invalid_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_email = {emailVal: true};

                $.validator.addMethod("passwordVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        if (value.length < 6) {
                            return false;
                        }
                        if (value == "123456") {
                            return false;
                        }
                    }
                    return true;
                }, M.util.get_string("registration_password_min_length_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_password = {passwordVal: true};

                $.validator.addMethod("nomeVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        value = value.replace(/^\s+|\s+$/g, "");
                        if (value.indexOf(' ') > 0) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                    return true;
                }, M.util.get_string("registration_full_name_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_nome = {nomeVal: true};

                $.validator.addMethod("cnpjVal", function (value, element) {
                    if ($(element).hasClass('required')) {
                        return value.match(/^[0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}\-[0-9]{2}$/);
                    }
                    return true;
                }, M.util.get_string("registration_cnpj_error", "local_kopere_pay"));
                $.validator.classRuleSettings.val_cnpj = {cnpjVal: true};

                /*
                 * phone
                 * ^\([1-9][0-9]\)\ [0-9][0-9][0-9][0-9]\-[0-9][0-9][0-9][0-9]$
                 *
                 * cep
                 * ^[0-9][0-9][0-9][0-9][0-9]\-[0-9][0-9][0-9]$
                 *
                 * cpf
                 * ^[0-9][0-9][0-9]\.[0-9][0-9][0-9]\.[0-9][0-9][0-9]\-[0-9][0-9]$
                 *
                 * cnpj
                 * ^[0-9][0-9]\.[0-9][0-9][0-9]\.[0-9][0-9][0-9]\/[0-9][0-9][0-9][0-9]\-[0-9][0-9]$
                 *
                 * datahora
                 * ^[0-9][0-9]\/[0-9][0-9]\/[0-9][0-9][0-9][0-9]\ [0-9][0-9]\:[0-9][0-9]$
                 *
                 * data
                 * ^[0-9][0-9]\/[0-9][0-9]\/[0-9][0-9][0-9][0-9]$
                 */
            };
            loadValidateAll();

        },
    };
});
