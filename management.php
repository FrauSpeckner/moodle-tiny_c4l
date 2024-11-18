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
 * TODO describe file management
 *
 * @package    tiny_c4l
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../../config.php');

require_login();

$url = new moodle_url('/lib/editor/tiny/plugins/c4l/management.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('menuitem_c4l', 'tiny_c4l') . ' ' . get_string('management', 'tiny_c4l'));
$compcatactive = optional_param('compcat', '', PARAM_ALPHANUMEXT);

require_capability('tiny/c4l:manage', context_system::instance());

echo $OUTPUT->header();

// Get all c4l components.
$dbcompcats = $DB->get_records('tiny_c4l_compcat');
$dbflavor = $DB->get_records('tiny_c4l_flavor');
$dbcompflavor = $DB->get_records('tiny_c4l_comp_flavor');
$dbcomponent = $DB->get_records('tiny_c4l_component');
$dbvariant = $DB->get_records('tiny_c4l_variant');

// Use array_values so mustache can parse it.
$compcats = array_values($dbcompcats);
$flavor = array_values($dbflavor);
$component = array_values($dbcomponent);
$variant = array_values($dbvariant);

// Build preview images for management.
$flavorexamples = [];
foreach ($component as $key => $value) {
    // Build a additional array with flavors for mustache.
    $flavors = [];
    foreach ($dbcompflavor as $val) {
        if ($val->componentname == $value->name) {
            array_push($flavors, $val->flavorname);
        }
    }
    $component[$key]->flavorsarr = $flavors;
    $component[$key]->exampleflavorsarr = $component[$key]->flavorsarr;
    if (count($component[$key]->flavorsarr) > 2) {
        // Keep only the first two entries, and add ...
        $component[$key]->exampleflavorsarr = array_slice($component[$key]->flavorsarr, 0, 2);
        array_push($component[$key]->exampleflavorsarr, 'more');
    }
    // Save an example to show on a flavor.
    foreach ($component[$key]->flavorsarr as $flav) {
        $flavorexamples[$flav] = $component[$key]->name;
    }
    // Add the compcat name as js selector, use database array.
    $component[$key]->compcatname = $dbcompcats[$value->compcat]->name;
}

// Use flavorexamples to add an example image to flavors.
foreach ($flavor as $key => $value) {
    if (isset($flavorexamples[$value->name])) {
        $flavor[$key]->example = $flavorexamples[$value->name];
    }
}

// Use empty array to create an add item.
$addentry = [];
array_push($compcats, $addentry);
array_push($flavor, $addentry);
array_push($component, $addentry);
array_push($variant, $addentry);

// Add exportlink.
$exportlink = \moodle_url::make_pluginfile_url(SYSCONTEXTID, 'tiny_c4l', 'export', null, '/', 'tiny_c4l_export.xml')->out();

$params = new \stdClass;
$params->compcatactive = $compcatactive;
$PAGE->requires->js_call_amd('tiny_c4l/management', 'init', [$params]);
echo($OUTPUT->render_from_template('tiny_c4l/management', [
    'compcats' => $compcats,
    'flavor' => $flavor,
    'component' => $component,
    'variant' => $variant,
    'exportlink' => $exportlink,
]));
echo $OUTPUT->footer();
