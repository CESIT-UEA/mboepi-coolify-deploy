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
 * campo.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use local_kopere_pay\vo\local_kopere_pay_detail;

/**
 * Class profile_field
 *
 * @package local_kopere_pay
 */
class profile_field {

    /** @var string */
    public static $categoryname = "Dados pessoais";

    /**
     * Returns a profile value using common shortname aliases.
     *
     * @param object $user
     * @param string $shortname
     * @param array $aliases
     * @return string
     */
    public static function get_user_profile_value($user, $shortname, $aliases = []) {
        if (!isset($user->profile) || !is_array($user->profile)) {
            return '';
        }

        $keys = array_merge([$shortname], $aliases, [strtoupper($shortname), ucfirst($shortname)]);

        foreach ($keys as $key) {
            if (isset($user->profile[$key]) && $user->profile[$key] !== '') {
                return $user->profile[$key];
            }
        }

        return '';
    }

    /**
     * Function profile_user_record_post
     *
     * @param local_kopere_pay_detail $koperepaydetalhe
     * @param array $profile
     * @return array|mixed
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function profile_user_record_post($koperepaydetalhe, $profile = []) {
        if (!isset($koperepaydetalhe->fieldcategory)) {
            return $profile;
        }

        $userinfofields = self::get_all_user_info_fields($koperepaydetalhe->fieldcategory);

        if (!$userinfofields) {
            return $profile;
        }

        foreach ($userinfofields as $userinfofield) {
            if (isset($_POST[$userinfofield->shortname])) {
                $profile[$userinfofield->shortname] = optional_param($userinfofield->shortname, "", PARAM_TEXT);
            }
        }
        return $profile;
    }

    /**
     * Function get_all_user_info_fields
     *
     * @param int|null $fieldcategory
     *
     * @return array
     *
     * @throws \dml_exception
     */
    public static function get_all_user_info_fields($fieldcategory = null) {
        global $DB;

        $extrafields = get_config("local_kopere_dashboard", "extra_fields");
        if (!$extrafields && $fieldcategory) {
            return null;
        }

        if ($fieldcategory) {
            $extrafields = explode(",", $extrafields);
            $extrafields[] = $fieldcategory;
            $extrafields = array_map('intval', $extrafields);
            $extrafields = implode(",", $extrafields);
        }

        if (!isset($extrafields[1])) {
            return [];
        }

        $sql = "SELECT * FROM {user_info_field} WHERE categoryid IN({$extrafields}) ORDER BY sortorder ASC";
        return $DB->get_records_sql($sql);
    }

    /**
     * Function add_field
     *
     * @param $shortname
     * @param $name
     * @param $userid
     * @param $value
     *
     * @throws \dml_exception
     */
    public static function add_field($shortname, $name, $userid, $value) {
        global $DB;

        $shortnames = [
            "birth",
            "cpf",
        ];

        $infofield = $DB->get_record_select("user_info_field", 'shortname LIKE ?', [$shortname]);

        if (!$infofield && in_array($shortname, $shortnames)) {

            $userinfocategory = $DB->get_record("user_info_category", ["name" => self::$categoryname]);
            if (!$userinfocategory) {
                $userinfocategory = (object) [
                    "name" => self::$categoryname,
                    "sortorder" => 1,
                ];
                $userinfocategory->id = $DB->insert_record("user_info_category", $userinfocategory);
            }

            $infofield = (object) [
                "shortname" => $shortname,
                "name" => $name,
                "datatype" => "text",
                "description" => "Campo {$name} do Kopere Pagamento",
                "descriptionformat" => 1,
                "categoryid" => $userinfocategory->id,
                "sortorder" => 99,
                "required" => 0,
                "locked" => 0,
                "visible" => 1,
                "forceunique" => 0,
                "signup" => 0,
                "defaultdata" => "",
                "defaultdataformat" => 0,
                "param1" => 2048,
                "param2" => 2048,
                "param3" => 0,
                "param4" => "",
                "param5" => "",
            ];

            $infofield->id = $DB->insert_record("user_info_field", $infofield);
        }

        // Bug caso altera para maiúscula.
        if ($infofield->shortname != $shortname) {
            $infofield->shortname = $shortname;
            $DB->update_record("user_info_field", $infofield);
        }

        $userinfodata = $DB->get_record("user_info_data", [
            "userid" => $userid,
            "fieldid" => $infofield->id,
        ]);

        if ($value && isset($value[2])) {
            if ($userinfodata) {
                $userinfodata->data = $value;
                $DB->update_record("user_info_data", $userinfodata);
            } else {
                $userinfodata = (object) [
                    "userid" => $userid,
                    "fieldid" => $infofield->id,
                    "data" => $value,
                    "dataformat" => 0,
                ];

                $DB->insert_record("user_info_data", $userinfodata);
            }
        }
    }
}
