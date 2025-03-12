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
 * Version information
 *
 * @package   local_cpsopdf
 * @copyright Mindfield Consulting
 * @license   Commercial
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/data/locallib.php');
require_once('lib.php');

require_login();

$mode   = optional_param('mode', '', PARAM_ALPHA);
$submit = optional_param('submit', 0, PARAM_INT);

/****************************************************************************/
if ($mode == 'preview' || $mode == 'save') {
/****************************************************************************/
    $id         = required_param('id', PARAM_SEQUENCE);      // IDs of exercise activities
    $activityid = required_param('activityid', PARAM_INT);   // ID of this activity (to mark completion against)

    $responses  = cpsopdf_get_data_responses($id);
    $pdffile    = cpsopdf_generate_pdf($responses);
    $filename   = "CPSO Report {$responses[0]['name']}.pdf";

    if ($mode == 'save') {
        cpsopdf_save_pdf($responses, $USER->id, $pdffile, $filename);

        // mark activity as complete
        $cm = get_coursemodule_from_id('url', $activityid);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $completion = new completion_info($course);
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);

        $message = ($submit == 1) ? "Results submitted successfully" : "Results saved successful";
        redirect(new moodle_url('/course/view.php', array('id'=>$cm->course)), $message);
    }

    cpsopdf_send_pdf($filename, 'file', $pdffile);
    die;

/****************************************************************************/
} else {
/****************************************************************************/
    $id         = required_param('id', PARAM_SEQUENCE);     // IDs of exercise activities
    $activityid = required_param('activityid', PARAM_INT);  // ID of this activity (to mark completion against)

    $savelabel  = ($submit == 1) ? 'Save &amp; Submit' : 'Save';
    $saveprompt = ($submit == 1) ? 'Save &amp; submit results to your permanent record?' : 'Save results to your permanent record?';

    list($cm, $course, $context) = cpsopdf_init($id);
    $course->format = course_get_format($course)->get_format();
    $title = $course->fullname." Report";

    $PAGE->set_url(new moodle_url('/course/view.php', array('id'=>$course->id)));
    $PAGE->set_cacheable(false);
    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    echo $OUTPUT->header();
    echo $OUTPUT->heading("Exercise Results");

    $myurl = new moodle_url('/local/cpsopdf/view.php', array('id'=>$id, 'activityid'=>$activityid));
    $responses = cpsopdf_get_data_responses($id);

    $table = new html_table();
    $table->head = array('',  'Action');
    $table->align = array("left", "right");
    $table->data = array();
    if ($record = $responses[0]['record']) {
        $table->data[] =  array(
            "Current exercise results. Please Save to mark this course as Complete.",
            '<a target="pdf" href="'.$myurl.'&mode=preview">Preview</a>'
                .' | <a href="'.$myurl.'&mode=save" onClick="return confirm(\''.addslashes($saveprompt).'\')">'.$savelabel.'</a>'
        );
    };
    echo html_writer::table($table, true);

    $docurl = new moodle_url('/local/cpsopdf/document.php');
    $table = new html_table();
    $table->head = array('Saved Documents', 'Date', 'Download');
    $table->align = array("left", "left", "right");
    $table->data = array();
    $dataid = $responses[0]['data']->id;
    $dbrecs = $DB->get_records('cpsopdf', array('userid'=>$USER->id, 'dataid'=>$dataid), 'id desc', 'id, name, timecreated');
    foreach ($dbrecs as $rec) {
        $pdfurl = $docurl.'?id='.$rec->id;
        $table->data[] =  array(
            '<a target="_new" href="'.$pdfurl.'">'
                .' '.htmlEntities($rec->name)
                .'</a>',
            date(CPSOPDF_TIMEFORMAT, $rec->timecreated),
            '<a target="_new" href="'.$pdfurl.'">'
                .$OUTPUT->pix_icon('i/export', 'Download')
                .'</a>'
        );
    }
    echo html_writer::table($table, true);

    echo $OUTPUT->footer();
}