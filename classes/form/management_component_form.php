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

namespace tiny_c4l\form;

/**
 * Class management_component_form
 *
 * @package    tiny_c4l
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class management_component_form extends base_form {
    public function definition() {
        global $DB;

        $compcats = $DB->get_records_menu('tiny_c4l_compcat', null, 'displayname', 'id, displayname');
        $flavors = $DB->get_records_menu('tiny_c4l_flavor', null, 'displayname', 'name, displayname');
        $variants = $DB->get_records_menu('tiny_c4l_variant', null, 'displayname', 'name, displayname');

        $mform =& $this->_form;

        // Set this variable to access correct db table.
        $this->formtype = "component";

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('componentname', 'tiny_c4l'), ['size' => '255']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'componentname', 'tiny_c4l');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('validclassname', 'tiny_c4l'), 'regex', '/^[_a-zA-Z][_a-zA-Z0-9-]*$/', 'client');

        $mform->addElement('text', 'displayname', get_string('displayname', 'tiny_c4l'), ['size' => '255']);
        $mform->setType('displayname', PARAM_TEXT);
        $mform->addHelpButton('displayname', 'displayname', 'tiny_c4l');

        $mform->addElement('select', 'compcat', get_string('compcat', 'tiny_c4l'), $compcats);
        $mform->setType('compcat', PARAM_INT);

        $mform->addElement($this->codemirror_present() ? 'editor' : 'textarea', 'code', get_string('code', 'tiny_c4l'));
        $mform->setType('code', PARAM_RAW);
        $mform->addHelpButton('code', 'code', 'tiny_c4l');

        $mform->addElement('textarea', 'text', get_string('text', 'tiny_c4l'));
        $mform->setType('text', PARAM_TEXT);

        $mform->addElement('autocomplete', 'variants', get_string('variants', 'tiny_c4l'), $variants, ['multiple' => true]);
        $mform->setType('variants', PARAM_TEXT);

        $mform->addElement('autocomplete', 'flavors', get_string('flavors', 'tiny_c4l'), $flavors, ['multiple' => true]);
        $mform->setType('flavors', PARAM_TEXT);

        $mform->addElement('text', 'displayorder', get_string('displayorder', 'tiny_c4l'));
        $mform->setType('displayorder', PARAM_INT);

        $mform->addElement($this->codemirror_present() ? 'editor' : 'textarea', 'css', get_string('css', 'tiny_c4l'));
        $mform->setType('css', PARAM_RAW);

        $mform->addElement($this->codemirror_present() ? 'editor' : 'textarea', 'js', get_string('js', 'tiny_c4l'));
        $mform->setType('js', PARAM_RAW);

        $mform->addElement('url', 'iconurl', get_string('iconurl', 'tiny_c4l'), ['size' => '255']);
        $mform->setType('iconurl', PARAM_URL);

        $mform->addElement('checkbox', 'hideforstudents', get_string('hideforstudents', 'tiny_c4l'));
        $mform->setType('hideforstudents', PARAM_INT);
    }
}
