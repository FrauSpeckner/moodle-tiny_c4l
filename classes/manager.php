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

namespace tiny_c4l;

use memory_xml_output;
use moodle_exception;
use stored_file;
use xml_writer;
use tiny_c4l\local\utils;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/xml/xml_writer.class.php');
require_once($CFG->dirroot . '/backup/util/xml/output/xml_output.class.php');
require_once($CFG->dirroot . '/backup/util/xml/output/memory_xml_output.class.php');

/**
 * Class manager
 *
 * @package    tiny_c4l
 * @copyright  2024 Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int $contextid */
    protected int $contextid = 1;
    /** @var array All tables to export data from. **/
    protected static $tables = [
        'compcat' => 'tiny_c4l_compcat',
        'component' => 'tiny_c4l_component',
        'flavor' => 'tiny_c4l_flavor',
        'variant' => 'tiny_c4l_variant',
        'compflavor' => 'tiny_c4l_comp_flavor',
    ];

    /** @var string Item. **/
    protected static $item = 'row';

    /**
     * Constructor.
     *
     * @param int $contextid
     */
    public function __construct(int $contextid = $this->contextid) {
        $this->contextid = $contextid;
    }

    /**
     * Export.
     *
     * @param int $compcatid
     * @return stored_file
     * @throws moodle_exception
     */
    public function export($compcatid = 0): stored_file {
        global $DB;
        $fs = get_file_storage();
        $fp = get_file_packer('application/zip');
        $compcats = $DB->get_records('tiny_c4l_compcat');
        // It is necessary to get the files for each compcat separately to avoid mixing up files from
        // different categories.
        foreach ($compcats as $compcat) {
            $files = $fs->get_area_files($this->contextid, 'tiny_c4l', 'images', $compcat->id);
            foreach ($files as $file) {
                $exportfiles[$compcat->name . '/' . $file->get_filepath() . $file->get_filename()] = $file;
            }
        }
        $filerecord = [
            'contextid' => $this->contextid,
            'component' => 'tiny_c4l',
            'filearea' => 'export',
            'itemid' => time(),
            'filepath' => '/',
            'filename' => 'tiny_c4l_export.xml',
        ];
        $exportxmlfile = $fs->create_file_from_string($filerecord, $this->exportxml());
        $exportfiles['tiny_c4l_export.xml'] = $exportxmlfile;
        $filename = 'tiny_c4l_export_' . time() . '.zip';
        $exportfile = $fp->archive_to_storage($exportfiles, $this->contextid, 'tiny_c4l', 'export', 0, '/', $filename);
        if (!$exportfile) {
            throw new moodle_exception(get_string('error_export', 'tiny_c4l'));
        }
        return $exportfile;
    }

    /**
     * Export XML.
     *
     * @return string
     */
    public function exportxml(): string {
        global $DB;

        // Start.
        $xmloutput = new memory_xml_output();
        $xmlwriter = new xml_writer($xmloutput);
        $xmlwriter->start();
        $xmlwriter->begin_tag('c4l');

        // Tiny_c4l_compcat.
        foreach (static::$tables as $shortname => $table) {
            // Get columns.
            $columns = $DB->get_columns($table);

            // Get data.
            $data = $DB->get_records($table);

            $xmlwriter->begin_tag($table);
            foreach ($data as $value) {
                $xmlwriter->begin_tag(static::$item);
                foreach ($columns as $column) {
                    $name = $column->name;
                    $xmlwriter->full_tag($name, $value->$name ?? '');
                }
                $xmlwriter->end_tag(static::$item);
            }
            $xmlwriter->end_tag($table);
        }

        // End.
        $xmlwriter->end_tag('c4l');
        $xmlwriter->stop();
        $xmlstr = $xmloutput->get_allcontents();

        // This is just here for compatibility reasons.
        $xmlstr = utils::replace_pluginfile_urls($xmlstr);

        return $xmlstr;
    }

    /**
     * Import files.
     *
     * @param array $files
     * @param int $categoryid
     * @param string $categoryname
     * @throws moodle_exception
     */
    public function importfiles($files, $categoryid, $categoryname = '') {
        $fs = get_file_storage();
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $newfilepath = ($categoryname ? str_replace('/' . $categoryname, '', $file->get_filepath()) : $file->get_filepath());
            if ($oldfile = $fs->get_file($this->contextid, 'tiny_c4l', 'images', $categoryid, $newfilepath, $file->get_filename())) {
                if ($oldfile->get_contenthash() != $file->get_contenthash()) {
                    $oldfile->replace_file_with($file);
                }
            } else {
                $newfile = $fs->create_file_from_storedfile([
                    'contextid' => $this->contextid,
                    'component' => 'tiny_c4l',
                    'filearea' => 'images',
                    'itemid' => $categoryid,
                    'filepath' => $newfilepath,
                    'filename' => $file->get_filename(),
                ], $file);
                if (!$newfile) {
                    throw new moodle_exception(get_string('error_fileimport', 'tiny_c4l', $newfilepath . $file->get_filename()));
                }
            }
        }
    }

    public function importxml(string $xmlcontent): bool {
        try {
            $xml = simplexml_load_string($xmlcontent);
        } catch (\Exception $exception) {
            $xml = false;
        }
        if (!$xml) {
            return false;
        }

        // Create mapping array for tiny_c4l_component table.
        $componentmap = [];

        foreach (self::$tables as $table) {
            if (!isset($xml->$table)) {
                throw new moodle_exception(get_string('error_import_missing_table', 'tiny_c4l', $table));
            }
        }

        $data = [];

        // Make data usable for further processing.
        foreach ($xml as $table => $rows) {
            foreach ($rows as $row) {
                $obj = new \stdClass();
                foreach ($row as $column => $value) {
                    $obj->$column = (string) $value;
                }
                $data[$table][] = $obj;
            }
        }

        // First process all component categories. We need the category ids for the components.
        foreach ($data['tiny_c4l_compcat'] as $compcat) {
            // Save new id for mapping.
            $componentmap[$compcat->id] = self::import_category($compcat);
        }

        foreach ($data['tiny_c4l_component'] as $component) {
            self::import_component($component, $componentmap);
        }

        foreach ($data['tiny_c4l_flavor'] as $flavor) {
            self::import_flavor($flavor, $componentmap);
        }

        foreach ($data['tiny_c4l_variant'] as $variant) {
            self::import_variant($variant, $componentmap);
        }

        foreach ($data['tiny_c4l_comp_flavor'] as $componentflavor) {
            self::import_component_flavor($componentflavor, $componentmap);
        }

        return true;
    }

    /**
     * Import a component category.
     *
     * @param array|object $record
     * @return int id of the imported category
     */
    public static function import_category(array|object $record): int {
        global $DB;
        $record = (array) $record;
        $oldid = $record['id'];
        $current = $DB->get_record('tiny_c4l_compcat', ['name' => $record['name']]);
        if ($current) {
            $record['id'] = $current->id;
            $DB->update_record('tiny_c4l_compcat', $record);
        } else {
            $record['id'] = $DB->insert_record('tiny_c4l_compcat', $record);
        }
        // Update pluginfile tags in css if the id has changed.
        if ($oldid != $record['id']) {
            $record['css'] = self::update_pluginfile_tags($oldid, $record['id'], $record['css']);
            $DB->update_record('tiny_c4l_compcat', $record);
        }
        return $record['id'];
    }

    /**
     * Import a component.
     *
     * @param array|object $record
     * @param array $componentmap
     * @return int id of the imported component
     */
    public static function import_component(array|object $record, array $componentmap): int {
        global $DB;
        $record = (array) $record;
        if (isset($componentmap[$record['compcat']])) {
            $record['compcat'] = $componentmap[$record['compcat']];
        }
        foreach ($componentmap as $oldid => $newid) {
            $record['css'] = self::update_pluginfile_tags($oldid, $newid, $record['css'] ?? '');
            $record['code'] = self::update_pluginfile_tags($oldid, $newid, $record['code'] ?? '');
            $record['js'] = self::update_pluginfile_tags($oldid, $newid, $record['js'] ?? '');
            $record['iconurl'] = self::update_pluginfile_tags($oldid, $newid, $record['iconurl'] ?? '');
        }
        $current = $DB->get_record('tiny_c4l_component', ['name' => $record['name']]);
        if ($current) {
            $record['id'] = $current->id;
            $DB->update_record('tiny_c4l_component', $record);
        } else {
            try {
                $record['id'] = $DB->insert_record('tiny_c4l_component', $record);
            } catch (\Exception $e) {
                throw new moodle_exception(get_string('error_import_component', 'tiny_c4l', $record['name']));
            }
        }

        if ($record['flavors'] != '') {
            foreach (explode(',', $record['flavors']) as $flavor) {
                if($flavor == '') {
                    continue;
                }
                $flavorrecord = [
                    'componentname' => $record['name'],
                    'flavorname' => $flavor,
                ];
                $DB->insert_record('tiny_c4l_comp_flavor', $flavorrecord);
            }
        }

        return $record['id'];
    }

    /**
     * Import a flavor.
     *
     * @param array|object $record
     * @param array $componentmap
     * @return int id of the imported flavor
     */
    public static function import_flavor(array|object $record, array $componentmap): int {
        global $DB;
        $record = (array) $record;
        $current = $DB->get_record('tiny_c4l_flavor', ['name' => $record['name']]);
        foreach ($componentmap as $oldid => $newid) {
            $record['css'] = self::update_pluginfile_tags($oldid, $newid, $record['css']);
            $record['content'] = self::update_pluginfile_tags($oldid, $newid, $record['content']);
        }
        if ($current) {
            $record['id'] = $current->id;
            $DB->update_record('tiny_c4l_flavor', $record);
        } else {
            $record['id'] = $DB->insert_record('tiny_c4l_flavor', $record);
        }
        return $record['id'];
    }

    /**
     * Import a variant.
     *
     * @param array|object $record
     * @param array $componentmap
     * @return int id of the imported variant
     */
    public static function import_variant(array|object $record, array $componentmap): int {
        global $DB;
        $record = (array) $record;
        $current = $DB->get_record('tiny_c4l_variant', ['name' => $record['name']]);
        foreach ($componentmap as $oldid => $newid) {
            $record['css'] = self::update_pluginfile_tags($oldid, $newid, $record['css']);
            $record['content'] = self::update_pluginfile_tags($oldid, $newid, $record['content']);
            $record['iconurl'] = self::update_pluginfile_tags($oldid, $newid, $record['iconurl'] ?? '');
        }
        if ($current) {
            $record['id'] = $current->id;
            $DB->update_record('tiny_c4l_variant', $record);
        } else {
            $record['id'] = $DB->insert_record('tiny_c4l_variant', $record);
        }
        return $record['id'];
    }

    /**
     * Import a relation between component and flavor.
     *
     * @param array|object $record
     * @param array $componentmap
     * @return int id of the imported relation
     */
    public static function import_component_flavor(array|object $record, array $componentmap): int {
        global $DB;
        $record = (array) $record;
        $current = $DB->get_record('tiny_c4l_comp_flavor', ['componentname' => $record['componentname'], 'flavorname' => $record['flavorname']]);
        foreach ($componentmap as $oldid => $newid) {
            $record['iconurl'] = self::update_pluginfile_tags($oldid, $newid, $record['iconurl'] ?? '');
        }
        if ($current) {
            $record['id'] = $current->id;
            $DB->update_record('tiny_c4l_comp_flavor', $record);
        } else {
            $record['id'] = $DB->insert_record('tiny_c4l_comp_flavor', $record);
        }
        return $record['id'];
    }

    /**
     * Update the pluginfile tags in the given subject.
     *
     * @param integer $oldid
     * @param integer $newid
     * @param string $subject
     * @return string
     */
    public static function update_pluginfile_tags(int $oldid, int $newid, string $subject): string {
        $oldstring = '@@PLUGINFILE@@/1/tiny_c4l/images/' . $oldid . '/';
        $newstring = '@@PLUGINFILE@@/1/tiny_c4l/images/' . $newid . '/';
        return str_replace($oldstring, $newstring, $subject);
    }

    /**
     * Delete a category.
     *
     * @param int $id
     */
    public static function delete_compcat(int $id): void {
        global $DB;
        $fs = get_file_storage();
        $fs->delete_area_files($this->contextid, 'tiny_c4l', 'images', $id);
        $DB->delete_records('tiny_c4l_compcat', ['id' => $id]);
        $DB->delete_records('tiny_c4l_component', ['compcat' => $id]);
    }

    /**
     * Import data from a zip file.
     *
     * @param stored_file|string $zip
     * @param int $draftitemid
     */
    public function import(stored_file|string $zip, $draftitemid = 0) {
        global $DB;

        if (file_exists($zip)) {
            $fs = get_file_storage();
            $fp = get_file_packer('application/zip');
            $fp->extract_to_storage($zip, $this->contextid, 'tiny_c4l', 'import', $draftitemid, '/');
            $manager = new manager();
            $xmlfile = $fs->get_file($this->contextid, 'tiny_c4l', 'import', $draftitemid, '/', 'tiny_c4l_export.xml');
            $xmlcontent = $xmlfile->get_content();
            $manager->importxml($xmlcontent);
            $categories = $DB->get_records('tiny_c4l_compcat');
            foreach ($categories as $category) {
                $categoryfiles = $fs->get_directory_files($this->contextid, 'tiny_c4l', 'import', $draftitemid, '/' . $category->name . '/', true, false);
                $manager->importfiles($categoryfiles, $category->id, $category->name);
            }
            $fs->delete_area_files($this->contextid, 'tiny_c4l', 'import', $draftitemid);

            \tiny_c4l\local\utils::purge_css_cache();
        }
    }
}
