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
 * @copyright 2022 Georg Maißer <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

// No guest autologin.
// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
/* require_login(0, false); */

global $DB, $PAGE, $OUTPUT, $USER;

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

$type = optional_param('type', 'liste', PARAM_TEXT);

// Check if optionid is valid.
$PAGE->set_context($context);

$title = get_string('allcourses', 'local_musi');

$PAGE->set_url('/local/musi/allekurse.php');
$PAGE->navbar->add($title);
$PAGE->set_title(format_string($title));
$PAGE->set_heading($title);
$PAGE->set_pagelayout('base');
$PAGE->add_body_class('local_musi-allcourses');

echo $OUTPUT->header();

switch ($type) {
    case 'karten':
        echo format_text("[allekursekarten filter=1 search=1 sort=1 sortby=text sortorder=asc requirelogin=false]", FORMAT_HTML);
        break;
    case 'grid':
        echo format_text("[allekursegrid filter=1 search=1 sort=1 sortby=text sortorder=asc requirelogin=false]", FORMAT_HTML);
        break;
    case 'liste':
    default:
        echo format_text("[allekurseliste filter=1 search=1 sort=1 sortby=text sortorder=asc requirelogin=false]", FORMAT_HTML);
        break;
}

echo $OUTPUT->footer();
