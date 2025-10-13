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

namespace local_musi;

use coding_exception;
use context_system;
use dml_exception;
use moodle_exception;
use HTMLPurifier_Exception;

/**
 * Helper functions for payment stuff.
 *
 * @package local_musi
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sports {
    /**
     * Generate a list of all Sports.
     *
     * @return array
     */
    public static function return_list_of_pages() {
        global $DB;

        $sql = "SELECT cm.id, p.name, p.intro
                FROM {page} p
                LEFT JOIN {course_modules} cm
                ON cm.instance = p.id
                JOIN {modules} m
                ON m.id = cm.module
                WHERE m.name = 'page'
                AND (p.content LIKE '%allekurse%category%'
                OR p.intro LIKE '%allekurse%category%') ";

        return $DB->get_records_sql($sql);
    }

    /**
     * Return courseid of the page with the most courses.
     *
     * @return int
     */
    public static function return_courseid() {
        global $DB;

        $courseid = $DB->get_field_sql(
            "SELECT s1.course FROM (
                SELECT DISTINCT cm.course, count(cm.course)
                FROM {page} p
                JOIN {course_modules} cm
                ON cm.instance = p.id
                JOIN {modules} m
                ON m.id = cm.module
                WHERE (p.content LIKE '%allekurse%category%' OR p.intro LIKE '%allekurse%category%')
                AND m.name = 'page'
                GROUP BY cm.course
                ORDER BY count(cm.course) DESC
            ) s1 LIMIT 1"
        );

        return $courseid;
    }

    /**
     * Returns the sports divisions
     * @param int $courseid
     * @param bool $noprint
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     * @throws HTMLPurifier_Exception
     */
    public static function get_all_sportsdivisions_data(int $courseid, $print = true): array {

        global $DB;

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $pages = self::return_list_of_pages();

        $data['categories'] = [];

        $caneditsubstitutionspool = has_capability('local/musi:editsubstitutionspool', context_system::instance());
        $canviewsubstitutionspool = has_capability('local/musi:viewsubstitutionspool', context_system::instance());

        // Iterate through sport categories.
        foreach ($sections as $section) {
            if (empty($section->name)) {
                continue;
            }

            $cmids = explode(',', $section->sequence);

            $category = [
                'name' => $section->name,
                'categoryid' => $section->id,
                'summary' => $section->summary,
                'sports' => [],
            ];

            // For performance.
            // Get all sport records.
            $sportrecords = $DB->get_records_sql("SELECT sport, teachers FROM {local_musi_substitutions}");
            // Get all teacher records.
            $teachersarr = [];
            foreach ($sportrecords as $sportrecord) {
                $teacherids = explode(',', $sportrecord->teachers);
                foreach ($teacherids as $teacherid) {
                    if (empty($teacherid)) {
                        continue;
                    }
                    $teachersarr[$teacherid] = $teacherid;
                }
            }

            if (!empty($teachersarr)) {
                [$inorequal, $params] = $DB->get_in_or_equal($teachersarr);
                $sql = "SELECT id, firstname, lastname, email, phone1, phone2 FROM {user} WHERE id $inorequal";
                $teacherrecords = $DB->get_records_sql($sql, $params);
            } else {
                $teacherrecords = [];
            }

            // Sports.
            foreach ($cmids as $cmid) {
                if (isset($pages[$cmid])) {
                    // If the page is hidden, we do not want to add it.
                    [$course, $cm] = get_course_and_cm_from_cmid($cmid);
                    if (empty($cm->visible)) {
                        continue;
                    }

                    $sport = $pages[$cmid]->name;

                    $description = null;
                    // We do not add descriptions, if they contain one of the "[allekurse..." shortcodes.
                    if (strpos($pages[$cmid]->intro, "[allekurse") == false) {
                        $description = $pages[$cmid]->intro;
                    }

                    $editsubstitutionspool = null;
                    if ($caneditsubstitutionspool) {
                        $editsubstitutionspool = true;
                    }

                    $viewsubstitutionspool = null;
                    $substitutionteachers = [];
                    if ($canviewsubstitutionspool) {
                        $viewsubstitutionspool = true;
                        // Retrieve the list of teachers who can substitute.
                        if (!empty($sportrecords[$sport])) {
                            $record = $sportrecords[$sport];
                            if (!empty($record->teachers)) {
                                $teacherids = explode(',', $record->teachers);
                                foreach ($teacherids as $teacherid) {
                                    $fullteacher = $teacherrecords[$teacherid] ?? null;
                                    if (!empty($fullteacher)) {
                                        $teacher['id'] = $fullteacher->id;
                                        $teacher['firstname'] = $fullteacher->firstname;
                                        $teacher['lastname'] = $fullteacher->lastname;
                                        $teacher['email'] = $fullteacher->email;
                                        if (get_config('local_musi', 'substitutionspoolshowphonenumbers')) {
                                            $teacher['phone1'] = $fullteacher->phone1;
                                            $teacher['phone2'] = $fullteacher->phone2;
                                        }
                                        $substitutionteachers[$fullteacher->id] = $teacher;
                                    }
                                }
                                // Now sort the teachers by last name.
                                usort($substitutionteachers, function ($a, $b) {
                                    return $a['lastname'] <=> $b['lastname'];
                                });
                            }
                        }
                        // Generate mailto-Link.
                        $mailstrings = self::generate_mailstring($substitutionteachers);
                    }

                    $category['sports'][] = [
                        'name' => $sport,
                        'editsubstitutionspool' => $editsubstitutionspool,
                        'viewsubstitutionspool' => $viewsubstitutionspool,
                        'substitutionteachers' => empty($substitutionteachers) ? null : $substitutionteachers,
                        'mailtolink' => $mailstrings['mailtolink'] ?? null,
                        'emailstring' => $mailstrings['emailstring'] ?? null,
                        'description' => $description,
                        'id' => $cmid,
                        'table' => $print ? format_text('[allekurseliste sort=1 search=1 lazy=1 requirelogin=false category="' .
                            $sport . '"]') : null,
                    ];
                }
            }
            // Group teachers of all sports to this category.
            $substitutionteachers = [];
            foreach ($category['sports'] as $sport) {
                if (isset($sport['substitutionteachers']) && is_array($sport['substitutionteachers'])) {
                    foreach ($sport['substitutionteachers'] as $substitutionteacher) {
                        if (!isset($substitutionteachers[(int)$substitutionteacher['id']])) {
                            $substitutionteachers[(int)$substitutionteacher['id']] = $substitutionteacher;
                        }
                    }
                }
            }
            $substitutionteachers = array_values($substitutionteachers);
            // Now sort the teachers by last name.
            usort($substitutionteachers, function ($a, $b) {
                return $a['lastname'] <=> $b['lastname'];
            });

            $mailstrings = self::generate_mailstring($substitutionteachers);
            $category['categorydata'] = [
                'viewsubstitutionspool' => $viewsubstitutionspool,
                'substitutionteachers' => empty($substitutionteachers) ? null : $substitutionteachers,
                'mailtolink' => $mailstrings['mailtolink'] ?? null,
                'emailstring' => $mailstrings['emailstring'] ?? null,
                'id' => $category['categoryid'] ?? null,
            ];
            $data['categories'][] = $category;
        }

        return $data;
    }

    /**
     * Resolve array of teachers into link list string.
     *
     * @param array $substitutionteachers
     *
     * @return array
     *
     */
    private static function generate_mailstring(array $substitutionteachers): array {
        global $USER;
        $emailstring = '';
        $mailtolink = '';
        if (!empty($substitutionteachers)) {
            foreach ($substitutionteachers as $teacher) {
                if (!empty($teacher['email']) && ($teacher['email'] != $USER->email)) {
                    $emailstring .= $teacher['email'] . ";";
                }
            }
            if (!empty($emailstring)) {
                $emailstring = trim($emailstring, ';');
                $loggedinuseremail = $USER->email;
                $mailtolink = str_replace(
                    ' ',
                    '%20',
                    htmlspecialchars(
                        "mailto:$loggedinuseremail?bcc=$emailstring",
                        ENT_QUOTES
                    )
                );
            }
        }
        return [
            'mailtolink' => $mailtolink ?? null,
            'emailstring' => $emailstring ?? null,
        ];
    }

    /**
     * Get an array of booking option ids for a provided sport name, e.g. "Basketball".
     *
     * @param string $sportname e.g. "Basketball"
     * @param int $bookingid optional
     * @return array an array of booking option ids for the provided sport
     * @throws dml_exception
     */
    public static function return_list_of_boids_with_sport(string $sportname, $bookingid = 0) {

        global $DB;

        $sqllikesport = $DB->sql_like('cfd.value', ':sportname', false, false);

        $sql = "SELECT bo.id
                FROM {booking_options} bo
                JOIN {customfield_data} cfd ON bo.id=cfd.instanceid
                JOIN {customfield_field} cff ON cff.id=cfd.fieldid
                WHERE cff.shortname='sport' AND $sqllikesport";

        $params = [
            'sportname' => $sportname,
        ];

        return $DB->get_records_sql($sql, $params);
    }
}
