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
use stdClass;

/**
 * Modal form to create single option dates which are not part of the date series.
 *
 * @package     local_musi
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class botags_modal_form extends \core_form\dynamic_form {
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;

        $existingbotagsarray = self::get_existing_botags_array();

        $mform->addElement(
            'autocomplete',
            'botags',
            get_string('editbotags', 'local_musi'),
            $existingbotagsarray,
            [
                'tags' => true,
                'multiple' => true,
                'placeholder' => get_string('createbotag', 'local_musi'),
                'showsuggestions' => false,
            ]
        );

        $mform->addElement('html', get_string('createbotag:helptext', 'local_musi'));
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/musi:canedit', $this->get_context_for_dynamic_submission());
    }

    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $data = new stdClass();

        $data->botags = self::get_existing_botags_array();

        $this->set_data($data);
    }

    public function process_dynamic_submission() {
        global $DB;

        $data = $this->get_data();

        foreach (self::get_existing_botags_array() as $existingbotagrecord) {
            if (!in_array($existingbotagrecord, $data->botags)) {
                $DB->delete_records('local_musi_botags', ['botag' => $existingbotagrecord]);
            }
        }

        foreach ($data->botags as $key => $value) {
            if (!$DB->get_record('local_musi_botags', ['botag' => $value])) {
                $newbotagrecord = new stdClass();
                $newbotagrecord->botag = $value;
                $DB->insert_record('local_musi_botags', $newbotagrecord);
            }
        }

        return $data;
    }

    public function validation($data, $files) {
        $errors = [];
        return $errors;
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/musi/dashboard.php');
    }

    /**
     * Helper function to return existing botags from DB as array.
     *
     * @return array array of existing botags
     */
    private static function get_existing_botags_array(): array {
        global $DB;
        $existingbotagrecords = $DB->get_records('local_musi_botags');
        $existingbotagsarray = [];
        if (!empty($existingbotagrecords)) {
            foreach ($existingbotagrecords as $existingbotagrecord) {
                $existingbotagsarray[$existingbotagrecord->botag] = $existingbotagrecord->botag;
            }
        }
        return $existingbotagsarray;
    }
}
