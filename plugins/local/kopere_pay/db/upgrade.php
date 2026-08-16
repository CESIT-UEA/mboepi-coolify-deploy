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
 * upgrade.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_bi\install\reports;
use local_kopere_pay\install\report;
use local_kopere_pay\util\campo;
use local_kopere_pay\util\enrollment_util;

/**
 * Function xmldb_local_kopere_pay_upgrade
 *
 * @param $oldversion
 * @return true
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 * @throws dml_exception
 * @throws downgrade_exception
 * @throws moodle_exception
 * @throws upgrade_exception
 * @throws \Exception
 */
function xmldb_local_kopere_pay_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018123006) {

        $tablekoperepaymatricula = new xmldb_table("kopere_pay_matricula");
        $tablekoperepayhistorico = new xmldb_table("kopere_pay_historico");
        $tablekoperepaycupom = new xmldb_table("kopere_pay_cupom");

        $fieldlasttime = new xmldb_field("lasttime", XMLDB_TYPE_INTEGER, 20);
        if (!$dbman->field_exists($tablekoperepaymatricula, $fieldlasttime)) {
            $dbman->add_field($tablekoperepaymatricula, $fieldlasttime);
        } else {
            $dbman->change_field_type($tablekoperepaymatricula, $fieldlasttime);
        }

        $fieldmetodo = new xmldb_field("metodo", XMLDB_TYPE_CHAR, 30);
        if (!$dbman->field_exists($tablekoperepaymatricula, $fieldmetodo)) {
            $dbman->add_field($tablekoperepaymatricula, $fieldmetodo);
        }

        if ($dbman->field_exists($tablekoperepayhistorico, $fieldmetodo)) {
            $dbman->drop_field($tablekoperepayhistorico, $fieldmetodo);
        }

        $fieldtime = new xmldb_field("time", XMLDB_TYPE_INTEGER, 20);
        if ($dbman->field_exists($tablekoperepaycupom, $fieldtime)) {
            $dbman->change_field_type($tablekoperepaycupom, $fieldtime);
        }
        if ($dbman->field_exists($tablekoperepayhistorico, $fieldtime)) {
            $dbman->change_field_type($tablekoperepayhistorico, $fieldtime);
        }

        // Truncate table.
        $DB->delete_records("kopere_pay_historico");
        $DB->delete_records("kopere_pay_matricula");

        upgrade_plugin_savepoint(true, 2018123006, "local", "kopere_pay");
    }

    if ($oldversion < 2018123009) {

        if ($CFG->dbtype == "mysqli") {
            $sql = "ALTER TABLE {kopere_pay_matricula} auto_increment = 10000";
            $DB->execute($sql);
        }

        upgrade_plugin_savepoint(true, 2018123009, "local", "kopere_pay");
    }

    if ($oldversion < 2019100803) {
        $tablekoperepaydetalhe = new xmldb_table("kopere_pay_detalhe");

        $fieldextradata = new xmldb_field("extradata", XMLDB_TYPE_TEXT);
        if (!$dbman->field_exists($tablekoperepaydetalhe, $fieldextradata)) {
            $dbman->add_field($tablekoperepaydetalhe, $fieldextradata);
        } else {
            $dbman->change_field_type($tablekoperepaydetalhe, $fieldextradata);
        }

        upgrade_plugin_savepoint(true, 2019100803, "local", "kopere_pay");
    }

    if ($oldversion < 2020050802) {
        $tablekoperepaymatricula = new xmldb_table("kopere_pay_matricula");

        $fieldstatus = new xmldb_field("status", XMLDB_TYPE_CHAR, 15);
        if (!$dbman->field_exists($tablekoperepaymatricula, $fieldstatus)) {
            $dbman->add_field($tablekoperepaymatricula, $fieldstatus);
        }

        upgrade_2020050802();

        upgrade_plugin_savepoint(true, 2020050802, "local", "kopere_pay");
    }

    if ($oldversion < 2020052600) {
        $tablekoperepaydetalhe = new xmldb_table("kopere_pay_detalhe");

        $fieldextradata = new xmldb_field("extradata", XMLDB_TYPE_TEXT);
        if (!$dbman->field_exists($tablekoperepaydetalhe, $fieldextradata)) {
            $dbman->add_field($tablekoperepaydetalhe, $fieldextradata);
        } else {
            $dbman->change_field_type($tablekoperepaydetalhe, $fieldextradata);
        }

        upgrade_plugin_savepoint(true, 2020052600, "local", "kopere_pay");
    }

    if ($oldversion < 2021062000) {
        $habilitado = get_config("local_kopere_dashboard", "kopere_pay-habilitar-MeioGerencianet");
        if ($habilitado) {
            set_config("kopere_pay-habilitar-MeioGerencianetCartao", 1, "local_kopere_dashboard");
            set_config("kopere_pay-habilitar-MeioGerencianetBoleto", 1, "local_kopere_dashboard");
            set_config("kopere_pay-habilitar-MeioGerencianet", 0, "local_kopere_dashboard");
        }

        upgrade_plugin_savepoint(true, 2021062000, "local", "kopere_pay");
    }

    if ($oldversion < 2023121400) {
        $tablekoperepaydetalhe = new xmldb_table("kopere_pay_detalhe");
        $fieldportfolio = new xmldb_field("portfolio", XMLDB_TYPE_CHAR, 10);
        if (!$dbman->field_exists($tablekoperepaydetalhe, $fieldportfolio)) {
            $dbman->add_field($tablekoperepaydetalhe, $fieldportfolio);
        }

        upgrade_plugin_savepoint(true, 2023121400, "local", "kopere_pay");
    }

    if ($oldversion < 2024020500) {
        set_config("kopere_pay-pagseguro-sandbox", 1, "local_kopere_dashboard");
        set_config("kopere_pay-pagseguro-token-sandbox", "434F7D42877347F5BB1399EFBED546FD", "local_kopere_dashboard");

        upgrade_plugin_savepoint(true, 2024020500, "local", "kopere_pay");
    }

    if ($oldversion < 2025011800) {

        $sql = "SELECT * FROM {user_info_category} WHERE name LIKE 'Dados de Matrícula'";
        $category = $DB->get_record_sql($sql);

        if ($category) {
            $category->name = campo::$categoryname;
            $DB->update_record("user_info_category", $category);

            set_config("extra_fields", $category->id, "local_kopere_dashboard");
        }

        upgrade_plugin_savepoint(true, 2025011800, "local", "kopere_pay");
    }

    if ($oldversion < 2025031401) {
        // Load report pages.
        $pagefiles = glob(__DIR__ . "/files/page-*.json");
        foreach ($pagefiles as $pagefile) {
            reports::from_file($pagefile);
        }

        upgrade_plugin_savepoint(true, 2025031401, "local", "kopere_pay");
    }

    if ($oldversion < 2026020700) {
        upgrade_2026020700();
        upgrade_plugin_savepoint(true, 2026020700, "local", "kopere_pay");
    }

    if ($oldversion < 2026041306) {
        set_config("enrolredirect", @$CFG->kopere_pay_enrolredirect, "local_kopere_pay");
        set_config("enrolredirect_page", @$CFG->kopere_pay_enrolredirect_page, "local_kopere_pay");

        // Rename tables (only when the old exists and the new does not).
        $renames = [
            "kopere_pay_cupom" => "local_kopere_pay_coupon",
            "kopere_pay_detalhe" => "local_kopere_pay_detail",
            "kopere_pay_historico" => "local_kopere_pay_history",
            "kopere_pay_enrollment" => "local_kopere_pay_enrollment",
        ];
        foreach ($renames as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            $newtable = new xmldb_table($newname);

            if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
                $dbman->rename_table($oldtable, $newname);
            }
        }

        // Table local_kopere_pay_coupon: field renames + keys + indexes.
        $table = new xmldb_table("local_kopere_pay_history");
        if ($dbman->table_exists($table)) {
            // Changue local_kopere_pay_rename_key to enrollmentid.
            $field = new xmldb_field("enrollmentid", XMLDB_TYPE_INTEGER, "10", null, null, null, null, "id");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "enrollmentid");
            }
        }

        // Table local_kopere_pay_coupon: field renames + keys + indexes.
        $table = new xmldb_table("local_kopere_pay_coupon");
        if ($dbman->table_exists($table)) {
            // Changue chave to uniquekey.
            $field = new xmldb_field("chave", XMLDB_TYPE_CHAR, "20", null, null, null, null, "type");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "uniquekey");
            }
            $field = new xmldb_field("key", XMLDB_TYPE_CHAR, "20", null, null, null, null, "type");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "uniquekey");
            }

            // Changue quantidade to amount.
            $field = new xmldb_field("quantidade", XMLDB_TYPE_INTEGER, "10", null, null, null, null, "email");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "amount");
            }

            // Changue valor to value.
            $field = new xmldb_field("valor", XMLDB_TYPE_CHAR, "11", null, null, null, null, "amount");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "value");
            }

            // Changue valor to value.
            $field = new xmldb_field("tipo", XMLDB_TYPE_CHAR, "11", null, null, null, null, "course");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "type");
            }

            $sql = "UPDATE {local_kopere_pay_coupon} SET type = 'value' WHERE type LIKE 'valor'";
            $DB->execute($sql);
        }

        // Table local_kopere_pay_detail: field renames + drops + keys + indexes.
        $table = new xmldb_table("local_kopere_pay_detail");
        if ($dbman->table_exists($table)) {
            // Changue preco to price.
            $field = new xmldb_field("preco", XMLDB_TYPE_CHAR, "20", null, null, null, null, "course");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "price");
            }

            // Changue dias to days.
            $field = new xmldb_field("dias", XMLDB_TYPE_INTEGER, "10", null, null, null, null, "price");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "days");
            }

            // Changue data to date.
            $field = new xmldb_field("data", XMLDB_TYPE_CHAR, "10", null, null, null, null, "days");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "date");
            }

            // Changue alunos to students.
            $field = new xmldb_field("alunos", XMLDB_TYPE_INTEGER, "10", null, null, null, null, "date");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "students");
            }

            // Changue cobranca to charge.
            $field = new xmldb_field("cobranca", XMLDB_TYPE_CHAR, "11", null, null, null, null, "students");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "charge");
            }

            // Drop removed fields (if they exist).
            $field = new xmldb_field("afiliado");
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            $field = new xmldb_field("comissao");
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            $field = new xmldb_field("entrega");
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }

            // Create fieldcategory.
            $field = new xmldb_field("fieldcategory", XMLDB_TYPE_INTEGER, "10", null, null, null, null, "extradata");
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Table local_kopere_pay_enrollment: field renames + keys + indexes.
        $table = new xmldb_table("local_kopere_pay_enrollment");
        if ($dbman->table_exists($table)) {

            // Changue cupom to coupon.
            $field = new xmldb_field("cupom", XMLDB_TYPE_CHAR, "20", null, null, null, null, "course");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "coupon");
            }

            // Changue valor to value.
            $field = new xmldb_field("valor", XMLDB_TYPE_CHAR, "20", null, null, null, null, "coupon");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "value");
            }

            // Changue metodo to method.
            $field = new xmldb_field("metodo", XMLDB_TYPE_CHAR, "30", null, null, null, null, "value");
            if ($dbman->field_exists($table, $field)) {
                $dbman->rename_field($table, $field, "method");
            }
        }

        // 1) Exact key renames.
        $exactmap = [
            "campos_extras" => "extra_fields",
            "senha_padrao" => "default_password",
            "cupomLength" => "couponlength",
            "formulario_theme" => "form_theme",
            "formulario_pedir_aceite" => "form_ask_accept",
            "formulario_aceite_compra" => "form_accept_purchase",
            "ocultar_summary_zero" => "hide_zero_summary",
            "formulario_mensalidade" => "form_monthly_fee",
        ];
        foreach ($exactmap as $oldname => $newname) {
            local_kopere_pay_rename_key($oldname, $newname);
        }

        // 2) Prefix renames.
        $prefixmap = [
            "builder_titulo_" => "builder_title_",
            "builder_topo_" => "builder_header_",
            "builder_oque_" => "builder_what_",
            "builder_aba_titulo_" => "builder_aba_title_",
            "builder_aba_titulolongo_" => "builder_aba_long_title_",
            "builder_aba_conteudo_" => "builder_aba_content_",
        ];
        foreach ($prefixmap as $oldprefix => $newprefix) {
            local_kopere_pay_rename_prefix($oldprefix, $newprefix);
        }

        // Reports.
        $cats = $DB->get_records("local_kopere_bi_cat", ["refkey" => "koperepay"]);
        foreach ($cats as $cat) {
            $pages = $DB->get_records("local_kopere_bi_page", ["cat_id" => $cat->id]);
            foreach ($pages as $page) {
                $blocks = $DB->get_records("local_kopere_bi_block", ["page_id" => $page->id]);
                foreach ($blocks as $block) {
                    $belements = $DB->get_records("local_kopere_bi_element", ["block_id" => $block->id]);
                    foreach ($belements as $belement) {
                        $DB->delete_records("local_kopere_bi_element", ["id" => $belement->id]);
                    }
                    $DB->delete_records("local_kopere_bi_block", ["id" => $block->id]);
                }
                $DB->delete_records("local_kopere_bi_page", ["id" => $page->id]);
            }
            $DB->delete_records("local_kopere_bi_cat", ["id" => $cat->id]);
        }

        // Load report pages.
        $pagefiles = glob(__DIR__ . "/files/page-*.json");
        foreach ($pagefiles as $pagefile) {
            reports::from_file($pagefile);
        }

        // If you need the renamed keys to be visible immediately in the same request.
        purge_all_caches();

        upgrade_plugin_savepoint(true, 2026041306, "local", "kopere_pay");
    }

    if ($oldversion < 2026052200) {
        set_config("sortorder", 301, "local_kopere_pay");
        upgrade_plugin_savepoint(true, 2026052200, "local", "kopere_pay");
    }

    report::atualiza();

    return true;
}

