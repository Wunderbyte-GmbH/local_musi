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
 * @package     local_musi
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer-Sengseis
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_booking\singleton_service;

require_once(__DIR__ . '/../../config.php');

require_login(0, true);

global $DB, $PAGE, $OUTPUT, $USER;

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

// Optional userid.
$userid = optional_param('userid', 0, PARAM_INT);

if (empty($userid)) {
    $userid = $USER->id;
    $user = $USER;
} else {
    $user = singleton_service::get_instance_of_user($userid);
}

// Check if optionid is valid.
$PAGE->set_context($context);
$title = get_string('history', 'local_shopping_cart');
$PAGE->set_url('/local/musi/shoppingcarthistory.php');
$PAGE->navbar->add($title);
$PAGE->set_title(format_string($title));
$PAGE->set_heading($title . " - $user->firstname $user->lastname ($user->email)");
$PAGE->set_pagelayout('base');
$PAGE->add_body_class('local_musi-shoppingcarthistory');
echo $OUTPUT->header();
echo "<div style='margin-left: 3%'>";
echo format_text("[shoppingcarthistory userid=$userid]", FORMAT_HTML);
echo "</div>";
echo $OUTPUT->footer();
