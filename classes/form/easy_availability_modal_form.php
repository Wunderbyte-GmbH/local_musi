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

namespace local_musi\form;

use context_module;
use context;
use mod_booking\booking_option;
use mod_booking\option\fields_info;
use mod_booking\output\eventslist;
use mod_booking\singleton_service;
use moodle_exception;
use stdClass;

/**
 * Modal form to allow simplified access to availability conditions for M:USI.
 *
 * @package     local_musi
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class easy_availability_modal_form extends \core_form\dynamic_form {

    /** @var bool $formmode 'simple' or 'expert' */
    public $formmode = null;

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        global $DB, $OUTPUT;

        /* At first get the option form configuration from DB.
        Unfortunately, we need this, because hideIf does not work with
        editors, headers and html elements. */
        $optionformconfig = [];
        if ($optionformconfigrecords = $DB->get_records('booking_optionformconfig')) {
            foreach ($optionformconfigrecords as $optionformconfigrecord) {
                $optionformconfig[$optionformconfigrecord->elementname] = $optionformconfigrecord->active;
            }
        }

        $formdata = $this->_customdata ?? $this->_ajaxformdata;

        // We need context on this.
        $context = $this->get_context_for_dynamic_submission();
        $formdata['context'] = $context;
        $optionid = $formdata['id'] ?? $formdata['optionid'] ?? 0;

        // Get the form mode, which can be 'simple' or 'expert'.
        if (isset($formdata['formmode'])) {
            // Formmode can also be set via custom data.
            // Currently we only need this for the optionformconfig...
            // ...which needs to be set to 'expert', so it shows all checkboxes.
            $this->formmode = $formdata['formmode'];
        } else {
            // Normal case: we get formmode from user preferences.
            $this->formmode = get_user_preferences('optionform_mode');
        }

        if (empty($this->formmode)) {
            // Default: Simple mode.
            $this->formmode = 'simple';
        }

        // We add the formmode to the optionformconfig.
        $optionformconfig['formmode'] = $this->formmode;

        $mform = &$this->_form;

        $mform->addElement('hidden', 'scrollpos');
        $mform->setType('scrollpos', PARAM_INT);

        // Add all available fields in the right order.
        fields_info::instance_form_definition($mform, $formdata, $optionformconfig);
    }

    /**
     * Process dynamic submission.
     * @return stdClass|null
     */
    public function process_dynamic_submission() {

        // Get data from form.
        $data = $this->get_data();

        // Pass data to update.
        $context = $this->get_context_for_dynamic_submission();

        $result = booking_option::update($data, $context);

        return $data;
    }

    /**
     * Set data for dynamic submission.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {

        $data = (object)$this->_ajaxformdata ?? $this->_customdata;

        $data->id = $this->_ajaxformdata['optionid'] ?? $this->_ajaxformdata['id'] ?? 0;

        fields_info::set_data($data);

        $this->set_data($data);
    }

    /**
     * Data preprocessing.
     *
     * @param array $defaultvalues
     *
     * @return void
     *
     */
    protected function data_preprocessing(&$defaultvalues) {

        // Custom lang strings.
        if (!isset($defaultvalues['descriptionformat'])) {
            $defaultvalues['descriptionformat'] = FORMAT_HTML;
        }

        if (!isset($defaultvalues['description'])) {
            $defaultvalues['description'] = '';
        }

        if (!isset($defaultvalues['notificationtextformat'])) {
            $defaultvalues['notificationtextformat'] = FORMAT_HTML;
        }

        if (!isset($defaultvalues['notificationtext'])) {
            $defaultvalues['notificationtext'] = '';
        }

        if (!isset($defaultvalues['beforebookedtext'])) {
            $defaultvalues['beforebookedtext'] = '';
        }

        if (!isset($defaultvalues['beforecompletedtext'])) {
            $defaultvalues['beforecompletedtext'] = '';
        }

        if (!isset($defaultvalues['aftercompletedtext'])) {
            $defaultvalues['aftercompletedtext'] = '';
        }
    }

    /**
     * Definition after data.
     * @return void
     * @throws coding_exception
     */
    public function definition_after_data() {

        $mform = $this->_form;
        $formdata = $this->_customdata ?? $this->_ajaxformdata;

        fields_info::definition_after_data($mform, $formdata);
    }

    /**
     * Get context for dynamic submission.
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {

        $settings = singleton_service::get_instance_of_booking_option_settings($this->_ajaxformdata['optionid']);
        return context_module::instance($settings->cmid);
    }

    /**
     * Check access for dynamic submission.
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {

        $context = $this->get_context_for_dynamic_submission();
        $optionid = $this->_ajaxformdata['optionid'] ?? $this->_ajaxformdata['id'] ?? 0;

        // The simplified availability menu.
        $alloweditavailability = (
            has_capability('local/musi:editavailability', $context) &&
            (has_capability('mod/booking:updatebooking', $context) ||
            (has_capability('mod/booking:limitededitownoption', $context) && $this->check_if_teacher($optionid)) ||
            (has_capability('mod/booking:addeditownoption', $context) && $this->check_if_teacher($optionid)))
        );
        if (!$alloweditavailability) {
            throw new moodle_exception('norighttoaccess', 'local_musi');
        }
    }

    public function validation($data, $files) {
        $errors = [];

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* if ($data['bookingopeningtime'] >= $data['bookingclosingtime']) {
            $errors['bookingopeningtime'] = get_string('error:starttime', 'local_musi');
            $errors['bookingclosingtime'] = get_string('error:endtime', 'local_musi');
        } */

        return $errors;
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/musi/dashboard.php');
    }

    /**
     * Check if logged in user is a teacher of the option.
     * @param int $optionid
     * @return bool true if it's a teacher, false if not
     */
    private function check_if_teacher(int $optionid) {
        global $USER;
        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        if (in_array($USER->id, $settings->teacherids)) {
            return true;
        } else {
            return false;
        }
    }
}