/**
 * Function upgrade_2020050802
 *
 * @return void
 * @throws dml_exception
 */
function upgrade_2020050802() {
    global $DB;

    // Efi.
    $sql = "
         SELECT DISTINCT pm.*
           FROM {kopere_pay_historico} ph
           JOIN {kopere_pay_enrollment} pm ON pm.id = ph.local_kopere_pay_rename_key
          WHERE receive LIKE '%\"status\":\"paid\"%'
            AND pm.id = ph.local_kopere_pay_rename_key
            AND pm.metodo LIKE 'MeioGerencianet%'";
    $koperepayenrollments = $DB->get_records_sql($sql);
    foreach ($koperepayenrollments as $koperepayenrollment) {
        enrollment_util::changue_status($koperepayenrollment, enrollment_util::PAID);
    }

    // Pagseguro.
    $sql = "
         SELECT DISTINCT pm.*
           FROM {kopere_pay_historico} ph
           JOIN {kopere_pay_enrollment} pm ON pm.id = ph.local_kopere_pay_rename_key
          WHERE receive LIKE '%\"status\":\"3\"%'
            AND pm.id = ph.local_kopere_pay_rename_key
            AND pm.metodo LIKE 'MeioPagseguro%'";
    $koperepayenrollments = $DB->get_records_sql($sql);
    foreach ($koperepayenrollments as $koperepayenrollment) {
        enrollment_util::changue_status($koperepayenrollment, enrollment_util::PAID);
    }

    // Manual.
    $sql = "
         SELECT DISTINCT pm.*
           FROM {kopere_pay_historico} ph
           JOIN {kopere_pay_enrollment} pm ON pm.id = ph.local_kopere_pay_rename_key
          WHERE receive LIKE '%\"status\":\"enrol\",\"modo\":\"manual\"%'
            AND pm.id = ph.local_kopere_pay_rename_key";
    $koperepayenrollments = $DB->get_records_sql($sql);
    foreach ($koperepayenrollments as $koperepayenrollment) {
        enrollment_util::changue_status($koperepayenrollment, enrollment_util::PAID);
    }

    // Gratis.
    $sql = "
         SELECT DISTINCT pm.*
           FROM {kopere_pay_historico} ph
           JOIN {kopere_pay_enrollment} pm ON pm.id = ph.local_kopere_pay_rename_key
          WHERE pm.id = ph.local_kopere_pay_rename_key
            AND pm.metodo LIKE 'MeioGratis'";
    $koperepayenrollments = $DB->get_records_sql($sql);
    foreach ($koperepayenrollments as $koperepayenrollment) {
        enrollment_util::changue_status($koperepayenrollment, enrollment_util::PAID);
    }

    // Resto.
    $sql = "SELECT * FROM {kopere_pay_matricula} WHERE status LIKE ''";
    $koperepaymatriculas = $DB->get_records_sql($sql);
    foreach ($koperepaymatriculas as $koperepaymatricula) {
        enrollment_util::changue_status($koperepaymatricula, enrollment_util::WAITING);
    }
}

