<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for qtype_codejudge.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The previous version.
 * @return bool
 */
function xmldb_qtype_codejudge_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061300) {
        $table = new xmldb_table('qtype_codejudge_grading');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('questionattemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('questionattemptstepid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('language', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'python');
            $table->add_field('code', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('rubric', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
            $table->add_field('score', XMLDB_TYPE_NUMBER, '5,2', null, null, null, null);
            $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('rawresponse', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_questionid', XMLDB_INDEX_NOTUNIQUE, ['questionid']);
            $table->add_index('idx_questionattemptid', XMLDB_INDEX_NOTUNIQUE, ['questionattemptid']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('qtype_codejudge_options');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('language', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'python');
            $table->add_field('rubric', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('startercode', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('editorheight', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '420');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'question', ['id']);
            $table->add_index('uq_questionid', XMLDB_INDEX_UNIQUE, ['questionid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061300, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061301) {
        // No database changes. This release fixes the edit form filename expected by Moodle.
        upgrade_plugin_savepoint(true, 2026061301, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061302) {
        // No database changes. This release aligns overridden method signatures with Moodle core.
        upgrade_plugin_savepoint(true, 2026061302, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061303) {
        // No database changes. This release removes an unsafe form API call.
        upgrade_plugin_savepoint(true, 2026061303, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061304) {
        // No database changes. This release renders the question text in preview/attempt views.
        upgrade_plugin_savepoint(true, 2026061304, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061305) {
        // No database changes. This release hides the grading rubric from students.
        upgrade_plugin_savepoint(true, 2026061305, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061306) {
        // No database changes. This release adds Portuguese fallback language strings.
        upgrade_plugin_savepoint(true, 2026061306, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061307) {
        // No database changes. This release stops the editor from intercepting quiz navigation submits.
        upgrade_plugin_savepoint(true, 2026061307, 'qtype', 'codejudge');
    }

    if ($oldversion < 2026061308) {
        // No database changes. This release adds a backend Quiz attempt submission observer.
        upgrade_plugin_savepoint(true, 2026061308, 'qtype', 'codejudge');
    }

    return true;
}
