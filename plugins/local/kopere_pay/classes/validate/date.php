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
 * date.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\validate;

/**
 * Class date
 */
class date {
    /**
     * Function validate
     *
     * @param mixed $data
     * @return bool
     */
    public static function validate($data) {
        $data = trim((string) $data);
        $data = str_replace(['-', '.'], '/', $data);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
        } else if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $data, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
        } else {
            return false;
        }

        if (!checkdate($month, $day, $year)) {
            return false;
        }

        if ($year < 1900) {
            return false;
        }

        $birthdate = \DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day));
        $today = new \DateTimeImmutable('today');

        if (!$birthdate || $birthdate > $today) {
            return false;
        }

        return true;
    }
}
