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
 * Add dates to option.
 *
 * @package local_musi
 * @copyright 2022 Bernhard Fischer <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// No guest autologin.
require_login(0, false);

global $DB, $PAGE, $OUTPUT, $USER;

if (!$context = context_system::instance()) {
    throw new moodle_exception('badcontext');
}

if ((has_capability('mod/booking:updatebooking', $context) || has_capability('mod/booking:addeditownoption', $context)) == false) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('accessdenied', 'mod_booking'), 4);
    echo get_string('nopermissiontoaccesspage', 'mod_booking');
    echo $OUTPUT->footer();
    die();
}

// Important note: SAP daily sums will no longer be generated within this file but by a scheduled task running every night!

// Check if optionid is valid.
$PAGE->set_context($context);

$title = 'SAP-Textdateien mit Tagessummen';

$PAGE->set_url('/local/musi/viewsapdailysums.php');
$PAGE->navbar->add($title);
$PAGE->set_title(format_string($title));

$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

$now = time(); // Current timestamp.
$dateoneyearago = strtotime('-365 days', $now);

$fs = get_file_storage();

// List all existing files as links.
// Revert the order, so we have the newest files on top.
$files = array_reverse($fs->get_area_files($context->id, 'local_musi', 'musi_sap_dailysums'));

$dataforsapfiletemplate = [];
foreach ($files as $file) {
    $filename = $file->get_filename();
    if ($filename == '.') {
        continue;
    }
    $filenamearr = explode('_', $filename);
    $datepart = $filenamearr[2];
    $year = substr($datepart, 0, 4);
    $month = substr($datepart, 4, 2);

    $url = moodle_url::make_pluginfile_url(
        $context->id,
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $filename,
        true // Force download of the file.
    );
    $currentlink = html_writer::link($url, $filename);

    // We collect all links per month, so we can show it in a nice way.

    // It's a new year.
    if (!isset($dataforsapfiletemplate['year'])) {
        $dataforsapfiletemplate = ['year' => [$year => ['month' => [$month => ['link' => [$currentlink]]]]]];
    } else if (!isset($dataforsapfiletemplate['year'][$year]['month'])) {
        // It's a new month.
        $dataforsapfiletemplate['year'][$year] = ['month' => [$month => ['link' => [$currentlink]]]];
    } else {
        // Else we can just add the link to the existing link array.
        $dataforsapfiletemplate['year'][$year]['month'][$month]['link'][] = $currentlink;
    }

    // If we want to delete all files, we can use this line.
    // Important: Only comment this in on testing environments!
    // $file->delete(); // Workaround: delete files.
}

echo build_sapfiles_accordion($dataforsapfiletemplate);
echo $OUTPUT->footer();

/**
 * Helper function to build the SAP files accordion.
 * @param array $dataforsapfiletemplate
 * @return string the html
 */
function build_sapfiles_accordion(array $dataforsapfiletemplate): string {

    // Unfortunately, the nested array did not work properly with mustache.
    // So we build the HTML with this function.

    $html = '<div id="sapfiles-years-accordion">
        <div class="sapfiles-year">';

    if (!empty($dataforsapfiletemplate['year'])) {
        foreach ($dataforsapfiletemplate['year'] as $y => $val) {
            $html .=
                '<div class="card-header" id="heading' . $y . '}">
                    <h5 class="mb-0">
                        <button class="btn btn-link" data-toggle="collapse" data-target="#collapse' . $y . '" aria-expanded="true"
                            aria-controls="collapse' . $y . '">
                            ' . $y . '
                        </button>
                    </h5>
                </div>
                <div id="collapse' . $y . '" class="collapse" aria-labelledby="heading' . $y .
                    '" data-parent="#sapfiles-years-accordion">
                    <div class="card-body">';

            $html .=
                        '<div id="sapfiles-' . $y . '-months-accordion">
                            <div class="sapfiles-month">';

            foreach ($dataforsapfiletemplate['year'][$y]['month'] as $m => $val) {
                $html .=
                                '<div class="h5" id="heading' . "$y-$m" . '}">
                                    <h6 class="mb-0">
                                        <div class="btn btn-link" data-toggle="collapse" data-target="#collapse' . "$y-$m" .
                                        '" aria-expanded="true" aria-controls="collapse' . "$y-$m" . '">
                                            ' . "$y-$m" . '
                                        </div>
                                    </h6>
                                </div>
                                <div id="collapse' . "$y-$m" . '" class="collapse" aria-labelledby="heading' . "$y-$m" .
                                    '" data-parent="#sapfiles-' . $y . '-months-accordion">
                                    <div class="card-body">';

                // Now we add all the links.
                foreach ($dataforsapfiletemplate['year'][$y]['month'][$m]['link'] as $l) {
                                        $html .= $l . '<br>';
                }

                $html .=
                                    '</div>
                                </div>';
            }

            $html .=
                            '</div>
                        </div>
                    </div>
                </div>';
        }
        $html .=
            '</div>
        </div>';
    }

    return $html;
}
