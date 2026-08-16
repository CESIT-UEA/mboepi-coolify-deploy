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
 * phone.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\validate;

/**
 * Class phone
 */
class phone {
    /**
     * Normalize Brazilian phone numbers for validation.
     *
     * Accepts common typed formats such as +55, 55, 0 + DDD and
     * 0 + carrier code + DDD before validating the final national number.
     *
     * @param mixed $phone
     * @return string
     */
    protected static function normalize_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if ($phone === '') {
            return '';
        }

        // International format: +55 48 99999-9999 or 55 48 99999-9999.
        if (strpos($phone, '55') === 0 && strlen($phone) > 11) {
            $withoutcountry = substr($phone, 2);
            if (strlen($withoutcountry) === 10 || strlen($withoutcountry) === 11) {
                $phone = $withoutcountry;
            }
        }

        // National trunk prefix: 0 48 99999-9999.
        if (strpos($phone, '0') === 0 && strlen($phone) === 12) {
            $phone = substr($phone, 1);
        }

        // Long distance with carrier code: 0 41 48 99999-9999.
        if (strpos($phone, '0') === 0 && strlen($phone) === 14) {
            $phone = substr($phone, 3);
        }

        return $phone;
    }

    /**
     * Function validate_celphone
     *
     * @param mixed $phone
     * @return bool
     */
    public static function validate_celphone($phone) {
        $phone = self::normalize_phone($phone);

        return preg_match('/^[1-9]{2}9[0-9]{8}$/', $phone) === 1;
    }

    /**
     * Function validate_fixedphone
     *
     * @param mixed $phone
     * @return bool
     */
    public static function validate_fixedphone($phone) {
        $phone = self::normalize_phone($phone);

        return preg_match('/^[1-9]{2}[2-5][0-9]{7}$/', $phone) === 1;
    }
}
