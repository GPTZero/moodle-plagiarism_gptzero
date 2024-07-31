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
 * forms used by the gptzero plagiarism plugin.
 *
 * @package    plagiarism_gptzero
 * @copyright  2024 Tyler Vu <tyler@gptzero.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->dirroot.'/lib/formslib.php');

class plagiarism_setup_form extends moodleform {
    public function definition () {
        $mform =& $this->_form;

        $mform->addElement('html', get_string('gptzeroexplain', 'plagiarism_gptzero'));

        $mform->addElement('advcheckbox', 'gptzero_enabled', get_string('enableplugin', 'plagiarism_gptzero'));
        $mform->setDefault('gptzero_enabled', 0);

        $mform->addElement('textarea', 'gptzero_student_disclosure', get_string('studentdisclosure', 'plagiarism_gptzero'),
                           'wrap="virtual" rows="6" cols="50"');
        $mform->addHelpButton('gptzero_student_disclosure', 'studentdisclosure', 'plagiarism_gptzero');
        $mform->setDefault('gptzero_student_disclosure', get_string('studentdisclosuredefault', 'plagiarism_gptzero'));

        $mods = core_component::get_plugin_list('mod');
        if (array_key_exists('assign', $mods) && plugin_supports('mod', 'assign', FEATURE_PLAGIARISM)) {
            $mform->addElement('checkbox', 'gptzero_enable_mod_assign', get_string('gptzero_enableplugin', 'plagiarism_gptzero', 'assign'));
        }

        $mform->addElement('passwordunmask', 'gptzero_apikey', get_string('apikey', 'plagiarism_gptzero'));
        $mform->setType('gptzero_apikey', PARAM_TEXT);
        $mform->addRule('gptzero_apikey', get_string('required'), 'required');

        $mform->addElement('html', '<div class="form-group row fitem"><div class="col-md-12 col-form-label">'.get_string("apikeyhelp","plagiarism_gptzero"));

        $this->add_action_buttons(true);
    }
}

