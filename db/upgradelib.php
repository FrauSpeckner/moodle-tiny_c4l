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
 * Tiny_c4l upgrade related helper functions.
 *
 * @package    tiny_c4l/db
 * @copyright  2024 ISB Bayern, Franziska Hübler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Insert junction table component_variant.
 * @return void
 */
function tiny_c4l_insert_comp_variant(): void {
    global $DB;

    $dbman = $DB->get_manager();

    // Define table tiny_c4l_comp_variant to be created.
    $table = new xmldb_table('tiny_c4l_comp_variant');

    // Adding fields to table tiny_c4l_comp_variant.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('component', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('variant', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

    // Adding keys to table tiny_c4l_comp_variant.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('tinyc4lcompvariant_comp_fk', XMLDB_KEY_FOREIGN, ['component'], 'tiny_c4l_component', ['id']);
    $table->add_key('tinyc4lcompvariant_variant_fk', XMLDB_KEY_FOREIGN, ['variant'], 'tiny_c4l_variant', ['name']);

    // Conditionally launch create table for tiny_c4l_comp_variant.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Fill table tiny_c4l_comp_variant.
    $board = 0;
    $nextnumber = 0;
    $components = $DB->get_recordset('tiny_c4l_component', null, '', 'id, variants');
    foreach ($components as $component) {
        $variants = explode(',', $component->variants);
        foreach ($variants as $variant) {
            if ($variant == '') {
                continue;
            }
            // Insert variant to component.
            $vartocomp = new stdClass();
            $vartocomp->component = $component->id;
            $vartocomp->variant = $variant;
            $DB->insert_record('tiny_c4l_comp_variant', $vartocomp);
        } 
    }
    $components->close();

    // Define field variants to be dropped from table tiny_c4l_component.
    $table = new xmldb_table('tiny_c4l_component');
    $field = new xmldb_field('variants');

    // Conditionally launch drop field variants.
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }
}
