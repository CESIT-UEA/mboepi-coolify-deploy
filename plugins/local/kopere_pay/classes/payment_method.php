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
 * payment_method.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use local_kopere_pay\html\form;
use local_kopere_pay\html\inputs\input_checkbox_select;
use local_kopere_pay\meios\IMeio;

/**
 * Class payment_method
 */
class payment_method extends base {

    /**
     * Function dashboard
     *
     * @return string
     * @throws \coding_exception
     */
    public function dashboard() {
        global $PAGE;
        $listameios = self::list_meios();

        $PAGE->set_title(get_string("menu_payment_methods", "local_kopere_pay"));

        $return = $this->tabs($listameios);
        $return .= "<div class=\"kopere_dashboard-card\">";

        $redirect = urlencode("classname=payment_method&method=dashboard");
        $form = new form("?classname=settings&method=save&redirect={$redirect}");

        foreach ($listameios as $meio) {
            if ($meio["escolha"]) {
                $form->add_input(
                    input_checkbox_select::new_instance()
                        ->set_title(get_string("payment_method_enable", "local_kopere_pay", $meio["name"]))
                        ->set_checked_by_config("kopere_pay-habilitar-{$meio["class"]}")
                );
            }
        }

        $form->create_submit_input(get_string('savechanges'));
        $return .= $form->close_and_return();

        $return .= "</div>";
        return $return;
    }

    /**
     * Function edit
     *
     * @return string
     * @throws \coding_exception
     */
    public function edit() {
        global $PAGE;

        $meio = optional_param('meio', '', PARAM_TEXT);
        $listameios = self::list_meios();

        $PAGE->set_title("Meios de Pagamentos");
        $return = $this->tabs($listameios);
        $return .= "<div class=\"kopere_dashboard-card\">";

        $redirect = urlencode("classname=payment_method&method=edit&meio={$meio}");
        $form = new form("?classname=settings&method=save&redirect={$redirect}");

        /** @var IMeio $class */
        $class = "local_kopere_pay\\meios\\{$meio}";

        $class::edit($form);

        $form->create_submit_input(get_string('savechanges'));
        $return .= $form->close_and_return();

        $return .= "</div>";
        return $return;
    }

    /**
     * Function list_meios
     *
     * @return array
     */
    public static function list_meios() {
        global $CFG;

        $classesmeios = glob("{$CFG->dirroot}/local/kopere_pay/classes/meios/*.php");
        $listameios = [];

        foreach ($classesmeios as $classe) {
            preg_match("/\/(\w+).php/", $classe, $outputarray);

            if (isset($outputarray[1])) {
                /** @var IMeio $class */
                $class = $outputarray[1];
                if ($class == 'IMeio') {
                    continue;
                }

                $class = "local_kopere_pay\\meios\\{$class}";
                $listameios[] = $class::get_name();
            }
        }
        return $listameios;
    }

    /**
     * Function tabs
     *
     * @param $listameios
     * @return string
     * @throws \coding_exception
     */
    private function tabs($listameios) {

        $return = "<ul class=\"nav nav-tabs\">";

        $meioselected = optional_param('meio', false, PARAM_TEXT);

        $class = isset($meioselected[2]) ? '' : 'active';
        $return .= "<li class='{$class}'><a href=\"?classname=payment_method&method=dashboard\">Meios de Pagamentos</a></li>";

        foreach ($listameios as $meio) {
            if ($meio['enable'] && $meio['escolha']) {
                $class = $meioselected == $meio['class'] ? 'active' : '';
                $return .= "<li class='{$class}'><a href=\"?classname=payment_method&method=edit&meio={$meio['class']}\">
                        {$meio['name']}</a></li>";
            }
        }

        $return .= "</ul>";

        return $return;
    }
}
