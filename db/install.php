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
 * Install script for Components for Learning (C4L)
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    tiny_c4l
 * @copyright  2024 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tiny_c4l\manager;

/**
 * Executed on installation of Components for Learning (C4L)
 *
 * @return bool
 */
function xmldb_tiny_c4l_install() {
    global $DB;
    $basezip = __DIR__ . 'base.zip';
    if (file_exists($basezip)) {
        $fs = get_file_storage();
        $fp = get_file_packer('application/zip');
        $fp->extract_to_storage($basezip, SYSCONTEXTID, 'tiny_c4l', 'import', 0, '/');
        $manager = new manager();
        $xmlfile = $fs->get_file(SYSCONTEXTID, 'tiny_c4l', 'import', 0, '/', 'tiny_c4l_export.xml');
        $xmlcontent = $xmlfile->get_content();
        $manager->importxml($xmlcontent);
        $categories = $DB->get_records('tiny_c4l_compcat');
        foreach ($categories as $category) {
            $categoryfiles = $fs->get_directory_files(SYSCONTEXTID, 'tiny_c4l', 'import', 0, '/' . $category->name . '/', true, false);
            $manager->importfiles($categoryfiles, $category->id, $category->name);
        }
        $fs->delete_area_files(SYSCONTEXTID, 'tiny_c4l', 'import', 0);

        \tiny_c4l\local\utils::purge_css_cache();
    }
    return true;
}
