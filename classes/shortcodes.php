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
 * Shortcodes for local_musi
 *
 * @package local_musi
 * @subpackage db
 * @since Moodle 3.11
 * @copyright 2022 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi;

use Closure;
use context_system;
use html_writer;
use local_wunderbyte_table\filters\types\callback;
use local_wunderbyte_table\filters\types\hourlist;
use mod_booking\bo_availability\bo_info;
use mod_booking\customfield\booking_handler;
use mod_booking\output\page_allteachers;
use local_musi\table\musi_table;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use local_wunderbyte_table\filters\types\datepicker;
use local_wunderbyte_table\filters\types\standardfilter;
use mod_booking\booking;
use mod_booking\singleton_service;
use moodle_url;

/**
 * Deals with local_shortcodes regarding booking.
 */
class shortcodes {
    /**
     * Prints out list of bookingoptions.
     * Arguments can be 'category' or 'perpage'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return mixed
     */
    public static function showallsports($shortcode, $args, $content, $env, $next) {

        global $OUTPUT;

        self::fix_args($args);

        // Get the ID of the course containing the sports categories.
        $courseid = sports::return_courseid();

        // If it's not set, we do nothing.
        if (empty($courseid)) {
            return get_string('nosportsdivision', 'local_musi');
        }

        $data = sports::get_all_sportsdivisions_data($courseid);

        return $OUTPUT->render_from_template('local_musi/shortcodes_rendersportcategories', $data);
    }
    /**
     * Unifiedview for List and Cards.
     *
     * @param mixed $shortcode
     * @param mixed $args
     * @param mixed $content
     * @param mixed $env
     * @param mixed $next
     * @param bool $renderascard
     * @param string $additionalwhere
     * @param array $additionalpalarms
     *
     * @return array
     *
     */
    public static function unifiedview(
        $shortcode,
        $args,
        $content,
        $env,
        $next,
        $renderascard = false,
        $additionalwhere = '',
        $additionalparams = []
    ): array {
        global $DB;

        self::fix_args($args);
        $booking = self::get_booking($args);
        $perpage = \mod_booking\shortcodes::check_perpage($args);

        $table = self::inittableforcourses();

        $wherearray = ['bookingid' => (int)$booking->id];

        self::set_wherearray_from_arguments($args, $wherearray, $additionalwhere);

        if (isset($args['teacherid']) && (is_int((int)$args['teacherid']))) {
            $wherearray['teacherobjects'] = '%"id":' . $args['teacherid'] . ',%';
        }
        [$fields, $from, $where, $params, $filter] = self::get_sql_params($booking, $wherearray, $additionalwhere);

        if (!empty($additionalparams)) {
            $params = array_merge($params, $additionalparams);
        }

        $table->set_filter_sql($fields, $from, $where, $filter, $params);
        $table->use_pages = true;

        if (!empty($args['image'])) {
            $table->set_tableclass('cardimageclass', 'pr-0 pl-1');
            $table->add_subcolumns('cardimage', ['image']);
        }

        self::set_table_options_from_arguments($table, $args);

        if ($renderascard) {
            self::generate_table_for_cards($table, $args);
        } else {
            self::generate_table_for_list($table, $args);
        }
        return [$table, $perpage];
    }

