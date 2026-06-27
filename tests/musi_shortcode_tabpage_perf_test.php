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

namespace local_musi;

use advanced_testcase;
use context_system;
use mod_booking\singleton_service;
use stdClass;

/**
 * Performance-regression tests for the central "offer page" tab structure.
 *
 * The real offer page is built from several [allekurseliste ...] shortcodes, one
 * per tab (Sportkurse, Schneesport, Workshops, Favoriten, ...). Each shortcode
 * builds a wunderbyte table. Historically this performed badly until the tabs were
 * switched to lazy loading (lazy=1), which DEFERS the row query to a later AJAX
 * call instead of running it inline while the page HTML is assembled
 * (see shortcodes::generate_output(): lazy => lazyouthtml(), otherwise outhtml()).
 *
 * Two argument families proved especially performance critical in production and
 * are therefore exercised for real here, not stubbed:
 *  - kst (cost centre): a booking customfield filter. shortcodes builds
 *    "(kst = 'X' OR kst LIKE 'X,%' OR kst LIKE '%,X' OR kst LIKE '%,X,%')" — the
 *    leading-wildcard LIKEs are un-indexable, so the customfield must exist and
 *    carry values for the clause to behave as in production.
 *  - includeoptions: an explicit option-id IN(...) list OR-ed into the WHERE.
 *
 * These tests pin the lazy contract with Moodle's DB read counter so a regression
 * (a tab losing lazy=1, or a per-row query creeping into the build path) is caught
 * deterministically: a lazy render's read cost must be INDEPENDENT of how many
 * booking options exist.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_musi\shortcodes::allcourseslist
 */
final class musi_shortcode_tabpage_perf_test extends advanced_testcase {

