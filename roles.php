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
 * @author      Stephan Lorbek <stephan.lorbek@uni-graz.at
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

global $DB, $PAGE, $OUTPUT, $USER;

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

$action = optional_param('action', '', PARAM_ALPHA);
if ($action) {
    $roleid = required_param('roleid', PARAM_INT);
} else {
    $roleid = 0;
}

require_capability('moodle/role:manage', $context);

$PAGE->set_context($context);
$title = get_string('roleoverview', 'local_musi');
$PAGE->set_url('/local/musi/roles.php');
$PAGE->navbar->add($title);
$PAGE->set_title(format_string($title));
$PAGE->set_pagelayout('base');
$PAGE->add_body_class('local_musi-roles');

$roles = role_fix_names(get_all_roles(), $context, ROLENAME_ORIGINAL);
$table = new html_table();
$table->colclasses = ['leftalign', 'leftalign', 'leftalign', 'leftalign'];
$table->attributes['class'] = 'admintable generaltable';
$table->id = 'roles';

switch($action) {
    case 'view':
        $PAGE->set_heading($title . ' - ' . $roles[$roleid]->localname);
        $assignments = $DB->get_records_sql('
        SELECT DISTINCT username, firstname, lastname, ra.userid, email, muid.data as "affiliation"
        FROM {role_assignments} ra JOIN {user} u ON(ra.userid=u.id)
        JOIN {user_info_data} muid ON(muid.userid = u.id)
        JOIN {user_info_field} muif ON(muif.id = muid.fieldid)
        WHERE ra.roleid = :id AND muif.name = :scope', ["id" => $roleid, "scope" => "eduPersonScopedAffiliation"]);
        $table->head = [
            get_string('user') . ' (' .  count($assignments) . ')',
            get_string('email'),
            get_string('roleaffiliation', 'local_musi'),
        ];
        foreach ($assignments as $assignment) {
            $table->data[] = [
                '<a href="'.new moodle_url("/user/view.php", ["id" => $assignment->userid]).'">'.
                    $assignment->firstname . ' ' . $assignment->lastname.
                '</a>',
                $assignment->email,
                $assignment->affiliation,
            ];
        }
        break;
    default:
        $table->head = [
            get_string('role') . ' ' . $OUTPUT->help_icon('roles', 'core_role'),
            get_string('description'),
            get_string('roleshortname', 'core_role'),
        ];

        $table->data = [];
        foreach ($roles as $role) {
            $url = new moodle_url($PAGE->url, ["id" => $role->id]);
            $table->data[] = [
                '<a href="' . $PAGE->url . '?action=view&amp;roleid=' . $role->id . '">'.$role->localname.'</a>',
                role_get_description($role),
                $role->shortname,
            ];
        }
}
echo $OUTPUT->header();
echo "<div style='margin-left: 3%'>";
echo html_writer::table($table);
echo "</div>";
echo $OUTPUT->footer();