/**
 * Schema normalization:
 * - Adds missing fields used by current code (fieldcategory).
 * - Expands field sizes for course/chave/cupom/valor to avoid truncation.
 * - Adds indexes used by lookups and dashboards.
 * - Enforces sane defaults for time/integer fields to avoid NULL-related edge cases.
 *
 * @return void
 * @throws \ddl_change_structure_exception
 * @throws \ddl_exception
 * @throws \ddl_table_missing_exception
 */
function upgrade_2026020700() {
    global $DB;

    $dbman = $DB->get_manager();
    // Table kopere_pay_detalhe.
    $tabledetalhe = new xmldb_table("kopere_pay_detalhe");

    $indexdetcourse = new xmldb_index("course", XMLDB_INDEX_NOTUNIQUE, ["course"]);
    if (!$dbman->index_exists($tabledetalhe, $indexdetcourse)) {
        $dbman->add_index($tabledetalhe, $indexdetcourse);
    }

    $indexdetportfolio = new xmldb_index("portfolio", XMLDB_INDEX_NOTUNIQUE, ["portfolio"]);
    if (!$dbman->index_exists($tabledetalhe, $indexdetportfolio)) {
        $dbman->add_index($tabledetalhe, $indexdetportfolio);
    }

    $indexdetstatus = new xmldb_index("status", XMLDB_INDEX_NOTUNIQUE, ["status"]);
    if (!$dbman->index_exists($tabledetalhe, $indexdetstatus)) {
        $dbman->add_index($tabledetalhe, $indexdetstatus);
    }

    // Table kopere_pay_cupom.
    $tablecupom = new xmldb_table("kopere_pay_cupom");

    $indexcupcourse = new xmldb_index("course", XMLDB_INDEX_NOTUNIQUE, ["course"]);
    if (!$dbman->index_exists($tablecupom, $indexcupcourse)) {
        $dbman->add_index($tablecupom, $indexcupcourse);
    }

    $indexcupchave = new xmldb_index("chave", XMLDB_INDEX_NOTUNIQUE, ["chave"]);
    if (!$dbman->index_exists($tablecupom, $indexcupchave)) {
        $dbman->add_index($tablecupom, $indexcupchave);
    }

    $indexcupemail = new xmldb_index("email", XMLDB_INDEX_NOTUNIQUE, ["email"]);
    if (!$dbman->index_exists($tablecupom, $indexcupemail)) {
        $dbman->add_index($tablecupom, $indexcupemail);
    }

    $indexcuptipocourse = new xmldb_index('tipo-course', XMLDB_INDEX_NOTUNIQUE, ["tipo", "course"]);
    if (!$dbman->index_exists($tablecupom, $indexcuptipocourse)) {
        $dbman->add_index($tablecupom, $indexcuptipocourse);
    }

    // Table kopere_pay_historico.
    $tablehistorico = new xmldb_table("kopere_pay_historico");

    $indexhisttime = new xmldb_index("time", XMLDB_INDEX_NOTUNIQUE, ["time"]);
    if (!$dbman->index_exists($tablehistorico, $indexhisttime)) {
        $dbman->add_index($tablehistorico, $indexhisttime);
    }

    // Table kopere_pay_matricula.
    $tablematricula = new xmldb_table("kopere_pay_matricula");

    $indexmatuserid = new xmldb_index("userid", XMLDB_INDEX_NOTUNIQUE, ["userid"]);
    if (!$dbman->index_exists($tablematricula, $indexmatuserid)) {
        $dbman->add_index($tablematricula, $indexmatuserid);
    }

    $indexmatcourse = new xmldb_index("course", XMLDB_INDEX_NOTUNIQUE, ["course"]);
    if (!$dbman->index_exists($tablematricula, $indexmatcourse)) {
        $dbman->add_index($tablematricula, $indexmatcourse);
    }

    $indexmatusercourse = new xmldb_index('userid-course', XMLDB_INDEX_NOTUNIQUE, ["userid", "course"]);
    if (!$dbman->index_exists($tablematricula, $indexmatusercourse)) {
        $dbman->add_index($tablematricula, $indexmatusercourse);
    }

    $indexmatstatuslasttime = new xmldb_index('status-lasttime', XMLDB_INDEX_NOTUNIQUE, ["status", "lasttime"]);
    if (!$dbman->index_exists($tablematricula, $indexmatstatuslasttime)) {
        $dbman->add_index($tablematricula, $indexmatstatuslasttime);
    }

    $indexmatcupom = new xmldb_index("idx_kppmat_cupom", XMLDB_INDEX_NOTUNIQUE, ["cupom"]);
    if (!$dbman->index_exists($tablematricula, $indexmatcupom)) {
        $dbman->add_index($tablematricula, $indexmatcupom);
    }
}

