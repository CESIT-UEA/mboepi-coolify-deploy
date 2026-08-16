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
 * error_message.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay;

use local_kopere_dashboard\util\message;

/**
 * Class error_message
 */
class error_message {
    /**
     * Function show
     *
     * @param $message
     * @return void
     */
    public static function show($message) {
        global $OUTPUT, $PAGE;

        $PAGE->set_title("Erro");
        echo $OUTPUT->header();

        message::print_danger("Efi respondeu {$message}");

        echo $OUTPUT->footer();
    }
}
