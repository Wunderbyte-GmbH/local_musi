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
 * Plugin administration pages are defined here.
 *
 * @package     local_musi
 * @category    admin
 * @copyright   2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB;

if ($hassiteconfig) {
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf

     // TODO: Define the plugin settings page - {@link https://docs.moodle.org/dev/Admin_settings}.

     $settings = new admin_settingpage('Musi', '');
     $ADMIN->add('localplugins', new admin_category('local_musi', get_string('pluginname', 'local_musi')));
     $ADMIN->add('local_musi', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(
            new admin_setting_heading('shortcodessetdefaultinstance',
                get_string('shortcodessetdefaultinstance', 'local_musi'),
                get_string('shortcodessetdefaultinstancedesc', 'local_musi')));

        $allowedinstances = [];

        if ($records = $DB->get_records_sql(
            "SELECT cm.id cmid, b.name bookingname
            FROM {course_modules} cm
            LEFT JOIN {booking} b
            ON b.id = cm.instance
            WHERE cm.module IN (
                SELECT id
                FROM {modules} m
                WHERE m.name = 'booking'
            )"
        )) {
            foreach ($records as $record) {
                $allowedinstances[$record->cmid] = "$record->bookingname (ID: $record->cmid)";
                $defaultcmid = $record->cmid; // Last cmid will be the default one.
            }
        }

        if (empty($allowedinstances)) {
            // If we have no instances, show an explanation text.
            $settings->add(new admin_setting_description(
                'shortcodesnobookinginstance',
                get_string('shortcodesnobookinginstance', 'local_musi'),
                get_string('shortcodesnobookinginstancedesc', 'local_musi')
            ));
        } else {
            // Show select for cmids of booking instances.
            $settings->add(
                new admin_setting_configselect('local_musi/shortcodessetinstance',
                    get_string('shortcodessetinstance', 'local_musi'),
                    get_string('shortcodessetinstancedesc', 'local_musi'),
                    $defaultcmid, $allowedinstances));
        }

        // Shortcode lists.
        $settings->add(
            new admin_setting_heading('shortcodelists',
                get_string('shortcodelists', 'local_musi'),
                get_string('shortcodelists_desc', 'local_musi')));

        $settings->add(
            new admin_setting_configcheckbox('local_musi/shortcodelists_showdescriptions',
                get_string('shortcodelists_showdescriptions', 'local_musi'), '', 0));

        $settings->add(
            new admin_setting_configcheckbox('local_musi/musishortcodesshowstart',
                get_string('musishortcodes:showstart', 'local_musi'), '', 0));

        $settings->add(
            new admin_setting_configcheckbox('local_musi/musishortcodesshowend',
                get_string('musishortcodes:showend', 'local_musi'), '', 0));

        $settings->add(
            new admin_setting_configcheckbox('local_musi/musishortcodesshowbookablefrom',
                get_string('musishortcodes:showbookablefrom', 'local_musi'), '', 0));

        $settings->add(
            new admin_setting_configcheckbox('local_musi/musishortcodesshowbookableuntil',
                get_string('musishortcodes:showbookableuntil', 'local_musi'), '', 0));

        $showfiltercoursetimesetting = new admin_setting_configcheckbox('local_musi/musishortcodesshowfiltercoursetime',
            get_string('musishortcodes:showfiltercoursetime', 'local_musi'), '', 0);
        $showfiltercoursetimesetting->set_updatedcallback(function() {
            cache_helper::purge_by_event('setbackoptionstable');
        });
        $settings->add($showfiltercoursetimesetting);

        $showfilterbookingtimesetting = new admin_setting_configcheckbox('local_musi/musishortcodesshowfilterbookingtime',
            get_string('musishortcodes:showfilterbookingtime', 'local_musi'), '', 0);
        $showfilterbookingtimesetting->set_updatedcallback(function() {
            cache_helper::purge_by_event('setbackoptionstable');
        });
        $settings->add($showfilterbookingtimesetting);

        $collapsedescriptionoptions = [
            0 => get_string('collapsedescriptionoff', 'local_musi'),
            100 => "100",
            200 => "200",
            300 => "300",
            400 => "400",
            500 => "500",
            600 => "600",
            700 => "700",
            800 => "800",
            900 => "900",
        ];
        $settings->add(
            new admin_setting_configselect('local_musi/collapsedescriptionmaxlength',
                get_string('collapsedescriptionmaxlength', 'local_musi'),
                get_string('collapsedescriptionmaxlength_desc', 'local_musi'),
                300, $collapsedescriptionoptions));

        // Newsletter settings.
        $settings->add(
            new admin_setting_heading('newslettersettingsheading',
                get_string('newslettersettingsheading', 'local_musi'), get_string('newslettersettingsdesc', 'local_musi')));

        // Choose the user profile field which is used to store each user's price category.
        $userprofilefieldsarray[0] = get_string('choose...', 'mod_booking');
        $userprofilefields = profile_get_custom_fields();
        if (!empty($userprofilefields)) {
            // Create an array of key => value pairs for the dropdown.
            foreach ($userprofilefields as $userprofilefield) {
                $userprofilefieldsarray[$userprofilefield->shortname] = $userprofilefield->name;
            }
        }
        $settings->add(
            new admin_setting_configselect('local_musi/newsletterprofilefield',
                get_string('newsletterprofilefield', 'local_musi'),
                get_string('newsletterprofilefielddesc', 'local_musi'),
                0, $userprofilefieldsarray));

        $settings->add(
            new admin_setting_configtext('local_musi/newslettersubscribed',
                get_string('newslettersubscribed', 'local_musi'),
                '', get_string('yes')));

        $settings->add(
            new admin_setting_configtext('local_musi/newsletterunsubscribed',
                get_string('newsletterunsubscribed', 'local_musi'),
                '', get_string('no')));

        // CONTRACT MANAGEMENT.
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* $settings->add(
            new admin_setting_heading('contractmanagement_heading',
                get_string('contractmanagementsettings', 'local_musi'),
                get_string('contractmanagementsettings_desc', 'local_musi')));

        $settings->add(
            new admin_setting_configtextarea('local_musi/contractformula',
                get_string('contractformula', 'local_musi'),
                get_string('contractformula_desc', 'local_musi'), '', PARAM_TEXT, 60, 10)); */

        $settings->add(
            new admin_setting_heading('additionalsettings',
                get_string('additionalsettings', 'local_musi'), ''));

        $settings->add(
            new admin_setting_configtext('local_musi/shortcodesarchivecmids',
                get_string('shortcodesarchivecmids', 'local_musi'),
                get_string('shortcodesarchivecmids_desc', 'local_musi'), ''));

        $settings->add(
            new admin_setting_configcheckbox('local_musi/substitutionspoolshowphonenumbers',
                get_string('substitutionspoolshowphonenumbers', 'local_musi'), '', 0));

        $settings->add(
            new admin_setting_configselect('local_musi/birthdateprofilefield',
                get_string('birthdateprofilefield', 'local_musi'),
                get_string('birthdateprofilefielddesc', 'local_musi'),
                0, $userprofilefieldsarray));
    }
}
