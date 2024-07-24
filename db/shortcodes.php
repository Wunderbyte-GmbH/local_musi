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
 * Shortcodes for mod booking
 *
 * @package local_musi
 * @subpackage db
 * @since Moodle 3.11
 * @copyright 2022 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$shortcodes = [
    'allekurseliste' => [
        'callback' => 'local_musi\shortcodes::allcourseslist',
        'wraps' => false,
        'description' => 'shortcodeslistofbookingoptions',
    ],
    'allekursekarten' => [
        'callback' => 'local_musi\shortcodes::allcoursescards',
        'wraps' => false,
        'description' => 'shortcodeslistofbookingoptionsascards',
    ],
    'allekursegrid' => [
        'callback' => 'local_musi\shortcodes::allcoursesgrid',
        'wraps' => false,
        'description' => 'shortcodeslistofbookingoptionsascards',
    ],
    'meinekursekarten' => [
        'callback' => 'local_musi\shortcodes::mycoursescards',
        'wraps' => false,
        'description' => 'shortcodeslistofmybookingoptionsascards',
    ],
    'trainerkursekarten' => [
        'callback' => 'local_musi\shortcodes::mytaughtcoursescards',
        'wraps' => false,
        'description' => 'shortcodeslistofmytaughtbookingoptionsascards',
    ],
    'meinekurseliste' => [
        'callback' => 'local_musi\shortcodes::mycourseslist',
        'wraps' => false,
        'description' => 'shortcodeslistofmybookingoptionsaslist',
    ],
    'alletrainerkarten' => [
        'callback' => 'local_musi\shortcodes::allteacherscards',
        'wraps' => false,
        'description' => 'shortcodeslistofteachersascards',
    ],
    'userinformation' => [
        'callback' => 'local_musi\shortcodes::userinformation',
        'wraps' => false,
        'description' => 'shortcodesuserinformation',
    ],
    'showallsports' => [
        'callback' => 'local_musi\shortcodes::showallsports',
        'wraps' => false,
        'description' => 'shortcodesshowallsports',
    ],
    'sparten' => [
        'callback' => 'local_musi\shortcodes::showallsports',
        'wraps' => false,
        'description' => 'shortcodesshowallsports',
    ],
    'newslettersubscribe' => [
        'callback' => 'local_musi\shortcodes::newslettersubscribe',
        'wraps' => false,
        'description' => 'shortcodesnewslettersubscribe',
    ],
    'newsletterunsubscribe' => [
        'callback' => 'local_musi\shortcodes::newsletterunsubscribe',
        'wraps' => false,
        'description' => 'shortcodesnewsletterunsubscribe',
    ],
];
