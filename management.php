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

use tiny_c4l\local\utils;

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

// Check if there items without valid component.
// Also already begin formatting as selectors.
$sqlflavor = "SELECT CONCAT('.', f.name, '.flavor') FROM {tiny_c4l_flavor} f
              LEFT JOIN {tiny_c4l_comp_flavor} cf ON f.name = cf.flavorname
              WHERE cf.id IS NULL";
$loneflavors = $DB->get_fieldset_sql($sqlflavor);
$sqlvariant = "SELECT CONCAT('.', v.name, '.variant') FROM {tiny_c4l_variant} v
                LEFT JOIN {tiny_c4l_component} c ON c.variants IS NOT NULL
                AND TRIM(c.variants) != ''
                AND (
                    c.variants = v.name
                    OR c.variants LIKE CONCAT('%,', v.name, ',%')
                    OR c.variants LIKE CONCAT(v.name, ',%')
                    OR c.variants LIKE CONCAT('%,', v.name)
                )
                WHERE c.id IS NULL";
$lonevariants = $DB->get_fieldset_sql($sqlvariant);
$sqlcomponent = "SELECT CONCAT('.', c.name, '.component') FROM {tiny_c4l_component} c
              LEFT JOIN {tiny_c4l_compcat} cc ON c.compcat = cc.id
              WHERE cc.id IS NULL";
$lonecomponents = $DB->get_fieldset_sql($sqlcomponent);
// Add a compcat to make these accessible.
if ($loneflavors || $lonevariants || $lonecomponents ) {
    $foundcompcat = [
        'id' => 'false',
        'name' => 'found-items',
        'displayname' => get_string('foundcompcat', 'tiny_c4l'),
        'loneflavors' => implode(',', $loneflavors),
        'lonevariants' => implode(',', $lonevariants),
        'lonecomponents' => implode(',', $lonecomponents),
    ];
    array_unshift($compcats, $foundcompcat);
}

// Add matching compcats to variants.
// foreach ($variant as $key => $value) {
//     $fcompcats = [];
//     // Select the components.
//     $sql = "SELECT compcat FROM {tiny_c4l_component}
//             WHERE variants = ?
//             OR variants LIKE CONCAT('%,', ?, ',%')
//             OR variants LIKE CONCAT(?, ',%')
//             OR variants LIKE CONCAT('%,', ?)";
//     $param = [$value->name, $value->name, $value->name, $value->name];
//     $fcomps = $DB->get_fieldset_sql($sql, $param);
//     if (!empty($fcomps)) {
//         $fcomps = array_unique($fcomps);
//         // Extract names and write as classes.
//         [$insql, $inparams] = $DB->get_in_or_equal($fcomps);
//         $fcompcats = $DB->get_fieldset_select('tiny_c4l_compcat', 'name', "id $insql", $inparams);
//         $variant[$key]->compcatmatches = implode(' ', $fcompcats);
//     }
// }
// Build component preview images for management, also add compcat.
foreach ($component as $key => $value) {
    // Add corresponding flavors.
    $flavorsarr = [];
    $flavorexamplesarr = [];
    foreach ($dbcompflavor as $val) {
        if ($val->componentname == $value->name) {
            array_push($flavorsarr, $val->flavorname);
            if (!empty($val->iconurl)) {
                array_push($flavorexamplesarr, utils::replace_pluginfile_urls($val->iconurl, true));
            }
        }
    }
    $component[$key]->flavorsarr = $flavorsarr;
    $component[$key]->flavorexamplesarr = $flavorexamplesarr;
    // Keep only the first two entries
    if (count($component[$key]->flavorexamplesarr) > 2) {
        $component[$key]->flavorexamplesarr = array_slice($component[$key]->flavorexamplesarr, 0, 2);
    }
    // Add the compcat name as js selector.
    $component[$key]->compcatname = $dbcompcats[$value->compcat]->name;
}

// Add flavor previews.
foreach ($flavor as $key => $value) {
    // Look for an example in comp_flavor.
    foreach ($dbcompflavor as $val) {
        if ($val->flavorname == $value->name) {
            if (!empty($val->iconurl)) {
                $flavor[$key]->example = utils::replace_pluginfile_urls($val->iconurl, true);
                continue;
            }
        }
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
