<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_musi
 * @copyright   2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_musi';
$plugin->release = '0.9.20';
$plugin->version = 2025061100;
$plugin->requires = 2022112800; // Requires this Moodle version. Current: Moodle 4.1.
$plugin->maturity = MATURITY_STABLE;
$plugin->dependencies = [
    'mod_booking' => 2025061200,
    'local_wunderbyte_table' => 2025060600,
    'local_shopping_cart' => 2025060400,
    'local_entities' => 2024111100,
];
