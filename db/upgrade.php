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
 * Upgrade steps for Components for Learning (C4L)
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    tiny_c4l
 * @category   upgrade
 * @copyright  2024 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_tiny_c4l_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024110500) {

        // Define table tiny_c4l_compcat to be created.
        $table = new xmldb_table('tiny_c4l_compcat');

        // Adding fields to table tiny_c4l_compcat.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('displayorder', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('css', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tiny_c4l_compcat.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('name', XMLDB_KEY_UNIQUE, ['name']);

        // Conditionally launch create table for tiny_c4l_compcat.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        // Define table tiny_c4l_component to be created.
        $table = new xmldb_table('tiny_c4l_component');

        // Adding fields to table tiny_c4l_component.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('compcat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('code', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('text', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('variants', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('displayorder', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('css', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('js', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('iconurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tiny_c4l_component.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('compcat', XMLDB_KEY_FOREIGN, ['compcat'], 'tiny_c4l_compcat', ['id']);
        $table->add_key('name', XMLDB_KEY_UNIQUE, ['name']);

        // Conditionally launch create table for tiny_c4l_component.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table tiny_c4l_flavor to be created.
        $table = new xmldb_table('tiny_c4l_flavor');

        // Adding fields to table tiny_c4l_flavor.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('css', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tiny_c4l_flavor.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('name', XMLDB_KEY_UNIQUE, ['name']);

        // Conditionally launch create table for tiny_c4l_flavor.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table tiny_c4l_comp_flavor to be created.
        $table = new xmldb_table('tiny_c4l_comp_flavor');

        // Adding fields to table tiny_c4l_comp_flavor.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('componentname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('flavorname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('iconurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null);

        // Adding keys to table tiny_c4l_comp_flavor.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('tinyc4lcompflav_comp_fk', XMLDB_KEY_FOREIGN, ['componentname'], 'tiny_c4l_component', ['name']);
        $table->add_key('tinyc4lcompflav_flav_fk', XMLDB_KEY_FOREIGN, ['flavorname'], 'tiny_c4l_flavor', ['name']);

        // Conditionally launch create table for tiny_c4l_comp_flavor.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table tiny_c4l_variant to be created.
        $table = new xmldb_table('tiny_c4l_variant');

        // Adding fields to table tiny_c4l_variant.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('css', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('iconurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tiny_c4l_variant.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('name', XMLDB_KEY_UNIQUE, ['name']);

        // Conditionally launch create table for tiny_c4l_variant.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // C4l savepoint reached.
        upgrade_plugin_savepoint(true, 2024110500, 'tiny', 'c4l');
    }

    return true;
}
