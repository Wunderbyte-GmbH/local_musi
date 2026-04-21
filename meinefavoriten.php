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
 * An overview of all booking options the currently logged in user
 * has marked as favorites.
 *
 * @package local_musi
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// No guest autologin.
require_login(0, false);

global $PAGE, $OUTPUT;

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

$PAGE->set_context($context);

$title = "<i class=\"fa fa-star-o\" aria-hidden=\"true\"></i>&nbsp;" . get_string('myfavorites', 'local_musi');

$PAGE->set_url('/local/musi/meinefavoriten.php');
$PAGE->navbar->add($title);
$PAGE->set_title($title);
$PAGE->set_pagelayout('base');
$PAGE->add_body_class('local_musi-meinefavoriten');

echo $OUTPUT->header();

echo "<div class='text-center h1'>$title</div>";

echo format_text("[meinefavoriten]", FORMAT_HTML);

echo $OUTPUT->footer();
