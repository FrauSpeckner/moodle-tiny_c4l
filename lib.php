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
 * Tiny C4L library functions.
 *
 * @package   tiny_c4l
 * @copyright 2023 Marc Català <reskit@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tiny_c4l\local\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Return a list of all the user preferences used by tiny_c4l.
 *
 * @return array
 */
function tiny_c4l_user_preferences() {
    $preferences = [];

    $preferences['c4l_category'] = [
        'type' => PARAM_INT,
        'null' => NULL_NOT_ALLOWED,
        'default' => 0,
    ];

    $preferences['c4l_category_flavors'] = [
        'type' => PARAM_RAW,
        'null' => NULL_ALLOWED,
    ];

    $preferences['c4l_component_variants'] = [
        'type' => PARAM_RAW,
        'null' => NULL_ALLOWED,
    ];

    return $preferences;
}

/**
 * Serve the requested file for the tiny_c4l plugin.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function tiny_c4l_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options
): bool {
    // Special case, sending a question bank export.
    if ($filearea === 'export') {
        require_capability('tiny/c4l:manage', \core\context::instance_by_id($context));

        $manager = new \tiny_c4l\manager();
        send_stored_file($manager->export());
    } else if ($filearea === 'images') {
        $fs = get_file_storage();
        $fullpath = '/1/tiny_c4l/images/' . implode('/', $args);
        if ((!$file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 0, 0, $forcedownload, $options);
    } else if ($filearea === 'js') {
        $js = utils::get_js_from_cache();
        if (!$js) {
            return send_file_not_found();
        }
        send_file($js, 'tiny_c4l_scripts.js', null, 0, true, false, 'text/javascript');
        return true;
    }
    $css = utils::get_css_from_cache();
    if (!$css) {
        return send_file_not_found();
    }
    send_file($css, 'tiny_c4l_styles.css', null, 0, true, false, 'text/css');
    return true;
}
