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
 * Add dates to option.
 *
 * @package local_musi
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author  Bernhard Fischer-Sengseis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once("$CFG->dirroot/user/profile/lib.php");

// No guest autologin.
require_login(0, false);

global $DB, $PAGE, $OUTPUT, $USER;

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

$action  = required_param('action', PARAM_ALPHANUMEXT);

// Check if optionid is valid.
$PAGE->set_context($context);

$newsletterprofilefield = get_config('local_musi', 'newsletterprofilefield');
$newslettersubscribed = get_config('local_musi', 'newslettersubscribed');
$newsletterunsubscribed = get_config('local_musi', 'newsletterunsubscribed');

// Page title.
if (empty($newsletterprofilefield)) {
    $title = 'Newsletter profile field missing';
    $description =
        'Profile field for newsletter is not set correctly. Please contact an admin.';
    $alerttype = 'danger';
} else if (empty($newslettersubscribed)) {
    $title = 'Value for subscription missing';
    $description =
        'A config setting is missing. Please contact an admin';
    $alerttype = 'danger';
} else if (empty($newsletterunsubscribed)) {
    $title = 'Value for unsubscription missing';
    $description =
        'A config setting is missing. Please contact an admin';
    $alerttype = 'danger';
} else if ($action == 'subscribe') {
    if (subscribe_to_newsletter()) {
        $title = get_string('newslettersubscribed:title', 'local_musi');
        $description = get_string('newslettersubscribed:description', 'local_musi');
        $alerttype = 'success';
    } else {
        $title = get_string('error');
        $description = get_string('newslettersubscribed:error', 'local_musi');
        $alerttype = 'danger';
    }
} else if ($action == 'unsubscribe') {
    if (unsubscribe_from_newsletter()) {
        $title = get_string('newsletterunsubscribed:title', 'local_musi');
        $description = get_string('newsletterunsubscribed:description', 'local_musi');
        $alerttype = 'success';
    } else {
        $title = get_string('error');
        $description = get_string('newsletterunsubscribed:error', 'local_musi');
        $alerttype = 'danger';
    }
} else {
    $title = 'Action param missing (subscribe/unsubscribe)';
    $description =
        'You did not provide a valid action parameter (subscribe/unsubscribe), so (un)subscription could not be executed.';
    $alerttype = 'danger';
}

$PAGE->set_url('/local/musi/newsletter.php');
$PAGE->navbar->add($title);
$PAGE->set_title(format_string($title));

$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

echo html_writer::div($description, "alert alert-$alerttype");

echo $OUTPUT->footer();

/**
 * Helper function to subscribe logged-in user to newsletter.
 * @return bool true if successful
 */
function subscribe_to_newsletter(): bool {
    global $USER;
    $newsletterprofilefield = get_config('local_musi', 'newsletterprofilefield');
    try {
        profile_save_custom_fields($USER->id, [$newsletterprofilefield => get_config('local_musi', 'newslettersubscribed')]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Helper function to unsubscribe logged-in user from newsletter.
 * @return bool true if successful
 */
function unsubscribe_from_newsletter(): bool {
    global $USER;
    $newsletterprofilefield = get_config('local_musi', 'newsletterprofilefield');
    try {
        profile_save_custom_fields($USER->id, [$newsletterprofilefield => get_config('local_musi', 'newsletterunsubscribed')]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
