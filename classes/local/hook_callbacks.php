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

namespace tiny_c4l\local;

use core\hook\output\before_http_headers;

/**
 * Class containing the hook callbacks for tiny_c4l.
 *
 * @package    tiny_c4l
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Hook callback function for the before_http_headers hook.
     *
     * Used to add our custom stylesheet and js to the DOM.
     *
     * @param before_http_headers $beforehttpheadershook
     */
    public static function add_c4l_data_to_dom(\core\hook\output\before_http_headers $beforehttpheadershook): void {
        global $PAGE;

        // Parameter to disable css delivery.
        if (optional_param('tiny_c4l_disable', false, PARAM_BOOL)) {
            return;
        }
        // Don't run during initial install.
        if (during_initial_install()) {
            return;
        }
        // Only run if plugin is enabled.
        $pluginmanager = \core_plugin_manager::instance();
        $plugins = $pluginmanager->get_enabled_plugins('tiny');
        if (!in_array('c4l', $plugins)) {
            return;
        }

        // Don't run if we are in the management page. This should help to remove invalid css/js.
        if ($beforehttpheadershook->renderer->get_page()->has_set_url() &&
            $beforehttpheadershook->renderer->get_page()->url->get_path() == '/lib/editor/tiny/plugins/c4l/management.php') {
            return;
        }

        // Add body class to hide elements not meant for students.
        if (!has_capability('tiny/c4l:manage', \context_system::instance())) {
            $PAGE->add_body_class('tiny_c4l_h4s');
        }

        $cache = \cache::make('tiny_c4l', utils::TINY_C4L_CACHE_AREA);
        $rev = $cache->get(utils::TINY_C4L_CSS_CACHE_REV);
        // var_dump($rev);
        if (!$rev) {
            $rev = utils::rebuild_css_cache();
        }
        $pluginfileurl = \moodle_url::make_pluginfile_url(
            SYSCONTEXTID,
            'tiny_c4l',
            'css',
            null,
            '/',
            'tiny_c4l_styles.css?rev=' . $rev
        );
        $beforehttpheadershook->renderer->get_page()->requires->css($pluginfileurl);

        $revjs = $cache->get(utils::TINY_C4L_JS_CACHE_REV);
        if (!$revjs) {
            $revjs = utils::rebuild_js_cache();
        }
        $pluginfileurljs = \moodle_url::make_pluginfile_url(
            SYSCONTEXTID,
            'tiny_c4l',
            'js',
            null,
            '/',
            'tiny_c4l_scripts.js?rev=' . $revjs
        );
        $beforehttpheadershook->renderer->get_page()->requires->js($pluginfileurljs);
    }
}