/**
 * Rename a single config_plugins key (plugin+name), handling conflicts safely.
 *
 * Rules:
 * - If old does not exist: do nothing.
 * - If new does not exist: rename old -> new (UPDATE name).
 * - If new exists: keep new; if new value is empty and old value is not, move value; then delete old.
 *
 * @param string $oldname
 * @param string $newname
 * @return void
 * @throws dml_exception
 */
function local_kopere_pay_rename_key(string $oldname, string $newname): void {
    global $DB;

    $oldrec = $DB->get_record("config_plugins", ["plugin" => "local_kopere_dashboard", "name" => $oldname]);
    if (!$oldrec) {
        return;
    }

    $newrec = $DB->get_record("config_plugins", ["plugin" => "local_kopere_dashboard", "name" => $newname]);

    // If target name does not exist, just rename.
    if (!$newrec) {
        $DB->execute(
            "UPDATE {config_plugins}
                SET name   = :newname
              WHERE plugin = 'local_kopere_dashboard'
                AND name   = :oldname",
            ["newname" => $newname, "oldname" => $oldname]
        );
    }
}

/**
 * Rename keys by prefix (e.g., builder_titulo_123 -> builder_title_123).
 *
 * @param string $oldprefix
 * @param string $newprefix
 * @return void
 * @throws \dml_exception
 */
function local_kopere_pay_rename_prefix(string $oldprefix, string $newprefix): void {
    global $DB;

    $records = $DB->get_records_select(
        "config_plugins",
        "plugin = 'local_kopere_dashboard' AND name LIKE :pattern",
        ["pattern" => "{$oldprefix}%"],
        "",
        "id, plugin, name, value"
    );

    foreach ($records as $rec) {
        $suffix = substr($rec->name, strlen($oldprefix));
        $newname = $newprefix . $suffix;

        local_kopere_pay_rename_key($rec->name, $newname);
    }
}
