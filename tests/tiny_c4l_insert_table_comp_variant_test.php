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
 * Unit test adding junction table for components and variants.
 *
 * @package    tiny_c4l
 * @copyright  2024 ISB Bayern, Franziska Hübler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_c4l;

defined('MOODLE_INTERNAL') || die;

/**
 * Unit test adding junction table for components and variants.
 *
 * @package    tiny_c4l
 * @copyright  2024 ISB Bayern, Franziska Hübler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tiny_c4l_insert_table_comp_variant_test extends \advanced_testcase {

    /**
     * Tests adding junction table for components and variants.
     * 
     * @covers \tiny_c4l_insert_comp_variant
     */
    public function test_tiny_c4l_insert_comp_variant(): void {
        global $DB, $CFG;

        require($CFG->dirroot . "/lib/editor/tiny/plugins/c4l/db/upgradelib.php");

        $this->resetAfterTest();

        // Truncate the tiny_c4l_component table to get a white piece of paper.
        $DB->delete_records('tiny_c4l_component');

        // Checking that tiny_c4l_component table is empty.
        $this->assertEmpty($DB->get_records('tiny_c4l_component'));

        // Populating tiny_c4l_component with data.

        // Insert one element without variant.
        $record1 = new \stdClass();
        $record1->name = 'inlinetag';
        $record1->displayname = 'Inline Tag';
        $record1->compcat = 2;
        $record1->code = '<span class="c4lv-inlinetag {{VARIANTS}}" aria-label="{{#inlinetag}}">{{PLACEHOLDER}}</span>';
        $record1->text = 'text';
        $record1->variants = '';
        $record1->displayorder = 0;
        $record1->css = '.c4l-inlinetag {font-weight: 900;}';
        $record1->js = '';
        $record1->iconurl = '';
        $record1->timecreated = time();
        $record1->timemodified = time()+1;        
        $DB->insert_record('tiny_c4l_component', $record1);

        // Insert one element with one variant.
        $record2 = new \stdClass();
        $record2->name = 'dodontcards';
        $record2->displayname = 'Do/Don’t Cards';
        $record2->compcat = 2;
        $record2->code = '<p class="c4l-spacer"></p>';
        $record2->text = 'Lorem ipsum';
        $record2->variants = 'full-width';
        $record2->displayorder = 0;
        $record2->css = '.c4l-dodontcards { margin-bottom: 0;}';
        $record2->js = '';
        $record2->iconurl = '';
        $record2->timecreated = time();
        $record2->timemodified = time()+2;        
        $DB->insert_record('tiny_c4l_component', $record2);

        // Insert one element with two variant.
        $record3 = new \stdClass();
        $record3->name = 'quote';
        $record3->displayname = 'Quote';
        $record3->compcat = 2;
        $record3->code = '<div class="c4lv-quote {{VARIANTS}}" aria-label="{{#quote}}"></div>';
        $record3->text = 'Lorem ipsum';
        $record3->variants = 'full-width,quote';
        $record3->displayorder = 0;
        $record3->css = '.c4l-quote-body {display: flex;}';
        $record3->js = '';
        $record3->iconurl = '@@PLUGINFILE@@/1/tiny_c4l/images/2/noun_project_icons/';
        $record3->timecreated = time();
        $record3->timemodified = time()+1;        
        $DB->insert_record('tiny_c4l_component', $record3);

        // Insert one element with three variant.
        $record4 = new \stdClass();
        $record4->name = 'readingcontext';
        $record4->displayname = 'Readingcontext';
        $record4->compcat = 2;
        $record4->code = '<div class="c4lv-readingcontext {{VARIANTS}}" aria-label="{{#readingcontext}}"></div>';
        $record4->text = 'Lorem ipsum';
        $record4->variants = 'comfort-reading,full-width,quote';
        $record4->displayorder = 0;
        $record4->css = '.c4lv-readingcontext p {font-size: 16px;}';
        $record4->js = '';
        $record4->iconurl = '';
        $record4->timecreated = time();
        $record4->timemodified = time()+1;        
        $DB->insert_record('tiny_c4l_component', $record4);

        // Check that there are 4 Elements in tiny_c4l_component.
        $this->assertEquals(4, $DB->count_records('tiny_c4l_component'));

        // Check 13 fieldnames in tiny_c4l_component.
        $comp_quote = $DB->get_record('tiny_c4l_component', ['name' => 'quote']);
        $this->assertEquals(13, sizeof((array) $comp_quote));

        // Running the migration script.
        tiny_c4l_insert_comp_variant();

        // Checking the results.

        // Check that there are still 4 Elements in tiny_c4l_component.
        $this->assertEquals(4, $DB->count_records('tiny_c4l_component'));

        // Check 12 fieldnames in tiny_c4l_component (so one field was deleted).
        $comp_quote = $DB->get_record('tiny_c4l_component', ['name' => 'quote']);
        $this->assertEquals(12, sizeof((array) $comp_quote));

        // Check that there are 6 Elements in tiny_c4l_comp_variant.
        $this->assertEquals(6, $DB->count_records('tiny_c4l_comp_variant'));

        // Check data correctness.
        $sql = "SELECT cpv.variant
                FROM {tiny_c4l_comp_variant} cpv
                JOIN {tiny_c4l_component} cp
                ON cpv.component = cp.id
                WHERE cp.name = :component";
        
        $this->assertCount(0, $DB->get_records_sql($sql, ['component' => 'inlinetag']));
        $this->assertCount(1, $DB->get_records_sql($sql, ['component' => 'dodontcards']));
        $this->assertCount(2, $DB->get_records_sql($sql, ['component' => 'quote']));
        $this->assertCount(3, $DB->get_records_sql($sql, ['component' => 'readingcontext']));
    }
}

        






