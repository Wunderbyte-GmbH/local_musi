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
 * Transactionlist page.
 *
 * @package    local_musi
 * @copyright  2023 Wunderbyte GmbH
 * @author     Christian Badusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_musi\output\transactionslist;

require_once('../../config.php');
require_login();

// Set up the page.
if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

$PAGE->set_context($context);
$title = get_string('pluginname', 'local_musi');
$pagetitle = $title;
$url = new moodle_url("/local/musi/transactionlist.php");

$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(get_string('transactionslist', 'local_musi'));

$output = $PAGE->get_renderer('local_musi');

echo $output->header();

$data = new transactionslist();
echo $output->render_transactions_list($data);

echo $output->footer();
