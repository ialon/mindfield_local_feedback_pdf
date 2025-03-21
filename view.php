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
 * The first page to view the feedback PDF
 *
 * @package   local_feedback_pdf
 * @copyright Mindfield Consulting
 * @license   Commercial
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/feedback_pdf/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/submission/file/locallib.php');

$mode   = optional_param('mode', '', PARAM_ALPHA);
$submit = optional_param('submit', 0, PARAM_INT);

$id         = required_param('id', PARAM_SEQUENCE);      // IDs of exercise activities
$activityid = required_param('activityid', PARAM_INT);   // ID of this activity
$assignid   = optional_param('assign', 0, PARAM_INT);   // ID of this activity

$ids = explode(",", $id); // if this was a list, return the first entry
list($course, $cm) = get_course_and_cm_from_cmid($ids[0], 'feedback');
require_course_login($course, true, $cm);
$feedback = $PAGE->activityrecord;

$feedbackcompletion = new mod_feedback_completion($feedback, $cm, $course->id);

$context = context_module::instance($cm->id);

if ($course->id == SITEID) {
    $PAGE->set_pagelayout('incourse');
}
$PAGE->set_url('/local/feedback_pdf/view.php', array('id' => $id, 'activityid' => $cm->id, 'assign' => $assignid));
$PAGE->set_title($feedback->name);
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('limitedwidth');

// Check access to the given courseid.
if ($course->id AND $course->id != SITEID) {
    require_course_login(get_course($course->id)); // This overwrites the object $COURSE .
}

require_capability('mod/feedback:view', $context);
require_capability('local/feedback_pdf:view', $context);

$PAGE->activityheader->set_description("");

/****************************************************************************/
if ($mode == 'preview' || $mode == 'save') {
/****************************************************************************/
    $responses  = feedback_pdf_get_responses($id);
    $pdffile    = feedback_pdf_generate_pdf($responses);
    $filename   = "Feedback PDF Report {$responses[0]['name']}.pdf";

    if ($mode == 'save') {
        if ($assignid) {
            feedback_pdf_submit_pdf($assignid, $USER->id, $pdffile, $filename);
        }
        feedback_pdf_save_pdf($responses, $USER->id, $pdffile, $filename);

        // mark activity as complete
        $cm = get_coursemodule_from_id('url', $activityid);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $completion = new completion_info($course);
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);

        $message = ($submit == 1) ? "Results submitted successfully" : "Results saved successful";
        redirect(new moodle_url('/course/view.php', array('id'=>$cm->course)), $message);
    }

    feedback_pdf_send_pdf($filename, 'file', $pdffile);
    die;

/****************************************************************************/
} else {
/****************************************************************************/
    $savelabel  = ($submit == 1) ? 'Save &amp; Submit' : 'Save';
    $saveprompt = ($submit == 1) ? 'Save &amp; submit results to your permanent record?' : 'Save results to your permanent record?';

    // Print the page header.
    echo $OUTPUT->header();
    echo $OUTPUT->heading("Exercise Results");

    $myurl = new moodle_url('/local/feedback_pdf/view.php', array('id'=>$id, 'activityid'=>$activityid, 'assign'=>$assignid));
    $responses = feedback_pdf_get_responses($id);

    $table = new html_table();
    $table->head = array('',  'Action');
    $table->align = array("left", "right");
    $table->data = array();
    if ($record = $responses[0]['record']) {
        $table->data[] =  array(
            "Current results. Please Save to mark this course as Complete.",
            '<a target="pdf" href="'.$myurl.'&mode=preview">Preview</a>'
                .' | <a href="'.$myurl.'&mode=save" onClick="return confirm(\''.addslashes($saveprompt).'\')">'.$savelabel.'</a>'
        );
    };
    echo html_writer::table($table, true);

    $docurl = new moodle_url('/local/feedback_pdf/document.php');
    $table = new html_table();
    $table->head = array('Saved Documents', 'Date', 'Download');
    $table->align = array("left", "left", "right");
    $table->data = array();
    $feedbackid = $responses[0]['feedback']->id;
    $dbrecs = $DB->get_records('feedback_pdf', array('userid'=>$USER->id, 'feedbackid'=>$feedbackid), 'id desc', 'id, name, timecreated');
    foreach ($dbrecs as $rec) {
        $pdfurl = $docurl.'?id='.$rec->id;
        $table->data[] =  array(
            '<a target="_new" href="'.$pdfurl.'">'
                .' '.htmlEntities($rec->name)
                .'</a>',
            date(FEEDBACK_PDF_TIMEFORMAT, $rec->timecreated),
            '<a target="_new" href="'.$pdfurl.'">'
                .$OUTPUT->pix_icon('i/export', 'Download')
                .'</a>'
        );
    }
    echo html_writer::table($table, true);
}

echo $OUTPUT->footer();