    /**
     * Prints out list of all bookingoptions.
     * Arguments can be 'category' or 'perpage'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function allcourseslist($shortcode, $args, $content, $env, $next) {
        global $DB;
        self::fix_args($args);
        $additionalwhere = '';
        $additionalparams = [];
        $booking = self::get_booking($args);

        if (!empty($args['includeoptions'])) {
            $wherearray = [];
            [$inorequal, $additionalparams] = $DB->get_in_or_equal(explode(',', $args['includeoptions']), SQL_PARAMS_NAMED);
            $additionalwhere = " (bookingid = " . (int)$booking->id . " OR id $inorequal )";
        }
        [$table, $perpage] = self::unifiedview(
            $shortcode,
            $args,
            $content,
            $env,
            $next,
            false,
            $additionalwhere,
            $additionalparams
        );
        $table->showcountlabel = empty($args['countlabel']) ? false : $args['countlabel'];
        return self::generate_output($args, $table, $perpage);
    }

    /**
     * Prints out list of cards bookingoptions.
     * Arguments can be 'category' or 'perpage'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function allcoursescards($shortcode, $args, $content, $env, $next) {
        self::fix_args($args);
        [$table, $perpage] = self::unifiedview($shortcode, $args, $content, $env, $next, true);
        return self::generate_output($args, $table, $perpage);
    }

    /**
     * Prints out grid of bookingoptions.
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string $out
     */
    public static function allcoursesgrid($shortcode, $args, $content, $env, $next) {

        self::fix_args($args);
        $booking = self::get_booking($args);
        $perpage = \mod_booking\shortcodes::check_perpage($args);

        $table = self::inittableforcourses();

        $wherearray = ['bookingid' => (int)$booking->id];

        $additionalwhere = '';
        self::set_wherearray_from_arguments($args, $wherearray, $additionalwhere);

        if (isset($args['teacherid']) && (is_int((int)$args['teacherid']))) {
            $wherearray['teacherobjects'] = '%"id":' . $args['teacherid'] . ',%';
        }

        [$fields, $from, $where, $params, $filter] = self::get_sql_params($booking, $wherearray, $additionalwhere);

        $table->set_filter_sql($fields, $from, $where, $filter, $params);

        $table->use_pages = false;

        $table->define_cache('mod_booking', 'bookingoptionstable');

        $table->add_subcolumns('entrybody', ['text', 'dayofweektime', 'sport', 'sportsdivision',
            'teacher', 'location', 'bookings', 'minanswers', 'price', 'action']);

        // This avoids showing all keys in list view.
        $table->add_classes_to_subcolumns('entrybody', ['columnkeyclass' => 'd-md-none']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-text'], ['text']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-dayofweektime'], ['dayofweektime']);
        $table->add_classes_to_subcolumns('entrybody', ['columniclassbefore' => 'fa fa-clock-o'], ['dayofweektime']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-sport'], ['sport']);
        $table->add_classes_to_subcolumns('entrybody', ['columnvalueclass' => 'sport-badge bg-info text-light'], ['sport']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-sportsdivision'], ['sportsdivision']);
        $table->add_classes_to_subcolumns('entrybody', ['columnvalueclass' => 'sportsdivision-badge'], ['sportsdivision']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-teacher'], ['teacher']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-location'], ['location']);
        $table->add_classes_to_subcolumns('entrybody', ['columniclassbefore' => 'fa fa-map-marker'], ['location']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-booking'], ['bookings']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-minanswers'], ['minanswers']);

        $table->add_classes_to_subcolumns('entrybody', ['columnclass' => 'grid-area-price'], ['price']);

        // Override naming for columns. one could use getstring for localisation here.
        $table->add_classes_to_subcolumns(
            'cardbody',
            ['keystring' => get_string('tableheadertext', 'booking')],
            ['text']
        );
        $table->add_classes_to_subcolumns(
            'cardbody',
            ['keystring' => get_string('tableheaderteacher', 'booking')],
            ['teacher']
        );
        $table->add_classes_to_subcolumns(
            'cardbody',
            ['keystring' => get_string('tableheadermaxanswers', 'booking')],
            ['maxanswers']
        );
        $table->add_classes_to_subcolumns(
            'cardbody',
            ['keystring' => get_string('tableheadermaxoverbooking', 'booking')],
            ['maxoverbooking']
        );

        $table->is_downloading('', 'List of booking options');

        self::set_table_options_from_arguments($table, $args);

