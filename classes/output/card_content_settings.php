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
 * This file contains the definition for the renderable classes for the booking instance
 *
 * @package   local_musi
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi\output;

use html_writer;
use mod_booking\singleton_service;
use moodle_url;
use renderer_base;
use renderable;
use stdClass;
use templatable;

/**
 * This class prepares data for displaying a booking option instance
 *
 * @package local_musi
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_content_settings implements renderable, templatable {

    /** @var stdClass $title */
    public $data = null;

    /**
     * In the Constructor, we gather all the data we need ans store it in the data property.
     */
    public function __construct() {

        $this->data = self::return_content();
    }

    /**
     * Generate the stats for this website
     *
     * @return stdClass
     */
    private static function return_content() {
        global $DB;

        $data = new stdClass();

        $cmid = get_config('local_musi', 'shortcodessetinstance');

        if ($cmid) {
            $url = new moodle_url('/course/modedit.php', ['update' => $cmid]);
            $data->editbookinginstance = ['link' => $url->out(false)];

            $url = new moodle_url('/mod/booking/view.php', ['id' => $cmid, 'whichview' => 'showall']);
            $data->editbookings = ['link' => $url->out(false)];

        } else {
            $url = new moodle_url('/admin/category.php?category=local_musi');
            $data->addbookinginstance = ['link' => $url->out(false)];
        }

        $url = new moodle_url('/mod/booking/teachers.php');
        $data->viewteachers = ['link' => $url->out(false)];

        $url = new moodle_url('/mod/booking/teachers_instance_report.php', ['cmid' => $cmid]);
        $data->teachersinstancereport = ['link' => $url->out(false)];

        $url = new moodle_url('/mod/booking/pricecategories.php');
        $data->editpricecategories = ['link' => $url->out(false)];

        $url = new moodle_url('/mod/booking/semesters.php');
        $data->editsemesters = ['link' => $url->out(false)];

        $url = new moodle_url('/admin/category.php?category=local_musi');
        $data->changebookinginstance = ['link' => $url->out(false)];

        $url = new moodle_url('#');
        $data->editbotags = ['link' => $url->out(false), 'data-action' => 'openbotagsmodal'];

        $url = new moodle_url('/local/musi/viewsapdailysums.php');
        $data->sapdailysums = ['link' => $url->out(false)];

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* $url = new moodle_url('/admin/category.php?category=local_musi');
        $data->editcontractformula = ['link' => $url->out(false)]; */

        $url = new moodle_url('/local/musi/transactionlist.php');
        $data->transactionslist = ['link' => $url->out(false)];

        $url = new moodle_url('/mod/booking/edit_campaigns.php');
        $data->campaigns = ['link' => $url->out(false)];

        return $data;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        // We transform the data object to an array where we can read key & value.
        foreach ($this->data as $key => $value) {

            $item = [
                'key' => get_string($key, 'local_musi')
            ];

            // We only have value & link at the time as types, but might have more at one point.
            foreach ($value as $type => $name) {
                $item[$type] = $name;
            }

            $returnarray['item'][] = $item;
        }

        return $returnarray;
    }
}
