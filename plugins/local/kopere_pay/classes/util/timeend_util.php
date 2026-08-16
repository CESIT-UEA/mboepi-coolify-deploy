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
 * timeend_util.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class timeend_util
 */
class timeend_util {
    /**
     * Function calculate_day
     *
     * @param local_kopere_pay_detail $detalhe
     *
     * @return int
     */
    public static function calculate_day($detalhe) {
        $timeend = 0;

        if ($detalhe->charge == 'unico') {
            if ($detalhe->days) {
                $now = date("Y-m-d H:i:s");
                $timeend = strtotime("{$now} + {$detalhe->days} days");
            } else if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $detalhe->date)) {
                $data = preg_replace('/(\d+)\/(\d+)\/(\d+)/', '$3-$2-$1', $detalhe->date);
                $timeend = strtotime("{$data} 23:59:00");
            }
        } else {
            if ($detalhe->days) {
                $now = date("Y-m-d H:i:s");
                $timeend = strtotime("{$now} + {$detalhe->days} months");
            }
        }

        return $timeend;
    }

    /**
     * Function calculate_month
     *
     * @param local_kopere_pay_detail $detalhe
     *
     * @return int
     * @throws \DateMalformedStringException
     */
    public static function calculate_month($detalhe) {
        $timeend = 0;
        if ($detalhe->days) {
            $now = date("Y-m-d H:i:s");
            $timeend = strtotime("{$now} + {$detalhe->days} month");
        } else if ($detalhe->date) {
            $data = new \DateTime($detalhe->date . ' 23:59');
            $timeend = $data->format('U');
        }

        return $timeend;
    }
}
