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

namespace local_musi\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir.'/tablelib.php');

use coding_exception;
use context_module;
use dml_exception;
use html_writer;
use local_wunderbyte_table\wunderbyte_table;
use mod_booking\bo_availability\bo_info;
use mod_booking\booking;
use mod_booking\booking_bookit;
use mod_booking\booking_option;
use mod_booking\option\dates_handler;
use mod_booking\output\col_availableplaces;
use mod_booking\output\col_teacher;
use mod_booking\price;
use mod_booking\singleton_service;
use local_entities\entity;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Search results for managers are shown in a table (student search results use the template searchresults_student).
 */
class musi_table extends wunderbyte_table {

    const SHOW_PARENT_THAN_ENTITY = 'parent_entity';
    const SHOW_ENTITY_THAN_PARENT = 'entity_parent';
    const SHOW_ONLY_ENTITY = 'entity';
    const SHOW_ONLY_PARENT = 'parent';

    /** @var booking $booking */
    private $booking = null;

    /** @var stdClass $buyforuser */
    private $buyforuser = null;

    /** @var context_module $buyforuser */
    private $context = null;

    /** @var array $displayoptions */
    private $displayoptions = [];

    /**
     * Constructor
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param booking $booking the booking instance
     * @param int $buyforuserid optional id of the user to buy for
     */
    public function __construct(string $uniqueid, booking $booking = null, int $buyforuserid = null) {
        parent::__construct($uniqueid);

        global $PAGE;

        if ($booking) {
            $this->booking = $booking;
        }

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* $this->outputbooking = $PAGE->get_renderer('mod_booking');
        $this->outputmusi = $PAGE->get_renderer('local_musi'); */

        // We set buyforuser here for better performance.
        if (empty($buyforuserid)) {
            $this->buyforuser = price::return_user_to_buy_for();
        } else {
            $this->buyforuser = singleton_service::get_instance_of_user($buyforuserid);
        }

        // Columns and headers are not defined in constructor, in order to keep things as generic as possible.
    }

    public function set_display_options($displayoptions) {

        // Units, e.g. "(UE: 1,3)".
        if (isset($displayoptions['showunits'])) { // Do not use empty here!!
            $this->displayoptions['showunits'] = (bool) $displayoptions['showunits'];
            // We need this for mustache tow work.
            if (!$this->displayoptions['showunits']) {
                unset($this->displayoptions['showunits']);
            }
        }

        // Max. answers.
        if (!isset($displayoptions['showmaxanwers'])) { // Do not use empty here!!
            $this->displayoptions['showmaxanwers'] = true; // Max. answers are shown by default.
        } else {
            $this->displayoptions['showmaxanwers'] = (bool) $displayoptions['showmaxanwers'];
            // We need this for mustache tow work.
            if (!$this->displayoptions['showmaxanwers']) {
                unset($this->displayoptions['showmaxanwers']);
            }
        }
    }

    /**
     * This function is called for each data row to allow processing of the
     * invisible value. It's called 'invisibleoption' so it does not interfere with
     * the bootstrap class 'invisible'.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $invisible Returns visibility of the booking option as string.
     * @throws coding_exception
     */
    public function col_invisibleoption($values) {

        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        if (!empty($settings->invisible)) {
            return get_string('invisibleoption', 'local_musi');
        } else {
            return '';
        }
    }

    public function col_image($values) {

        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        if (empty($settings->imageurl)) {
            return null;
        }

        return $settings->imageurl;
    }

    /**
     * This function is called for each data row to allow processing of the
     * teacher value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $string Return name of the booking option.
     * @throws dml_exception
     */
    public function col_teacher($values) {

        // Render col_teacher using a template.
        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);
        $data = new col_teacher($values->id, $settings);