    /** @var string cost-centre value tagged onto seeded options. */
    private const KST_VALUE = 'ET592004';

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        singleton_service::destroy_instance();
        \cache_helper::purge_all();
    }

    /**
     * Create the booking "kst" (cost centre) option customfield once per test.
     */
    private function create_kst_customfield(): void {
        $category = $this->getDataGenerator()->create_custom_field_category([
            'name' => 'OfferPageCat',
            'component' => 'mod_booking',
            'area' => 'booking',
            'itemid' => 0,
            'contextid' => context_system::instance()->id,
        ]);
        $category->save();

        $field = $this->getDataGenerator()->create_custom_field([
            'categoryid' => $category->get('id'),
            'name' => 'Kostenstelle',
            'shortname' => 'kst',
            'type' => 'text',
            'configdata' => '',
        ]);
        $field->save();
    }

    /**
     * Seed one booking instance with $n options; every other option is tagged with
     * the cost-centre value so the kst filter has something to match.
     *
     * @param int $n
     * @return array{0:int,1:int[]} [cmid, optionids]
     */
    private function seed_instance(int $n): array {
        $course = $this->getDataGenerator()->create_course();
        $booking = $this->getDataGenerator()->create_module('booking', [
            'course' => $course->id,
            'name' => 'Offer page booking',
        ]);

        /** @var \mod_booking_generator $gen */
        $gen = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $optionids = [];
        for ($i = 0; $i < $n; $i++) {
            $record = (object) [
                'bookingid' => $booking->id,
                'courseid' => $course->id,
                'text' => 'Course ' . $i,
                'description' => 'Course ' . $i,
                'chooseorcreatecourse' => 0,
                'coursestarttime_0' => strtotime('now + 1 day'),
                'courseendtime_0' => strtotime('now + 2 day'),
                'maxanswers' => 5,
                'maxoverbooking' => 0,
            ];
            // Tag every other option with the cost centre.
            if ($i % 2 === 0) {
                $record->customfield_kst = self::KST_VALUE;
            }
            $option = $gen->create_option($record);
            $optionids[] = (int) $option->id;
        }
        return [(int) $booking->cmid, $optionids];
    }

    /**
     * Render an [allekurseliste ...] shortcode the way the filter_shortcodes plugin
     * does and return the produced HTML.
     *
     * @param array $args shortcode arguments (coerced to strings as the filter does)
     * @return string
     */
    private function render_allekurseliste(array $args): string {
        $args = array_map(fn($v) => (string) $v, $args);
        $next = function () {
            return '';
        };
        return \local_musi\shortcodes::allcourseslist(
            'allekurseliste',
            $args,
            '',
            new stdClass(),
            $next
        );
    }

    /**
     * Measure the DB reads a single shortcode render costs once everything is warm.
     *
     * @param array $args
     * @return int read delta of a second, fully-warm render
     */
    private function warm_read_cost(array $args): int {
        global $DB;
        $this->render_allekurseliste($args);
        $before = $DB->perf_get_reads();
        $this->render_allekurseliste($args);
        return $DB->perf_get_reads() - $before;
    }

    /**
     * A lazy shortcode that filters by cost centre (kst) and an explicit
     * includeoptions list defers its row query, so its DB read cost must not grow
     * with the number of booking options on the instance.
     */
    public function test_lazy_kst_and_includeoptions_does_not_scale(): void {
        global $PAGE;
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new \moodle_url('/'));
        $this->create_kst_customfield();

        [$smallcmid, $smallids] = $this->seed_instance(10);
        [$largecmid, $largeids] = $this->seed_instance(40);

        $baseargs = [
            'lazy' => 1,
            'filter' => 1,
            'search' => 1,
            'sort' => 1,
            'kst' => self::KST_VALUE,
            'requirelogin' => 'false',
        ];

        // includeoptions: a handful of real option ids from the same instance,
        // mirroring the production "home" tab's explicit id list.
        $small = $this->warm_read_cost(
            ['id' => $smallcmid, 'includeoptions' => implode(',', array_slice($smallids, 0, 5))] + $baseargs
        );
        $large = $this->warm_read_cost(
            ['id' => $largecmid, 'includeoptions' => implode(',', array_slice($largeids, 0, 5))] + $baseargs
        );

        // 30 extra options must not translate into ~30 extra reads. A small,
        // bounded difference (filter cardinality etc.) is acceptable; per-row
        // scaling is not.
        $growth = $large - $small;
        $this->assertLessThan(
            10,
            $growth,
            "Lazy [allekurseliste kst=.. includeoptions=..] read cost grew by {$growth} reads "
                . "when option count went 10 -> 40 (small={$small}, large={$large}). A lazy tab must "
                . "defer its row query; growth proportional to rows means it is running inline."
        );
    }

    /**
     * A whole tab block (several lazy shortcodes, like the real offer page, with
     * mixed kst / includeoptions filters) must keep its DB reads independent of
     * option count.
     */
    public function test_lazy_tab_block_does_not_scale_with_option_count(): void {
        global $PAGE, $DB;
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new \moodle_url('/'));
        $this->create_kst_customfield();

        [$smallcmid, $smallids] = $this->seed_instance(10);
        [$largecmid, $largeids] = $this->seed_instance(40);

        // Mirrors the real page: a handful of lazy tabs with varied filters.
        $renderblock = function (int $cmid, array $ids): void {
            $this->render_allekurseliste(['id' => $cmid, 'lazy' => 1, 'filter' => 1, 'search' => 1, 'sort' => 1]);
            $this->render_allekurseliste(['id' => $cmid, 'lazy' => 1, 'filter' => 1, 'image' => 1, 'units' => 1]);
            $this->render_allekurseliste(['id' => $cmid, 'lazy' => 1, 'kst' => self::KST_VALUE, 'search' => 1]);
            $this->render_allekurseliste([
                'id' => $cmid, 'lazy' => 1, 'sort' => 1,
                'includeoptions' => implode(',', array_slice($ids, 0, 5)),
            ]);
        };

        // Warm both.
        $renderblock($smallcmid, $smallids);
        $renderblock($largecmid, $largeids);

        $before = $DB->perf_get_reads();
        $renderblock($smallcmid, $smallids);
        $small = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $renderblock($largecmid, $largeids);
        $large = $DB->perf_get_reads() - $before;

        $growth = $large - $small;
        $this->assertLessThan(
            25,
            $growth,
            "Lazy tab block read cost grew by {$growth} reads for 10 -> 40 options "
                . "(small={$small}, large={$large}). The offer page must stay flat in option count."
        );
    }

    /**
     * Contrast: a NON-lazy shortcode runs its query inline, so it must cost
     * noticeably more reads than the lazy variant on the same instance. This
     * documents the inline path that was the original performance problem and
     * guards the assumption that lazy is the materially cheaper option at render.
     */
    public function test_non_lazy_shortcode_is_more_expensive_than_lazy(): void {
        global $PAGE;
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new \moodle_url('/'));
        $this->create_kst_customfield();

        [$cmid] = $this->seed_instance(30);

        $lazy = $this->warm_read_cost([
            'id' => $cmid, 'lazy' => 1, 'filter' => 1, 'search' => 1, 'sort' => 1, 'kst' => self::KST_VALUE,
        ]);
        $inline = $this->warm_read_cost([
            'id' => $cmid, 'filter' => 1, 'search' => 1, 'sort' => 1, 'kst' => self::KST_VALUE,
        ]);

        $this->assertGreaterThan(
            $lazy,
            $inline,
            "Non-lazy render ({$inline} reads) was not more expensive than lazy ({$lazy} reads); "
                . "expected the inline path to execute the row query that lazy defers."
        );
    }
}
