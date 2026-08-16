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
 * update_checker.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use core\update\checker_exception;

/**
 * Class update_checker
 *
 * @package local_kopere_pay\util
 */
class update_checker extends \core\update\checker {

    /**
     * update_checker constructor.
     */
    public function __construct() {
    }

    /**
     * Function fetch
     *
     * @return object|null
     */
    public function fetch() {
        try {
            $response = $this->get_response();
        } catch (checker_exception $e) {
            return null;
        }

        return json_decode($response);
    }

    /**
     * Returns the list of HTTP params to be sent to the updates provider URL
     *
     * @return array of (string)param => (string)value
     */
    protected function prepare_request_params() {
        $this->load_current_environment();
        $this->restore_response();

        $params = [
            "format" => "json",
            "version" => $this->currentversion,
            "branch" => $this->currentbranch,
            "plugins" => "theme_boost_magnific@2024080500,theme_degrade@2024080500",
        ];

        if (isset($this->recentresponse["ticket"])) {
            $params["ticket"] = $this->recentresponse["ticket"];
        }

        return $params;
    }
}
