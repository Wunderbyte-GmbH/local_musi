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

use coding_exception;
use context_system;
use context;
use dml_exception;
use mod_booking\singleton_service;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Modal form to allow editing of substitution pools for sports.
 *
 * @package     local_musi
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substitutionspool_form extends \core_form\dynamic_form {
    /**
     * Get the context for the dynamic submission.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return context_system::instance();
    }

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('hidden', 'sport');
        $mform->setType('sport', PARAM_RAW);
        $sport = $this->_ajaxformdata['sport'];

        $options = [
            'tags' => false,
            'multiple' => true,
            'noselectionstring' => get_string('choose...', 'mod_booking'),
            'ajax' => 'mod_booking/form_teachers_selector',
            'valuehtmlcallback' => function ($value) {
                global $OUTPUT;
                if (empty($value)) {
                    return get_string('choose...', 'mod_booking');
                }
                $user = singleton_service::get_instance_of_user((int)$value);
                $details = [
                    'id' => $user->id ?? 0,
                    'email' => $user->email ?? '',
                    'firstname' => $user->firstname ?? '',
                    'lastname' => $user->lastname ?? '',
                ];
                return $OUTPUT->render_from_template(
                    'mod_booking/form-user-selector-suggestion',
                    $details
                );
            },
        ];
        $mform->addElement(
            'autocomplete',
            'substitutionspoolteachers',
            get_string('substitutionspool:infotext', 'local_musi', $sport),
            [],
            $options
        );
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {

        $context = $this->get_context_for_dynamic_submission();
        // Check if user has the capability to edit the substitutions pool.
        if (!has_capability('local/musi:editsubstitutionspool', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_musi');
        }
    }

    /**
     * Set data for dynamic submission.
     *
     * @return void
     * @throws dml_exception
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $data = new stdClass();
        $data->sport = $this->_ajaxformdata['sport'];
        if ($existingrecord = $DB->get_record('local_musi_substitutions', ['sport' => $data->sport])) {
            $data->substitutionspoolteachers = explode(',', $existingrecord->teachers);
        } else {
            $data->substitutionspoolteachers = [];
        }
        $this->set_data($data);
    }

    /**
     * Process dynamic submission.
     *
     * @return mixed
     * @throws coding_exception
     * @throws dml_exception
     */
    public function process_dynamic_submission() {
        global $DB, $USER;

        // We get the data prepared by set_data_for_dynamic_submission().
        $data = $this->get_data();
        $sport = $data->sport;

        $teacheridsarr = $data->substitutionspoolteachers;

        return self::add_or_update_substitution_for_sport($sport, $teacheridsarr, true);
    }

    /**
     * Add or update substitution teacher pool for sport.
     *
     * @param string $sport
     * @param array $teacheridsarr
     * @param bool $overwriteteachers
     *
     * @return bool
     *
     */
    public static function add_or_update_substitution_for_sport(
        string $sport,
        array $teacheridsarr,
        bool $overwriteteachers
    ): bool {
        global $DB, $USER;
        $now = time();

        if ($existingrecord = $DB->get_record('local_musi_substitutions', ['sport' => $sport])) {
            $newteachersasstrings = trim(implode(',', $teacheridsarr), ',');
            if ($existingrecord->teachers == $newteachersasstrings) {
                // No change.
                return true;
            }
            if (!$overwriteteachers) {
                // Don't overwrite -> merge given records first.
                $existingteachersarray = explode(',', $existingrecord->teachers);
                $teacheridsarr = array_merge($existingteachersarray, $teacheridsarr);
                // After merging, remove duplicates.
                $teacheridsarr = array_unique($teacheridsarr);
            }
            $teacherids = trim(implode(',', $teacheridsarr), ',');
            $existingrecord->teachers = $teacherids;

            $existingrecord->usermodified = $USER->id;
            $existingrecord->timemodified = $now;
            $DB->update_record('local_musi_substitutions', $existingrecord);
            return true;
        } else {
            $teacherids = trim(implode(',', $teacheridsarr), ',');
            $newrecord = new stdClass();
            $newrecord->sport = $sport;
            $newrecord->teachers = $teacherids;
            $newrecord->usermodified = $USER->id;
            $newrecord->timecreated = $now;
            $newrecord->timemodified = $now;
            // It's a new sports category.
            if ($DB->insert_record('local_musi_substitutions', $newrecord)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];
        // Currently not needed.
        return $errors;
    }

    /**
     * Get page URL for dynamic submission.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/musi/sparten.php');
    }
}
