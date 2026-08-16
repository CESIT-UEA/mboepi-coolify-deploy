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
 * formater.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

/**
 * Class formater
 */
class formater {

    /**
     * Function price_to_float
     *
     * @param $number
     * @return float
     */
    public static function price_to_float($number) {
        if (is_numeric($number)) {
            return floatval($number);
        } else if (strpos($number, ".") && strpos($number, ",")) {
            $number = str_replace(".", "", $number);
            $number = str_replace(",", ".", $number);

            return floatval("0{$number}");
        } else {
            $number = str_replace(",", ".", $number);
            return floatval("0{$number}");
        }
    }

    /**
     * Function number_formater
     *
     * @param $number
     * @return string
     */
    public static function number_formater($number) {
        $number = self::price_to_float($number);
        return number_format($number, 2, ',', '.');
    }

    /**
     * Function only_number
     *
     * @param $number
     * @return array|string|string[]|null
     */
    public static function only_number($number) {
        return preg_replace('/[^0-9]/', '', $number);
    }
}
