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
use core_text;
use dml_exception;
use local_wunderbyte_table\local\customfield\wbt_field_controller_base;
use local_wunderbyte_table\local\customfield\wbt_field_controller_info;
use mod_booking\customfield\booking_handler;
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
     * @param bool $print Whether to include printable content.
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
     * The sport is compared with the names returned by {@see self::return_sportnames_of_options()},
     * so options with several sports and sports chosen in a dropdown customfield are found too.
     *
     * @param string $sportname e.g. "Basketball"
     * @param int $bookingid optional
     * @return array an array of records with the booking option ids for the provided sport
     * @throws dml_exception
     */
    public static function return_list_of_boids_with_sport(string $sportname, $bookingid = 0) {

        $sportname = self::normalise_sportname($sportname);

        $boids = [];
        foreach (self::return_sportnames_of_options() as $optionid => $sportnames) {
            if (in_array($sportname, $sportnames, true)) {
                $boids[$optionid] = (object)['id' => $optionid];
            }
        }
        return $boids;
    }

    /**
     * Returns the names of the sports of all booking options which have a value in the customfield "sport".
     *
     * What the customfield stores depends on its type: a text field stores the name of the sport itself,
     * a dropdown menu (select) stores the number of the chosen entry and fields with multiple choice
     * (e.g. dynamicformat) store all chosen keys in one value, as comma separated list or json array.
     * Here every stored value is translated into the names an option can be found with.
     *
     * @param int $optionid optional, to get the sports of one booking option only
     * @return array [optionid => [normalised sport name, ...]], {@see self::normalise_sportname()}
     * @throws dml_exception
     */
    public static function return_sportnames_of_options(int $optionid = 0): array {
        global $DB;

        // Customfield shortnames are only unique within a component and area, so we have to name them.
        $sql = "SELECT cfd.id, cfd.instanceid, cfd.value
                  FROM {customfield_data} cfd
                  JOIN {customfield_field} cff ON cff.id = cfd.fieldid
                  JOIN {customfield_category} cfc ON cfc.id = cff.categoryid
                  JOIN {booking_options} bo ON bo.id = cfd.instanceid
                 WHERE cff.shortname = 'sport' AND cfc.component = 'mod_booking' AND cfc.area = 'booking'";
        $params = [];
        if (!empty($optionid)) {
            $sql .= " AND bo.id = :optionid";
            $params['optionid'] = $optionid;
        }

        $fieldcontroller = wbt_field_controller_info::get_instance_by_shortname('sport', 'mod_booking', 'booking');

        $sportnames = [];
        // Many options share the same value, so every distinct value is translated only once.
        $namesofvalue = [];
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $value = (string)$record->value;
            if (trim($value) === '') {
                continue;
            }
            if (!isset($namesofvalue[$value])) {
                $namesofvalue[$value] = self::return_sportnames_of_value($value, $fieldcontroller);
            }
            $sportnames[(int)$record->instanceid] = $namesofvalue[$value];
        }
        $records->close();

        return $sportnames;
    }

    /**
     * Translates the stored value of the customfield "sport" into the names of the sports.
     *
     * @param string $value the value as stored in the table customfield_data
     * @param wbt_field_controller_base $fieldcontroller field controller of the customfield "sport"
     * @return array list of normalised sport names
     */
    private static function return_sportnames_of_value(string $value, wbt_field_controller_base $fieldcontroller): array {
        // The value as a whole comes first: the name of a sport in a text field might contain a comma.
        $keys = [$value];
        $decoded = json_decode($value);
        if (is_array($decoded)) {
            $keys = array_merge($keys, array_filter($decoded, 'is_scalar'));
        } else if (strpos($value, ',') !== false) {
            $keys = array_merge($keys, explode(',', $value));
        }

        $names = [];
        foreach ($keys as $key) {
            $key = (string)$key;
            // A stored key can be the name itself (text field) or stand for the name (dropdown menu).
            // The field controller knows the type of the field and returns the name the user has chosen.
            // We do not want format_string here, as it turns "&" into "&amp;" and never matches a page name.
            foreach ([$key, $fieldcontroller->get_option_value_by_key($key, false)] as $name) {
                $name = self::normalise_sportname($name);
                if ($name !== '') {
                    $names[$name] = $name;
                }
            }
        }
        return array_values($names);
    }

    /**
     * Returns the sports of every sports division: the sections of the course with the sport pages are
     * the sports divisions, the pages in them are the sports, exactly as the shortcode [sparten] shows them.
     *
     * @return array [name of sports division => [normalised sport name => true]] in the order of the sections
     */
    public static function return_sports_of_divisions(): array {
        $courseid = self::return_courseid();
        if (empty($courseid)) {
            return [];
        }

        $data = self::get_all_sportsdivisions_data($courseid, false);

        $sportsofdivisions = [];
        foreach ($data['categories'] as $category) {
            foreach ($category['sports'] as $sport) {
                $sportsofdivisions[$category['name']][self::normalise_sportname($sport['name'])] = true;
            }
        }
        return $sportsofdivisions;
    }

    /**
     * Decides which sports division a booking option with the given sports has to have.
     *
     * A booking option has exactly one sports division. If its sports belong to different sports divisions,
     * a stored one is kept as long as it is one of them, so it can be chosen by hand. In all other cases
     * the first matching sports division in the order of the sections is used.
     *
     * @param array $sportnames normalised names of the sports of the booking option
     * @param array $sportsofdivisions {@see self::return_sports_of_divisions()}
     * @param string $storeddivision the sports division stored for the booking option right now
     * @return string|null null if none of the sports has a sports division
     */
    public static function return_division_for_sports(
        array $sportnames,
        array $sportsofdivisions,
        string $storeddivision = ''
    ): ?string {
        $divisions = [];
        foreach ($sportsofdivisions as $division => $sports) {
            if (!empty(array_intersect_key($sports, array_flip($sportnames)))) {
                $divisions[] = (string)$division;
            }
        }
        if (empty($divisions)) {
            return null;
        }

        foreach ($divisions as $division) {
            if (self::normalise_sportname($division) === self::normalise_sportname($storeddivision)) {
                return $division;
            }
        }
        return reset($divisions);
    }

    /**
     * Returns the id of the customfield "sportsdivision" of mod_booking.
     *
     * @return int 0 if there is no such customfield
     * @throws dml_exception
     */
    public static function return_sportsdivision_fieldid(): int {
        global $DB;

        $sql = "SELECT cff.id
                  FROM {customfield_field} cff
                  JOIN {customfield_category} cfc ON cfc.id = cff.categoryid
                 WHERE cff.shortname = 'sportsdivision' AND cfc.component = 'mod_booking' AND cfc.area = 'booking'";

        return (int)$DB->get_field_sql($sql, [], IGNORE_MULTIPLE);
    }

    /**
     * Saves the sports division of a booking option. The caller has to take care of the caches.
     *
     * @param int $optionid
     * @param string $division
     */
    public static function save_sportsdivision(int $optionid, string $division): void {
        $instance = (object)[
            'id' => $optionid,
            'customfield_sportsdivision' => $division,
        ];
        booking_handler::create()->instance_form_save($instance);
    }

    /**
     * Names of sports and sports divisions are compared case insensitive and without surrounding whitespace.
     *
     * @param string $sportname
     * @return string
     */
    public static function normalise_sportname(string $sportname): string {
        return core_text::strtolower(trim($sportname));
    }
}
