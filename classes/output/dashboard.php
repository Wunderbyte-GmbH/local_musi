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
class dashboard implements renderable, templatable {
    /** @var stdClass $cards */
    public $cards = [];

    /**
     * Constructor
     *
     * @param card|null $card
     */
    public function __construct() {

        $this->create_standard_dashboard();
    }

    /**
     * Create standard dashboard.
     *
     * @return void
     */
    public function create_standard_dashboard() {

        // Add the card with information about the entities on this system.
        $this->card_settings();

        // Add the card with information about courses and bookings.
        $this->card_stats1();

        // Add the card with information about the entities on this system.
        $this->card_entities();

        // Add the card with the list of sports.
        $this->card_sports();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function card_sports() {
        global $PAGE;
        $output = $PAGE->get_renderer('local_musi');
        $data = new card_content_sports();

        $card = new card(
            get_string('listofsports', 'local_musi'),
            $output->render_card_content($data),
            get_string('listofsports_desc', 'local_musi'),
            'bg-light'
        );
        $this->add_card($card);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function card_entities() {
        global $PAGE;
        $output = $PAGE->get_renderer('local_musi');
        $data = new card_content_entities();

        $card = new card(
            get_string('entities', 'local_musi'),
            $output->render_card_content($data),
            get_string('numberofentities_desc', 'local_musi'),
            'bg-light'
        );
        $this->add_card($card);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function card_stats1() {
        global $PAGE;

        $output = $PAGE->get_renderer('local_musi');
        $data = new card_content_stats1();

        $card = new card(
            get_string('numberofcourses', 'local_musi'),
            $output->render_card_content($data),
            get_string('numberofcourses_desc', 'local_musi'),
            'bg-light'
        );
        $this->add_card($card);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function card_settings() {
        global $PAGE;

        $output = $PAGE->get_renderer('local_musi');
        $data = new card_content_settings();

        $card = new card(
            get_string('settingsandreports', 'local_musi'),
            $output->render_card_content($data),
            get_string('settingsandreports_desc', 'local_musi'),
            'bg-light'
        );
        $this->add_card($card);
    }

    /**
     * Add dashboard card.
     *
     * @param card|null $card
     * @return void
     */
    public function add_card(card $card = null) {
        if ($card) {
            $this->cards[] = $card;
        } else {
            $this->cards[] = new card();
        }
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $returnarray = [
                'cards' => (array)$this->cards,
        ];
        return $returnarray;
    }
}
