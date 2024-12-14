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
 * Viewing invitation history script.
 *
 * @package    enrol_invitation
 * @copyright  2021-2024 TNG Consulting Inc. {@link https://www.tngconsulting.ca}
 * @author     Michael Milette
 * @copyright  2013 UC Regents
 * @author     Rex Lorenzo
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/invitation_form.php');

require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();
$courseid = required_param('courseid', PARAM_INT);
$inviteid = optional_param('inviteid', 0, PARAM_INT);
$actionid = optional_param('actionid', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$context = context_course::instance($courseid);
if (!has_capability('enrol/invitation:enrol', $context)) {
    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    throw new moodle_exception('nopermissiontosendinvitation', 'enrol_invitation', $courseurl);
}

// Set up page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/enrol/invitation/history.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('course');
$PAGE->set_course($course);
$pagetitle = get_string('invitehistory', 'enrol_invitation');
$PAGE->set_heading($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->navbar->add($pagetitle);

// Do not display the page if we are going to be redirecting the user.
if ($actionid != invitation_manager::INVITE_RESEND) {
    // OUTPUT form.
    echo $OUTPUT->header();

    // Print out a heading.
    echo $OUTPUT->heading($pagetitle, 2, 'headingblock');

    // OUTPUT page tabs.
    print_page_tabs('history');
}

// Course must have invitation plugin installed (will give error if not found).
$invitationmanager = new invitation_manager($courseid, true);

// Get invites and display them.
$invites = $invitationmanager->get_invites();

if (empty($invites)) {
    echo $OUTPUT->notification(get_string('noinvitehistory', 'enrol_invitation'), 'notifymessage');
} else {
    // Update invitation if the user decided to revoke/extend/resend an invite.
    if ($inviteid && $actionid) {
        if (!$currinvite = $invites[$inviteid]) {
            throw new moodle_exception('invalidinviteid');
        }
        if ($actionid == invitation_manager::INVITE_REVOKE) {
            // Set the invite to be expired.
            $DB->set_field(
                'enrol_invitation',
                'timeexpiration',
                time() - 1,
                ['courseid' => $currinvite->courseid, 'id' => $currinvite->id]
            );
            $DB->set_field('enrol_invitation', 'status', 'revoked', ['courseid' => $currinvite->courseid, 'id' => $currinvite->id]);

            \enrol_invitation\event\invitation_deleted::create_from_invitation($currinvite)->trigger();

            echo $OUTPUT->notification(get_string('revoke_invite_sucess', 'enrol_invitation'), 'notifysuccess');
        } else if ($actionid == invitation_manager::INVITE_EXTEND) {
            // Resend the invite and email.
            $invitationmanager->send_invitations($currinvite, true);

            echo $OUTPUT->notification(get_string('extend_invite_sucess', 'enrol_invitation'), 'notifysuccess');
        } else if ($actionid == invitation_manager::INVITE_RESEND) {
            // Send the user to the invite form with prefilled data.
            $redirect = new moodle_url(
                '/enrol/invitation/invitation.php',
                ['courseid' => $currinvite->courseid, 'inviteid' => $currinvite->id]
            );
            redirect($redirect);
        } else {
            throw new moodle_exception('invalidactionid');
        }

        // Get the updated invites.
        $invites = $invitationmanager->get_invites();
    }

    // Columns to display.
    $columns = [
            'invitee'           => get_string('historyinvitee', 'enrol_invitation'),
            'role'              => get_string('historyrole', 'enrol_invitation'),
            'status'            => get_string('historystatus', 'enrol_invitation'),
            'datesent'          => get_string('historydatesent', 'enrol_invitation'),
            'dateexpiration'    => get_string('historydateexpiration', 'enrol_invitation'),
            'actions'           => get_string('historyactions', 'enrol_invitation'),
    ];

    $table = new flexible_table('invitehistory');
    $table->define_columns(array_keys($columns));
    $table->define_headers(array_values($columns));
    $table->define_baseurl($PAGE->url);
    $table->set_attribute('class', 'generaltable');

    $table->setup();

    $strftime = get_string('strftimedatetimeshort', 'core_langconfig');

    $rolecache = [];
    foreach ($invites as $invite) {
        /* Build display row:
         * [0] - invitee
         * [1] - role
         * [2] - status
         * [3] - dates sent
         * [4] - expiration date
         * [5] - actions
         */

        // Display invitee.
        $row[0] = $invite->email;

        // Figure out invited role.
        if (empty($rolecache[$invite->roleid])) {
            $role = $DB->get_record('role', ['id' => $invite->roleid]);
            if (empty($role)) {
                // Cannot find role, give error.
                $rolecache[$invite->roleid] =
                        get_string('historyundefinedrole', 'enrol_invitation');
            } else {
                $rolecache[$invite->roleid] = $role->name;
            }
        }
        $row[1] = $rolecache[$invite->roleid];

        // What is the status of the invite?
        $status = $invitationmanager->get_invite_status($invite);
        $row[2] = $status;

        // If status was used, figure out who used the invite.
        $result = $invitationmanager->who_used_invite($invite);
        if (!empty($result)) {
            $row[2] .= ' ' . get_string('used_by', 'enrol_invitation', $result);
        }

        // If user's enrollment expired or will expire, let viewer know.
        $result = $invitationmanager->get_access_expiration($invite);
        if (!empty($result)) {
            $row[2] .= ' ' . $result;
        }

        // When was the invite sent?
        $row[3] = userdate($invite->timesent, $strftime);

        // When does the invite expire?
        $row[4] = userdate($invite->timeexpiration, $strftime);

        // If status is active, then state how many days/minutes left.
        if ($status == get_string('status_invite_active', 'enrol_invitation')) {
            $expirestext = sprintf(
                '%s %s',
                get_string('historyexpires_in', 'enrol_invitation'),
                distance_of_time_in_words(time(), $invite->timeexpiration, true)
            );
            $row[4] .= ' ' . html_writer::tag('span', '(' . $expirestext . ')', ['expires-text']);
        }

        // Are there any actions user can do?
        $row[5] = '';
        $url = new moodle_url('/enrol/invitation/history.php', ['courseid' => $courseid, 'inviteid' => $invite->id]);
        // Same if statement as above, seperated for clarity.
        if ($status == get_string('status_invite_active', 'enrol_invitation')) {
            // Create link to revoke an invite.
            $url->param('actionid', invitation_manager::INVITE_REVOKE);
            $row[5] .= html_writer::link($url, get_string('action_revoke_invite', 'enrol_invitation'));
            $row[5] .= html_writer::start_tag('br');
            // Create link to extend an invite.
            $url->param('actionid', invitation_manager::INVITE_EXTEND);
            $row[5] .= html_writer::link($url, get_string('action_extend_invite', 'enrol_invitation'));
        } else if (
            $status == get_string('status_invite_expired', 'enrol_invitation')
            || $status == get_string('status_invite_revoked', 'enrol_invitation')
        ) {
            // Create link to resend invite.
            $url->param('actionid', invitation_manager::INVITE_RESEND);
            $row[5] .= html_writer::link($url, get_string('action_resend_invite', 'enrol_invitation'));
        }

        $table->add_data($row);
    }

    $table->finish_output();
}

echo $OUTPUT->footer();