        $numitems = count($data->teachers);
        $i = 0;
        foreach ($data->teachers as $key => &$value) {
            if (++$i === $numitems) {
                $value['last'] = true;
            } else {
                $value['last'] = false;
            }
        }
        $output = singleton_service::get_renderer('local_musi');
        return $output->render_col_teacher($data);;
    }

    /**
     * This function is called for each data row to allow processing of the
     * price value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $string Return name of the booking option.
     * @throws dml_exception
     */
    public function col_price($values) {
        // Render col_price using a template.
        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        return booking_bookit::render_bookit_button($settings, $this->buyforuser->id);
    }

    /**
     * This function is called for each data row to allow processing of the
     * text value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $string Return name of the booking option.
     * @throws dml_exception
     */
    public function col_text($values) {

        if (!$this->booking) {
            $this->booking = singleton_service::get_instance_of_booking_by_bookingid($values->bookingid);
        }

        if ($this->booking) {
            $url = new moodle_url('/mod/booking/optionview.php', ['optionid' => $values->id,
                                                                  'cmid' => $this->booking->cmid,
                                                                  'userid' => $this->buyforuser->id]);
        } else {
            $url = '#';
        }

        $title = $values->text;
        if (!empty($values->titleprefix)) {
            $title = $values->titleprefix . ' - ' . $values->text;
        }

        if (!$this->is_downloading()) {
            $title = "<div class='musi-table-option-title'><a href='$url' target='_blank'>$title</a></div>";
        }

        return $title;
    }


    /**
     * This function is called for each data row to allow processing of the
     * booking value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $coursestarttime Returns course start time as a readable string.
     * @throws coding_exception
     */
    public function col_bookings($values) {

        global $PAGE;

        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);
        // Render col_bookings using a template.
        $data = new col_availableplaces($values, $settings, $this->buyforuser);
        if (!empty($this->displayoptions['showmaxanwers'])) {
            $data->showmaxanswers = $this->displayoptions['showmaxanwers'];
        }
        $output = singleton_service::get_renderer('local_musi');
        return $output->render_col_availableplaces($data);
    }

    /**
     * This function is called for each data row to allow processing of the
     * minanswers value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string a string containing the minanswers description and value
     * @throws coding_exception
     */
    public function col_minanswers($values) {
        $ret = null;
        if (!empty($values->minanswers)) {
            $ret = get_string('minanswers', 'mod_booking') . ": $values->minanswers";
        }
        return $ret;
    }

    /**
     * This function is called for each data row to allow processing of the
     * location value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string location
     * @throws coding_exception
     */
    public function col_location($values) {
        return $this->get_location_string($values,self::SHOW_ONLY_ENTITY);
    }

    public function col_location_parent($values) {
        return $this->get_location_string($values,self::SHOW_ONLY_PARENT);
    }
    public function col_location_parent_than_entity($values) {
        return $this->get_location_string($values,self::SHOW_PARENT_THAN_ENTITY);
    }

    public function col_location_entity_than_parent($values) {
      return $this->get_location_string($values,self::SHOW_ENTITY_THAN_PARENT);
    }
    private function get_location_string($values, $type){
        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        if (isset($settings->entity) && (count($settings->entity) > 0)) {

            $url = new moodle_url('/local/entities/view.php', ['id' => $settings->entity['id']]);
            $entityname =  $settings->entity['name'];

           if($type == self::SHOW_ONLY_ENTITY){
               return html_writer::tag('a', $entityname, ['href' => $url->out(false)]);
           }else{
               $entity = entity::load($settings->entity['id']);
               if($entity->parentid != 0){
                   $parententity = entity::load($entity->parentid);

                   if(isset($parententity)){
                   $parententityname =  $parententity->name;

                   switch ($type){
                       case self::SHOW_ONLY_PARENT:
                           return html_writer::tag('a', $parententityname, ['href' => $url->out(false)]);
                       case self::SHOW_PARENT_THAN_ENTITY:

                           return html_writer::tag('a', $parententityname.'<span class="musi-location-additional"> ('.$entityname.')</span>', ['href' => $url->out(false)]);
                       case self::SHOW_ENTITY_THAN_PARENT:
                           return html_writer::tag('a', $entityname.'<span class="musi-location-additional"> ('.$parententityname.')</span>', ['href' => $url->out(false)]);;
                       default:
                           return html_writer::tag('a', $entityname, ['href' => $url->out(false)]);
                   }
                   }
               }
                   return html_writer::tag('a', $entityname, ['href' => $url->out(false)]);
           }

        }
        return $settings->location;
    }

    /**
     * This function is called for each data row to allow processing of the
     * sports value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $sports Returns rendered sport.
     * @throws coding_exception
     */
    public function col_sport($values) {

        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        if (isset($settings->customfields) && isset($settings->customfields['sport'])) {
            if (is_array($settings->customfields['sport'])) {
                return implode(", ", $settings->customfields['sport']);
            } else {
                return $settings->customfields['sport'];
            }
        }

        // Normally we won't arrive here, but if so, we want to show a meaningful error message.
        if (!$this->context) {
            $this->context = context_module::instance($settings->cmid);
        }

        // The error message should only be shown to admins.
        if (has_capability('moodle/site:config', $this->context)) {

            $message = get_string('youneedcustomfieldsport', 'local_musi');

            $message = "<div class='alert alert-danger'>$message</div>";

            return $message;
        }

        // Normal users won't notice the problem.
        return '';
    }

    /**
     * This function is called for each data row to allow processing of the
     * booking option tags (botags).
     *
     * @param object $values Contains object with all the values of record.
     * @return string $sports Returns course start time as a readable string.
     * @throws coding_exception
     */
    public function col_botags($values) {

        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        $botagsstring = '';

        if (isset($settings->customfields) && isset($settings->customfields['botags'])) {
            $botagsarray = $settings->customfields['botags'];
            if (!empty($botagsarray)) {
                foreach ($botagsarray as $botag) {
                    if (!empty($botag)) {
                        $botagsstring .=
                            "<span class='musi-table-botag rounded-sm bg-info text-light pl-1 pr-1 pb-0 pt-0 mr-1'>
                            $botag
                            </span>";
                    } else {
                        continue;
                    }
                }
                if (!empty($botagsstring)) {
                    return $botagsstring;
                } else {
                    return '';
                }
            }
        }
        return '';
    }

    /**
     * This function is called for each data row to allow processing of the
     * associated Moodle course.
     *
     * @param object $values Contains object with all the values of record.
     * @return string a link to the Moodle course - if there is one
     * @throws coding_exception
     */
    public function col_course($values) {
        global $USER;

        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);
        $ret = '';

        $moodleurl = new moodle_url('/course/view.php', ['id' => $settings->courseid]);
        $courseurl = $moodleurl->out(false);
        // If we download, we want to return the plain URL.
        if ($this->is_downloading()) {
            return $courseurl;
        }

        $answersobject = singleton_service::get_instance_of_booking_answers($settings);
        $status = $answersobject->user_status($USER->id);

        $isteacherofthisoption = booking_check_if_teacher($values);

        if (!empty($settings->courseid) && (
                $status == STATUSPARAM_BOOKED ||
                has_capability('mod/booking:updatebooking', $this->context) ||
                (has_capability('mod/booking:addeditownoption', $this->context) && $isteacherofthisoption) ||
                (has_capability('mod/booking:limitededitownoption', $this->context) && $isteacherofthisoption)
        )) {
            $gotomoodlecourse = get_string('tocoursecontent', 'local_musi');
            $ret = "<a href='$courseurl' target='_self' class='btn btn-primary mt-2 mb-2 w-100'>
                <i class='fa fa-graduation-cap fa-fw' aria-hidden='true'></i>&nbsp;&nbsp;$gotomoodlecourse
            </a>";
        }

        return $ret;
    }

    /**
     * This function is called for each data row to allow processing of the
     * dayofweektime value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $dayofweektime String for date series, e.g. "Mon, 16:00 - 17:00"
     * @throws coding_exception
     */
    public function col_dayofweektime($values) {

        $ret = '';
        $settings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);

        $units = null;

        if (!empty($settings->dayofweektime)) {
            $localweekdays = dates_handler::get_localized_weekdays(current_language());
            $dayinfo = dates_handler::prepare_day_info($settings->dayofweektime);
            if (isset($dayinfo['day']) && $dayinfo['starttime'] && $dayinfo['endtime']) {
                $ret = $localweekdays[$dayinfo['day']] . ', '.$dayinfo['starttime'] . ' -- ' . $dayinfo['endtime'];
            } else if (!empty($settings->dayofweektime)) {
                $ret = $settings->dayofweektime;
            } else {
                $ret = get_string('datenotset', 'mod_booking');
            }
            if (!$this->is_downloading() &&  !empty($this->displayoptions['showunits'])) {
                $units = dates_handler::calculate_and_render_educational_units($settings->dayofweektime);
                $ret .= " ($units)";
            }
        }

        return $ret;
    }

    /**
     * This function is called for each data row to allow processing of the
     * courseendtime value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $courseendtime Returns course end time as a readable string.
     * @throws coding_exception
     */
    public function col_coursedates($values) {

        // Prepare date string.
        if ($values->coursestarttime != 0) {
            $returnarray[] = userdate($values->coursestarttime, get_string('strftimedatetime'));
        }

        // Prepare date string.
        if ($values->courseendtime != 0) {
            $returnarray[] = userdate($values->courseendtime, get_string('strftimedatetime'));
        }

        return implode(' - ', $returnarray);
    }

    /**
     * This function is called for each data row to add a link
     * for managing responses (booking_answers).
     *
     * @param object $values Contains object with all the values of record.
     * @return string $link Returns a link to report.php (manage responses).
     * @throws moodle_exception
     * @throws coding_exception
     */
    public function col_manageresponses($values) {
        global $CFG, $DB;

        // Link is empty on default.
        $link = '';

        $settings = singleton_service::get_instance_of_booking_option_settings($values->optionid, $values);
        $bookinganswers = singleton_service::get_instance_of_booking_answers($settings, 0);

        if (count($bookinganswers->usersonlist) > 0) {
            // Add a link to redirect to the booking option.
            $link = new moodle_url($CFG->wwwroot . '/mod/booking/report.php', array(
                'id' => $values->cmid,
                'optionid' => $values->optionid
            ));
            // Use html_entity_decode to convert "&amp;" to a simple "&" character.
            if ($CFG->version >= 2023042400) {
                // Moodle 4.2 needs second param.
                $link = html_entity_decode($link->out(), ENT_QUOTES);
            } else {
                // Moodle 4.1 and older.
                $link = html_entity_decode($link->out(), ENT_COMPAT);
            }

            if (!$this->is_downloading()) {
                // Only format as a button if it's not an export.
                $link = '<a href="' . $link . '" class="btn btn-secondary">'
                    . get_string('bstmanageresponses', 'mod_booking')
                    . '</a>';
            }
        }
        // Do not show a link if there are no answers.

        return $link;
    }

    /**
     * This function is called for each data row to allow processing of the
     * action button.
     *
     * @param object $values Contains object with all the values of record.
     * @return string $action Returns formatted action button.
     * @throws moodle_exception
     * @throws coding_exception
     */
    public function col_action($values) {

        if (!$this->booking) {
            $this->booking = singleton_service::get_instance_of_booking_by_bookingid($values->bookingid, $values);
        }

        $data = new stdClass();

        $data->optionid = $values->id;
        $data->componentname = 'mod_booking';

        if ($this->booking) {
            $data->cmid = $this->booking->cmid;
        }

        // We will have a number of modals on this site, therefore we have to distinguish them.
        // This is in case we render modal.
        $data->modalcounter = $values->id;
        $data->modaltitle = $values->text;
        $data->userid = $this->buyforuser->id;

        // Get the URL to edit the option.
        if (!empty($values->id)) {
            $bosettings = singleton_service::get_instance_of_booking_option_settings($values->id, $values);
            if (!empty($bosettings)) {

                if (!$this->context) {
                    $this->context = context_module::instance($bosettings->cmid);
                }

                // ONLY users with the mod/booking:updatebooking capability can edit options.
                $allowedit = has_capability('mod/booking:updatebooking', $this->context);
                if ($allowedit) {
                    if (isset($bosettings->editoptionurl)) {
                        // Get the URL to edit the option.
                        $data->editoptionurl = $this->add_return_url($bosettings->editoptionurl);
                    }
                }

                // Send e-mail to all booked users menu entry.
                $allowsendmailtoallbookedusers = (
                    get_config('booking', 'teachersallowmailtobookedusers') && (
                        has_capability('mod/booking:updatebooking', $this->context) ||
                        (has_capability('mod/booking:addeditownoption', $this->context) && booking_check_if_teacher($values)) ||
                        (has_capability('mod/booking:limitededitownoption', $this->context) && booking_check_if_teacher($values))
                    )
                );
                if ($allowsendmailtoallbookedusers) {
                    $mailtolink = booking_option::get_mailto_link_for_partipants($values->id);
                    if (!empty($mailtolink)) {
                        $data->sendmailtoallbookedusers = true;
                        $data->mailtobookeduserslink = $mailtolink;
                    }
                }

                // The simplified availability menu.
                $alloweditavailability = (
                    // Admin capability.
                    has_capability('mod/booking:updatebooking', $this->context) ||
                    // Or: Everyone with the M:USI editavailability capability.
                    has_capability('local/musi:editavailability', $this->context) ||
                    // Or: Teachers can edit the availability of their own option.
                    (has_capability('mod/booking:addeditownoption', $this->context) && booking_check_if_teacher($values)) ||
                    (has_capability('mod/booking:limitededitownoption', $this->context) && booking_check_if_teacher($values))
                );
                if ($alloweditavailability) {
                    $data->editavailability = true;
                }

                $canviewreports = (
                    has_capability('mod/booking:viewreports', $this->context)
                    || (has_capability('mod/booking:limitededitownoption', $this->context) && booking_check_if_teacher($values))
                    || has_capability('mod/booking:updatebooking', $this->context)
                );

                // If the user has no capability to editoptions, the URLs will not be added.
                if ($canviewreports) {

                    if (isset($bosettings->manageresponsesurl)) {
                        // Get the URL to manage responses (answers) for the option.
                        $data->manageresponsesurl = $bosettings->manageresponsesurl;
                    }

                    if (isset($bosettings->optiondatesteachersurl)) {
                        // Get the URL for the optiondates-teachers-report.
                        $data->optiondatesteachersurl = $bosettings->optiondatesteachersurl;
                    }
                }
            }
        }

        if (has_capability('local/shopping_cart:cashier', $this->context)) {
            // If booking option is already cancelled, we want to show the "undo cancel" button instead.
            if ($values->status == 1) {
                $data->showundocancel = true;
                $data->undocancellink = html_writer::link('#',
                '<i class="fa fa-undo fa-fw" aria-hidden="true"></i> ' .
                    get_string('undocancelthisbookingoption', 'mod_booking'),
                    [
                        'class' => 'dropdown-item undocancelallusers',
                        'data-id' => $values->id,
                        'data-componentname' => 'mod_booking',
                        'data-area' => 'option',
                        'onclick' =>
                            "require(['mod_booking/confirm_cancel'], function(init) {
                                init.init('" . $values->id . "', '" . $values->status . "');
                            });"
                    ]);
            } else {
                // Else we show the default cancel button.
                // We do NOT set $data->undocancel here.
                $data->showcancel = true;
                $data->cancellink = html_writer::link('#',
                '<i class="fa fa-ban fa-fw" aria-hidden="true"></i> ' .
                    get_string('cancelallusers', 'mod_booking'),
                    [
                        'class' => 'dropdown-item cancelallusers',
                        'data-id' => $values->id,
                        'data-componentname' => 'mod_booking',
                        'data-area' => 'option',
                        'onclick' =>
                            "require(['local_shopping_cart/menu'], function(menu) {
                                menu.confirmCancelAllUsersAndSetCreditModal('" . $values->id . "', 'mod_booking', 'option');
                            });"
                    ]);
            }
        } else {
            $data->showcancel = null;
            $data->showundocancel = null;
        }

        $output = singleton_service::get_renderer('local_musi');
        return $output->render_musi_bookingoption_menu($data);
    }

    /**
     * Override wunderbyte_table function and use own renderer.
     *
     * @return void
     */
    public function finish_html() {

        global $PAGE;

        $table = new \local_wunderbyte_table\output\table($this);
        $output = singleton_service::get_renderer('mod_booking');
        echo $output->render_bookingoptions_wbtable($table);
    }

    private function add_return_url(string $urlstring):string {

        $returnurl = $this->baseurl->out();

        $urlcomponents = parse_url($urlstring);

        parse_str($urlcomponents['query'], $params);

        $url = new moodle_url(
            $urlcomponents['path'],
            array_merge(
                $params, [
                'returnto' => 'url',
                'returnurl' => $returnurl
                ]
            )
        );

        return $url->out(false);
    }
}
