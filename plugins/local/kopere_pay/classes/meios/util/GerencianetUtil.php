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
 * GerencianetUtil.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\meios\util;

defined('MOODLE_INTERNAL') || die;

/**
 * Utility helpers for Efi (Gerencianet) charge statuses.
 *
 * This class converts API status codes into human-readable, localized strings.
 */
class GerencianetUtil {

    /**
     * Get a localized description for an Efi (Gerencianet) charge status.
     *
     * @param string $status The raw status code returned by the gateway.
     * @return string Localized description when known; otherwise the raw status code.
     */
    public static function getStatus(string $status): string {
        $knownstatuses = [
            "new",
            "waiting",
            "paid",
            "unpaid",
            "refunded",
            "contested",
            "canceled",
            "settled",
        ];

        if (!in_array($status, $knownstatuses, true)) {
            return $status;
        }

        $stringid = "efi_status_" . $status;

        // Safe fallback if the string is missing.
        if (!get_string_manager()->string_exists($stringid, "local_kopere_pay")) {
            return $status;
        }

        return get_string($stringid, "local_kopere_pay");
    }
}
