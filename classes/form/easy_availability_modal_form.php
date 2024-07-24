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

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $formdata = $this->_customdata ?? $this->_ajaxformdata;

        // We need context on this.
        $context = $this->get_context_for_dynamic_submission();
        $formdata['context'] = $context;
        $optionid = $formdata['id'] ?? $formdata['optionid'] ?? 0;

        $mform = &$this->_form;

        $mform->addElement('hidden', 'scrollpos');
        $mform->setType('scrollpos', PARAM_INT);

        // Add all available fields in the right order.
        fields_info::instance_form_definition($mform, $formdata);
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
            (has_capability('mod/booking:limitededitownoption', $context) && booking_check_if_teacher($optionid)) ||
            (has_capability('mod/booking:addeditownoption', $context) && booking_check_if_teacher($optionid)))
        );
        if (!$alloweditavailability) {
            throw new moodle_exception('norighttoaccess', 'local_musi');
        }
    }

    public function validation($data, $files) {
        $errors = [];
        return $errors;
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/musi/dashboard.php');
    }
}
