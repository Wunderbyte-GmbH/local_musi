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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_musi
 * @category    upgrade
 * @copyright   2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_musi upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_musi_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2022040300) {
        // Define table local_musi_sports to be created.
        $table = new xmldb_table('local_musi_sports');

        // Adding fields to table local_musi_sports.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sportscategoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('descriptionformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_musi_sports.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_musi_sports.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table local_musi_sportscategory to be created.
        $table = new xmldb_table('local_musi_sportscategories');

        // Adding fields to table local_musi_sportscategory.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('descriptionformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_musi_sportscategory.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_musi_sportscategory.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Musi savepoint reached.
        upgrade_plugin_savepoint(true, 2022040300, 'local', 'musi');
    }

    if ($oldversion < 2022080400) {
        // Define table local_musi_botags to be created.
        $table = new xmldb_table('local_musi_botags');

        // Adding fields to table local_musi_botags.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('botag', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table local_musi_botags.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_musi_botags.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Musi savepoint reached.
        upgrade_plugin_savepoint(true, 2022080400, 'local', 'musi');
    }

    if ($oldversion < 2023041700) {
        // Define table local_musi_globals to be created.
        $table = new xmldb_table('local_musi_globals');

        // Adding fields to table local_musi_globals.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fieldname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fieldvalue_num', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, '0');
        $table->add_field('fieldvalue_char', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table local_musi_globals.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_musi_globals.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Musi savepoint reached.
        upgrade_plugin_savepoint(true, 2023041700, 'local', 'musi');
    }

    if ($oldversion < 2023091401) {
        // We need to run a correction on all the booking option.

        if (class_exists('local_entities\entitiesrelation_handler')) {
            global $DB;

            // Get the record with all the entities relations and their parents.
            $sql = "SELECT ler.*, e.name, (
                                        SELECT pe.name
                                        FROM {local_entities} pe
                                        WHERE pe.id=e.parentid) as parentname

                                FROM {local_entities_relations} ler
                                JOIN {local_entities} e
                                ON e.id=ler.entityid
                                WHERE component='mod_booking'
                                AND area='option'";

            $records = $DB->get_records_sql($sql);

            foreach ($records as $record) {
                $data = (object)[
                    'id' => $record->instanceid,
                    'location' => $record->parentname ?? $record->name,
                ];

                $DB->update_record('booking_options', $data, true);
            }
        }

        // Musi savepoint reached.
        upgrade_plugin_savepoint(true, 2023091401, 'local', 'musi');
    }

    if ($oldversion < 2023092900) {
        // Define table local_musi_substitutions to be created.
        $table = new xmldb_table('local_musi_substitutions');

        // Adding fields to table local_musi_substitutions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sport', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('teachers', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_musi_substitutions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_musi_substitutions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Musi savepoint reached.
        upgrade_plugin_savepoint(true, 2023092900, 'local', 'musi');
    }

    if ($oldversion < 2023120400) {
        // Define table local_musi_sap to be created.
        $table = new xmldb_table('local_musi_sap');

        // Adding fields to table local_musi_sap.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('identifier', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('paymentid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('pu_openorderid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sap_line', XMLDB_TYPE_CHAR, '1023', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('error', XMLDB_TYPE_CHAR, '1023', null, null, null, null);
        $table->add_field('info', XMLDB_TYPE_CHAR, '1023', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_musi_sap.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_musi_sap.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Musi savepoint reached.
        upgrade_plugin_savepoint(true, 2023120400, 'local', 'musi');
    }

    return true;
}