        $table->tabletemplate = 'local_musi/table_grid_list';
        return self::generate_output($args, $table, $perpage);
    }

    /**
     * Prints out list of cards of bookingoptions.
     * Arguments can be 'id', 'category' or 'perpage'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function mycoursescards($shortcode, $args, $content, $env, $next) {

        global $USER;
        self::fix_args($args);
        $booking = self::get_booking($args);
        $userid = $USER->id;


        $perpage = \mod_booking\shortcodes::check_perpage($args);

        $table = self::inittableforcourses();

        $wherearray = ['bookingid' => (int)$booking->id];

        $additionalwhere = '';
        self::set_wherearray_from_arguments($args, $wherearray, $additionalwhere);

        if (isset($args['teacherid']) && (is_int((int)$args['teacherid']))) {
            $wherearray['teacherobjects'] = '%"id":' . $args['teacherid'] . ',%';
        }
        [$fields, $from, $where, $params, $filter] = self::get_sql_params($booking, $wherearray, $additionalwhere, $userid);

        $table->set_filter_sql($fields, $from, $where, $filter, $params);

        $table->use_pages = false;

        self::generate_table_for_cards($table, $args);

        self::set_table_options_from_arguments($table, $args);
        $table->cardsort = true;
        // We override the cache, because the my cache has to be invalidated with every booking.
        $table->define_cache('mod_booking', 'mybookingoptionstable');

        return self::generate_output($args, $table, $perpage);
    }

    /**
     * Prints out list of bookingoptions where the current user is a trainer.
     * Arguments can be 'id', 'category' or 'perpage'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function mytaughtcoursescards($shortcode, $args, $content, $env, $next) {

        global $USER;
        self::fix_args($args);
        $booking = self::get_booking($args);

        global $USER;
        self::fix_args($args);
        $booking = self::get_booking($args);

        $perpage = \mod_booking\shortcodes::check_perpage($args);

        $table = self::inittableforcourses();

        // We want to check for the currently logged in user...
        // ... if (s)he is teaching courses.
        $teacherid = $USER->id;

        // This is the important part: We only filter for booking options where the current user is a teacher!
        // Also we only want to show courses for the currently set booking instance (semester instance).
        [$fields, $from, $where, $params, $filter] =
            booking::get_all_options_of_teacher_sql($teacherid, (int)$booking->id);

        $table->set_filter_sql($fields, $from, $where, $filter, $params);

        $table->use_pages = false;

        self::generate_table_for_cards($table, $args);

        self::set_table_options_from_arguments($table, $args);

        $table->cardsort = true;

        // This allows us to use infinite scrolling, No pages will be used.
        $table->infinitescroll = 30;

        return self::generate_output($args, $table, $perpage);
    }

    /**
     * Prints out list of my booked bookingoptions.
     * Arguments can be 'category' or 'perpage'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function mycourseslist($shortcode, $args, $content, $env, $next) {

        global $USER;
        $userid = $USER->id;
        self::fix_args($args);
        $booking = self::get_booking($args);

        $perpage = \mod_booking\shortcodes::check_perpage($args);

        $table = self::inittableforcourses();

        $table->showcountlabel = empty($args['countlabel']) ? false : $args['countlabel'];
        $wherearray = ['bookingid' => (int)$booking->id];

        $additionalwhere = '';
        self::set_wherearray_from_arguments($args, $wherearray, $additionalwhere);

        [$fields, $from, $where, $params, $filter] = self::get_sql_params($booking, $wherearray, $additionalwhere, $userid);

        $table->set_filter_sql($fields, $from, $where, $filter, $params);

        $table->use_pages = false;

        self::generate_table_for_list($table, $args);

        self::set_table_options_from_arguments($table, $args);

        $table->cardsort = true;

        // We override the cache, because the my cache has to be invalidated with every booking.
        $table->define_cache('mod_booking', 'mybookingoptionstable');

        return self::generate_output($args, $table, $perpage);
    }

    /**
     * Prints out user dashboard overview as cards.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function userdashboardcards($shortcode, $args, $content, $env, $next) {
        global $DB, $PAGE, $USER;
        self::fix_args($args);
        // If the id argument was not passed on, we have a fallback in the connfig.
        if (!isset($args['id'])) {
            $args['id'] = get_config('local_musi', 'shortcodessetinstance');
        }

        // To prevent misconfiguration, id has to be there and int.
        if (!(isset($args['id']) && $args['id'] && is_int((int)$args['id']))) {
            return 'Set id of booking instance';
        }

        if (!$booking = singleton_service::get_instance_of_booking_by_cmid($args['id'])) {
            return 'Couldn\'t find right booking instance ' . $args['id'];
        }

        $user = $USER;

        $booked = $booking->get_user_booking_count($USER);
        $asteacher = $DB->get_fieldset_select(
            'booking_teachers',
            'optionid',
            "userid = {$USER->id} AND bookingid = $booking->id "
        );
        $credits = shopping_cart_credits::get_balance($USER->id);

        $data['booked'] = $booked;
        $data['teacher'] = count($asteacher);
        $data['credits'] = $credits[0];

        $output = $PAGE->get_renderer('local_musi');
        return $output->render_user_dashboard_overview($data);
    }

    /**
     * Prints out all teachers as cards.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function allteacherscards($shortcode, $args, $content, $env, $next) {
        global $DB, $PAGE;
        self::fix_args($args);
        $teacherids = [];

        // Now get all teachers that we're interested in.
        $sqlteachers =
            "SELECT DISTINCT bt.userid, u.firstname, u.lastname, u.email
            FROM {booking_teachers} bt
            LEFT JOIN {user} u
            ON u.id = bt.userid
            ORDER BY u.lastname ASC";

        if ($teacherrecords = $DB->get_records_sql($sqlteachers)) {
            foreach ($teacherrecords as $teacherrecord) {
                $teacherids[] = $teacherrecord->userid;
            }
        }

        // Now prepare the data for all teachers.
        $data = new page_allteachers($teacherids);
        $output = $PAGE->get_renderer('mod_booking');        // And return the rendered page showing all teachers.
        return $output->render_allteacherspage($data);
    }

    /**
     * Initiates table of courses.
     *
     * @param mixed $booking
     *
     * @return musi_table $table
     *
     */
    private static function inittableforcourses() {

        global $PAGE, $USER;

        // We must make sure that we are not on cachier.
        $url = $PAGE->url;

        $url = $url->out();
        if (strpos($url, 'cashier.php') === false) {
            shopping_cart::buy_for_user(0);
        }

        $tablename = bin2hex(random_bytes(12));

        // It's important to have the baseurl defined, we use it as a return url at one point.
        $baseurl = $PAGE->url ?? new moodle_url('');

        // On the cashier page, we want to buy for different users...
        // ...else we always want to buy for ourselves.
        if (strpos($baseurl->out(), "cashier.php") !== false) {
            $buyforuserid = null;
        } else {
            $buyforuserid = $USER->id;
        }

        $table = new musi_table($tablename);

        $table->define_baseurl($baseurl->out());
        $table->cardsort = true;
        // Without defining sorting won't work!
        $table->define_columns(['titleprefix']);
        return $table;
    }

    /**
     * Add the musi standard filters to the table.
     *
     * @param musi_table $table
     *
     * @return void
     *
     */
    public static function add_standardfilters($table) {
        // Turn on or off.
        if (get_config('local_musi', 'musishortcodesshowfilterbookable')) {
            $callbackfilter = new callback('bookable', get_string('bookable', 'local_musi'));
            $callbackfilter->add_options([
                0 => get_string('notbookable', 'local_musi'),
                1 => get_string('bookable', 'local_musi'),
            ]);
            // This filter expects a record from booking options table.
            // We check if it is bookable for the user.
            $callbackfilter->define_callbackfunction('local_musi\shortcodes::filter_bookable');
            $table->add_filter($callbackfilter);
        }

        $standardfilter = new standardfilter('sport', get_string('sport', 'local_musi'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('sportsdivision', get_string('sportsdivision', 'local_musi'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('teacherobjects', get_string('teachers', 'mod_booking'));
        $standardfilter->add_options(['jsonattribute' => 'name']);
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('dayofweek', get_string('dayofweek', 'local_musi'));
        $standardfilter->add_options([
            'monday' => get_string('monday', 'mod_booking'),
            'tuesday' => get_string('tuesday', 'mod_booking'),
            'wednesday' => get_string('wednesday', 'mod_booking'),
            'thursday' => get_string('thursday', 'mod_booking'),
            'friday' => get_string('friday', 'mod_booking'),
            'saturday' => get_string('saturday', 'mod_booking'),
            'sunday' => get_string('sunday', 'mod_booking'),
        ]);
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('location', get_string('location', 'mod_booking'));
        $table->add_filter($standardfilter);

        $standardfilter = new standardfilter('botags', get_string('tags', 'core'));
        $table->add_filter($standardfilter);

        if (get_config('local_musi', 'musishortcodesshowfiltercoursetime')) {
            $hourlist = new hourlist('coursestarttime', get_string('timeofdaycoursestart', 'local_musi'));
            $table->add_filter($hourlist);
        }

        if (get_config('local_musi', 'musishortcodesshowfilterbookingtime')) {
            $datepicker = new datepicker(
                'bookingopeningtime',
                get_string('timefilter:bookingtime', 'mod_booking'),
                'bookingclosingtime'
            );
            $datepicker->add_options(
                'in between',
                '<',
                get_string('apply_filter', 'local_wunderbyte_table'),
                'now',
                'now + 1 year'
            );
            $table->add_filter($datepicker);
        }
    }

    /**
     * Get booking from shortcode arguments.
     *
     * @param mixed $args
     *
     * @return mixed
     *
     */
    private static function get_booking($args) {
        self::fix_args($args);
        // If the id argument was not passed on, we have a fallback in the connfig.
        if (!isset($args['id'])) {
            $args['id'] = get_config('local_musi', 'shortcodessetinstance');
        }

        // To prevent misconfiguration, id has to be there and int.
        if (!(isset($args['id']) && $args['id'] && is_int((int)$args['id']))) {
            return 'Set id of booking instance';
        }

        if (!$booking = singleton_service::get_instance_of_booking_by_cmid($args['id'])) {
            return 'Couldn\'t find right booking instance ' . $args['id'];
        }

        return $booking;
    }
    /**
     * Set table from shortcodes arguments.
     *
     * @param musi_table $table
     * @param array $args
     *
     * @return void
     *
     */
    private static function set_table_options_from_arguments(&$table, $args) {
        self::fix_args($args);

        /** @var musi_table $table */
        $table->set_display_options($args);
        \mod_booking\shortcodes::set_common_table_options_from_arguments($table, $args);
        self::set_common_table_options_from_arguments($table, $args);
    }

    /**
     * Setting options from shortcodes arguments common for musi_table.
     *
     * @param musi_table $table reference to table
     * @param array $args
     *
     * @return void
     *
     */
    private static function set_common_table_options_from_arguments(&$table, $args) {
        if (!empty($args['filter'])) {
            self::add_standardfilters($table);
        }

        if (!empty($args['search'])) {
            $table->define_fulltextsearchcolumns([
                'titleprefix', 'text', 'sportsdivision', 'sport', 'description', 'location',
                'teacherobjects', 'botags']);
        }

        if (!empty($args['sort'])) {
            $sortablecolumns = [
                'titleprefix' => get_string('titleprefix', 'local_musi'),
                'text' => get_string('coursename', 'local_musi'),
                'sportsdivision' => get_string('sportsdivision', 'local_musi'),
                'sport' => get_string('sport', 'local_musi'),
                'location' => get_string('location', 'local_musi'),
            ];

            if (get_config('local_musi', 'musishortcodesshowsortingfreeplaces')) {
                $standardsortable = new \local_wunderbyte_table\local\sortables\types\standardsortable(
                    'freeplaces',
                    get_string('freeplaces', 'local_musi')
                );
                $select = '(SELECT COALESCE(NULLIF(s1.maxanswers, 0), 999999) - COUNT(ba.id)
                               FROM {booking_answers} ba
                               WHERE ba.optionid = s1.id AND ba.waitinglist < 3) AS freeplaces';
                $from = '';
                $where = '';
                $standardsortable->define_sql($select, $from, $where);

                $standardsortable->define_cache('mod_booking', 'bookedusertable');
                $table->add_sortable($standardsortable);
            }

            if (get_config('local_musi', 'musishortcodesshowstart')) {
                $sortablecolumns['coursestarttime'] = get_string('coursestarttime', 'mod_booking');
            }
            if (get_config('local_musi', 'musishortcodesshowend')) {
                $sortablecolumns['courseendtime'] = get_string('courseendtime', 'mod_booking');
            }
            if (get_config('local_musi', 'musishortcodesshowbookablefrom')) {
                $sortablecolumns['bookingopeningtime'] = get_string('bookingopeningtime', 'mod_booking');
            }
            if (get_config('local_musi', 'musishortcodesshowbookableuntil')) {
                $sortablecolumns['bookingclosingtime'] = get_string('bookingclosingtime', 'mod_booking');
            }
            $table->define_sortablecolumns($sortablecolumns);
        }
        if (isset($args['pageable']) && ($args['pageable'] == 1 || $args['pageable'] == true)) {
            $table->pageable(true);
            $table->stickyheader = true;
        }

        if (!isset($args['pageable']) || $args['pageable'] == 0 || $args['pageable'] == "false" || $args['pageable'] == false) {
            $infinitescrollpage = is_numeric($args['infinitescrollpage'] ?? '') ? (int)$args['infinitescrollpage'] : 30;
            // This allows us to use infinite scrolling, No pages will be used.
            $table->infinitescroll = $infinitescrollpage;
        }
    }
    /**
     * Generates Table for cards
     *
     * @param musi_table $table
     * @param mixed $args
     *
     * @return void
     *
     */
    private static function generate_table_for_cards(&$table, $args) {
        self::fix_args($args);
        $table->define_cache('mod_booking', 'bookingoptionstable');

        // We define it here so we can pass it with the mustache template.
        $table->add_subcolumns('optionid', ['id']);

        $table->add_subcolumns('cardimage', ['image']);
        $table->add_subcolumns('optioninvisible', ['invisibleoption']);

        $table->add_subcolumns('cardbody', ['action', 'invisibleoption', 'sportsdivision', 'sport', 'text', 'botags']);
        $table->add_classes_to_subcolumns('cardbody', ['columnkeyclass' => 'd-none']);
        $table->add_classes_to_subcolumns('cardbody', ['columnvalueclass' => 'float-right m-1'], ['action']);
        $table->add_classes_to_subcolumns('cardbody', ['columnvalueclass' => 'font-size-sm'], ['botags']);
        $table->add_classes_to_subcolumns(
            'cardbody',
            ['columnvalueclass' => 'text-center shortcodes_option_info_invisible'],
            ['invisibleoption']
        );
        $table->add_classes_to_subcolumns('cardbody', ['columnvalueclass' =>
            'sportsdivision-badge'], ['sportsdivision']);
        $table->add_classes_to_subcolumns(
            'cardbody',
            ['columnvalueclass' => 'sport-badge rounded-sm text-gray-800 mt-2'],
            ['sport']
        );
        $table->add_classes_to_subcolumns('cardbody', ['columnvalueclass' => 'm-0 mt-1 mb-1 h5'], ['text']);

        // Subcolumns.
        $subcolumns = ['attachment', 'teacher', 'dayofweektime', 'location', 'institution', 'responsiblecontact'];
        if (get_config('local_musi', 'musishortcodesshowstart')) {
            $subcolumns[] = 'coursestarttime';
        }
        if (get_config('local_musi', 'musishortcodesshowend')) {
            $subcolumns[] = 'courseendtime';
        }
        if (get_config('local_musi', 'musishortcodesshowbookablefrom')) {
            $subcolumns[] = 'bookingopeningtime';
        }
        if (get_config('local_musi', 'musishortcodesshowbookableuntil')) {
            $subcolumns[] = 'bookingclosingtime';
        }
        $subcolumns[] = 'bookings';
        if (!empty($args['showminanswers'])) {
            $subcolumns[] = 'minanswers';
        }
        if (get_config('local_musi', 'musishortcodesshowoptiondates')) {
            $subcolumns[] = 'showdates';
        }
        $table->add_subcolumns('cardlist', $subcolumns);
        $table->add_classes_to_subcolumns('cardlist', ['columnkeyclass' => 'd-none']);
        $table->add_classes_to_subcolumns('cardlist', ['columnvalueclass' => 'text-secondary']);
        $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'text-secondary']);
        $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-map-marker'], ['location']);
        $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-building-o'], ['institution']);
        $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-user'], ['responsiblecontact']);

        if (get_config('local_musi', 'musishortcodesshowstart')) {
            $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-play'], ['coursestarttime']);
        }
        if (get_config('local_musi', 'musishortcodesshowend')) {
            $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-stop'], ['courseendtime']);
        }
        if (get_config('local_musi', 'musishortcodesshowbookablefrom')) {
            $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-forward'], ['bookingopeningtime']);
        }
        if (get_config('local_musi', 'musishortcodesshowbookableuntil')) {
            $table->add_classes_to_subcolumns(
                'cardlist',
                ['columniclassbefore' => 'fa fa-fw fa-step-forward'],
                ['bookingclosingtime']
            );
        }

        $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-clock-o'], ['dayofweektime']);
        $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-users'], ['bookings']);
        if (!empty($args['showminanswers'])) {
            $table->add_classes_to_subcolumns('cardlist', ['columniclassbefore' => 'fa fa-fw fa-arrow-up'], ['minanswers']);
        }

        // Set additional descriptions.
        $table->add_classes_to_subcolumns('cardlist', ['columnalt' => get_string('teacheralt', 'local_musi')], ['teacher']);
        $table->add_classes_to_subcolumns('cardlist', ['columnalt' => get_string('locationalt', 'local_musi')], ['location']);
        $table->add_classes_to_subcolumns('cardlist', ['columnalt' => get_string('dayofweekalt', 'local_musi')], ['dayofweektime']);
        $table->add_classes_to_subcolumns('cardlist', ['columnalt' => get_string('bookingsalt', 'local_musi')], ['bookings']);
        $table->add_classes_to_subcolumns('cardimage', ['cardimagealt' => get_string('imagealt', 'local_musi')], ['image']);

        $table->add_subcolumns('cardfooter', ['course', 'price']);
        $table->add_classes_to_subcolumns('cardfooter', ['columnkeyclass' => 'd-none']);
        $table->add_classes_to_subcolumns('cardfooter', ['columnclass' => 'theme-text-color bold '], ['price']);
        $table->set_tableclass('cardimageclass', 'w-100');

        $table->is_downloading('', 'List of booking options');
        $table->tabletemplate = 'local_musi/table_card';
    }

    /**
     * Generates Tables for List.
     *
     * @param musi_table $table
     * @param mixed $args
     *
     * @return void
     *
     */
    private static function generate_table_for_list(&$table, $args) {

        self::fix_args($args);

        $subcolumnsleftside = ['text'];
        $subcolumnsinfo = ['teacher', 'dayofweektime', 'location', 'institution', 'responsiblecontact'];
        if (get_config('local_musi', 'musishortcodesshowstart')) {
            $subcolumnsinfo[] = 'coursestarttime';
        }
        if (get_config('local_musi', 'musishortcodesshowend')) {
            $subcolumnsinfo[] = 'courseendtime';
        }
        if (get_config('local_musi', 'musishortcodesshowbookablefrom')) {
            $subcolumnsinfo[] = 'bookingopeningtime';
        }
        if (get_config('local_musi', 'musishortcodesshowbookableuntil')) {
            $subcolumnsinfo[] = 'bookingclosingtime';
        }
        $subcolumnsinfo[] = 'bookings';

        // Check if we should add the description.
        if (get_config('local_musi', 'shortcodelists_showdescriptions')) {
            $subcolumnsleftside[] = 'description';
        }
        if (get_config('local_musi', 'musishortcodesshowoptiondates')) {
            $subcolumnsleftside[] = 'showdates';
        }

        // We might need a setting here.
        $subcolumnsleftside[] = 'attachment';

        if (!empty($args['showminanswers'])) {
            $subcolumnsinfo[] = 'minanswers';
        }

        $table->define_cache('mod_booking', 'bookingoptionstable');

        // We define it here so we can pass it with the mustache template.
        $table->add_subcolumns('optionid', ['id']);

        $table->add_subcolumns('top', ['sportsdivision', 'sport', 'action']);
        $table->add_subcolumns('leftside', $subcolumnsleftside);
        $table->add_subcolumns('info', $subcolumnsinfo);

        $table->add_subcolumns('rightside', ['botags', 'invisibleoption', 'course', 'price']);

        $table->add_classes_to_subcolumns('top', ['columnkeyclass' => 'd-none']);
        $table->add_classes_to_subcolumns('top', ['columnclass' => 'text-left col-md-8'], ['sport', 'sportsdivision']);
        $table->add_classes_to_subcolumns('top', ['columnvalueclass' =>
            'sport-badge rounded-sm text-gray-800 mt-2'], ['sport']);
        $table->add_classes_to_subcolumns('top', ['columnvalueclass' =>
            'sportsdivision-badge'], ['sportsdivision']);
        $table->add_classes_to_subcolumns('top', ['columnclass' => 'text-right col-md-2 position-relative pr-0'], ['action']);

        $table->add_classes_to_subcolumns('leftside', ['columnkeyclass' => 'd-none']);
        $table->add_classes_to_subcolumns('leftside', ['columnclass' => 'text-left mt-1 mb-1 h3 col-md-auto'], ['text']);
        if (get_config('local_musi', 'shortcodelists_showdescriptions')) {
            $table->add_classes_to_subcolumns('leftside', ['columnclass' => 'text-left mt-1 mb-3 col-md-auto'], ['description']);
        }
        $table->add_classes_to_subcolumns('info', ['columnkeyclass' => 'd-none']);
        $table->add_classes_to_subcolumns('info', ['columnclass' => 'text-left text-secondary font-size-sm pr-2']);
        $table->add_classes_to_subcolumns('info', ['columnvalueclass' => 'd-flex'], ['teacher']);
        $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-clock-o'], ['dayofweektime']);
        $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-map-marker'], ['location']);
        $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-building-o'], ['institution']);
        $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-user'], ['responsiblecontact']);
        if (get_config('local_musi', 'musishortcodesshowstart')) {
            $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-play'], ['coursestarttime']);
        }
        if (get_config('local_musi', 'musishortcodesshowend')) {
            $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-stop'], ['courseendtime']);
        }
        if (get_config('local_musi', 'musishortcodesshowbookablefrom')) {
            $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-forward'], ['bookingopeningtime']);
        }
        if (get_config('local_musi', 'musishortcodesshowbookableuntil')) {
            $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-step-forward'], ['bookingclosingtime']);
        }
        $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-ticket'], ['bookings']);
        if (!empty($args['showminanswers'])) {
            $table->add_classes_to_subcolumns('info', ['columniclassbefore' => 'fa fa-arrow-up'], ['minanswers']);
        }

        // Set additional descriptions.
        $table->add_classes_to_subcolumns('info', ['columnalt' => get_string('teacheralt', 'local_musi')], ['teacher']);
        $table->add_classes_to_subcolumns('info', ['columnalt' => get_string('dayofweekalt', 'local_musi')], ['dayofweektime']);
        $table->add_classes_to_subcolumns('info', ['columnalt' => get_string('locationalt', 'local_musi')], ['location']);
        $table->add_classes_to_subcolumns('info', ['columnalt' => get_string('bookingsalt', 'local_musi')], ['bookings']);

        $table->add_classes_to_subcolumns(
            'rightside',
            ['columnvalueclass' => 'text-right mb-auto align-self-end shortcodes_option_info_invisible '],
            ['invisibleoption']
        );
        $table->add_classes_to_subcolumns('rightside', ['columnclass' => 'text-right mb-auto align-self-end '], ['botags']);
        $table->add_classes_to_subcolumns('rightside', ['columnclass' =>
            'text-right mt-auto w-100 align-self-end theme-text-color bold '], ['price']);

        // Override naming for columns. one could use getstring for localisation here.
        $table->add_classes_to_subcolumns(
            'top',
            ['keystring' => get_string('tableheadertext', 'booking')],
            ['sport']
        );
        $table->add_classes_to_subcolumns(
            'leftside',
            ['keystring' => get_string('tableheadertext', 'booking')],
            ['text']
        );
        $table->add_classes_to_subcolumns(
            'info',
            ['keystring' => get_string('tableheadermaxanswers', 'booking')],
            ['maxanswers']
        );
        $table->add_classes_to_subcolumns(
            'info',
            ['keystring' => get_string('tableheadermaxoverbooking', 'booking')],
            ['maxoverbooking']
        );

        $table->is_downloading('', 'List of booking options');
        $table->tabletemplate = 'local_musi/table_list';
    }

    /**
     * Prints a link to subscribe to the newsletter
     * Supported $args: 'button' => if true, the link will be shown as button.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function newslettersubscribe($shortcode, $args, $content, $env, $next) {

        self::fix_args($args);

        $moodleurl = new moodle_url('/local/musi/newsletter.php?action=subscribe');
        $link = $moodleurl->out(false);
        $renderedlink = html_writer::link($link, get_string('newslettersubscribed:title', 'local_musi'), ['target' => '_blank']);
        if (isset($args['button']) && ($args['button'] == 1 || $args['button'] == true || $args['button'] == "true")) {
            $renderedlink = html_writer::link($link, get_string('newslettersubscribed:title', 'local_musi'), [
                'class' => 'btn btn-sm btn-primary',
                'target' => '_blank',
            ]);
        }

        return $renderedlink;
    }

    /**
     * Prints a link to unsubscribe from the newsletter
     * Supported $args: 'button' => if true, the link will be shown as button.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function newsletterunsubscribe($shortcode, $args, $content, $env, $next) {

        self::fix_args($args);

        $moodleurl = new moodle_url('/local/musi/newsletter.php?action=unsubscribe');
        $link = $moodleurl->out(false);
        $renderedlink = html_writer::link($link, get_string('newsletterunsubscribed:title', 'local_musi'), ['target' => '_blank']);
        if (isset($args['button']) && ($args['button'] == 1 || $args['button'] == true || $args['button'] == "true")) {
            $renderedlink = html_writer::link($link, get_string('newsletterunsubscribed:title', 'local_musi'), [
                'class' => 'btn btn-sm btn-danger',
                'target' => '_blank',
            ]);
        }

        return $renderedlink;
    }

    /**
     * Helper function to remove quotation marks from args.
     * @param array &$args reference to arguments array
     */
    private static function fix_args(array &$args) {
        foreach ($args as $key => &$value) {
            // Get rid of quotation marks.
            $value = str_replace('"', '', $value);
            $value = str_replace("'", "", $value);
        }
    }

    /**
     * Modify wherearray and additionalwhere via arguments.
     *
     * @param array $args
     * @param array $wherearray
     * @param string $additionalwhere
     *
     * @return void
     */
    private static function set_wherearray_from_arguments(array &$args, array &$wherearray, string &$additionalwhere = '') {
        // This is special treatment of sport.
        if (!empty($args['category'])) {
            $wherearray['sport'] = $args['category'];
            unset($args['category']);
        };

        $customfields = booking_handler::get_customfields();
        // Set given customfields (shortnames) as arguments.
        $fields = [];
        if (!empty($customfields) && !empty($args)) {
            foreach ($args as $key => $value) {
                foreach ($customfields as $customfield) {
                    if ($customfield->shortname == $key) {
                        $fields[$key] = $value;
                        break;
                    }
                }
            }
        }
        if (!empty($fields)) {
            // This should now support multiple customfields.
            $additionalwheres = [];
            foreach ($fields as $customfield => $value) {
                $value = strip_tags(trim($value));
                // We have to check for multiple values, separated by comma.
                $values = explode(',', $value);
                foreach ($values as $value) {
                    $additionalwheres[] = "($customfield = '$value'
                                OR $customfield LIKE '$value,%'
                                OR $customfield LIKE '%,$value'
                                OR $customfield LIKE '%,$value,%')";
                }
            }
            if (!empty($additionalwheres)) {
                if (!empty($additionalwhere)) {
                    $additionalwhere .= " AND ";
                }
                $additionalwhere .= " (" . implode(' OR ', $additionalwheres) . ")";
            }
        }
    }
    /**
     * Static function to be called by the callback filter.
     *
     * @param mixed $record
     *
     * @return int
     *
     */
    public static function filter_bookable($record) {
        $userid = shopping_cart::return_buy_for_userid();
        $settings = singleton_service::get_instance_of_booking_option_settings($record->id);
            $boinfo = new bo_info($settings);
            // We only filter on the hard blocking options.
            [$id] = $boinfo->is_available($settings->id, $userid, true);
            return in_array(
                $id,
                [
                    MOD_BOOKING_BO_COND_BOOKITBUTTON,
                    MOD_BOOKING_BO_COND_CONFIRMBOOKIT,
                    MOD_BOOKING_BO_COND_PRICEISSET,
                    MOD_BOOKING_BO_COND_CONFIRMATION,
                    MOD_BOOKING_BO_COND_BOOKWITHCREDITS,
                    MOD_BOOKING_BO_COND_CONFIRMBOOKWITHCREDITS,
                    MOD_BOOKING_BO_COND_CONFIRMBOOKWITHSUBSCRIPTION,
                ]
            ) ? 1 : 0;
    }
    /**
     * Helperfunction to get SQL Params.
     *
     * @param mixed $args
     * @param mixed $booking
     * @param mixed $wherearray
     * @param mixed $additionalwhere
     * @param int $userid
     *
     * @return array
     *
     */
    private static function get_sql_params($booking, $wherearray, $additionalwhere, $userid = null) {

        return  [$fields, $from, $where, $params, $filter] =
                    booking::get_options_filter_sql(
                        0,
                        0,
                        '',
                        null,
                        $booking->context,
                        [],
                        $wherearray,
                        $userid,
                        [MOD_BOOKING_STATUSPARAM_BOOKED],
                        $additionalwhere
                    );
    }

    /**
     * Helperfunction to generate output
     *
     * @param mixed $args
     * @param musi_table $table
     * @param int $perpage
     *
     * @return string
     *
     */
    private static function generate_output($args, $table, $perpage) {
        if (!empty($args['lazy'])) {
            [$idstring, $encodedtable, $out] = $table->lazyouthtml($perpage, true);
            return $out;
        }
        return $table->outhtml($perpage, true);
    }
}
