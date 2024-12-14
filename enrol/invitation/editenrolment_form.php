<?php
// This file is part of Invitation for Moodle - https://moodle.org/
//
// Invitation is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Invitation is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Contains the form used to edit invitation enrolment for a user.
 *
 * @package    enrol_invitation
 * @copyright  2013 UC Regents
 * @copyright  2011 Jerome Mouneyrac {@link http://www.moodleitandme.com}
 * @author     Jerome Mouneyrac
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Form to edit invitation enrolment for a user.
 *
 * @copyright  2013 UC Regents
 * @copyright  2011 Jerome Mouneyrac {@link http://www.moodleitandme.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_invitation_user_enrolment_form extends moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        $user   = $this->_customdata['user'];
        $course = $this->_customdata['course'];
        $ue     = $this->_customdata['ue'];

        $mform->addElement('header', 'general', '');

        $options = [ENROL_USER_ACTIVE    => get_string('participationactive', 'enrol'),
                         ENROL_USER_SUSPENDED => get_string('participationsuspended', 'enrol'), ];
        if (isset($options[$ue->status])) {
            $mform->addElement('select', 'status', get_string('participationstatus', 'enrol'), $options);
        }

        $mform->addElement('date_selector', 'timestart', get_string('enroltimestart', 'enrol'), ['optional' => true]);

        $mform->addElement('date_selector', 'timeend', get_string('enroltimeend', 'enrol'), ['optional' => true]);

        $mform->addElement('hidden', 'ue');
        $mform->setType('ue', PARAM_INT);

        $mform->addElement('hidden', 'ifilter');
        $mform->setType('ifilter', PARAM_ALPHA);

        $this->add_action_buttons();

        $this->set_data([
            'ue' => $ue->id,
            'status' => $ue->status,
            'timestart' => $ue->timestart,
            'timeend' => $ue->timeend,
        ]);
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     *
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['timestart']) && !empty($data['timeend'])) {
            if ($data['timestart'] >= $data['timeend']) {
                $errors['timestart'] = get_string('error');
                $errors['timeend'] = get_string('error');
            }
        }

        return $errors;
    }
}
