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
 * @package   local_feedback_pdf
 * @copyright Mindfield Consulting
 * @license   Commercial
 */

defined('MOODLE_INTERNAL') || die();

define("FEEDBACK_PDF_TIMEFORMAT", "M d, y h:iA");

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/lib/completionlib.php');

/**
 * Strips extra HTML from string
 *
 * @param string $str
 * @return string
 */
function feedback_pdf_format_string($str)
{
    return strip_tags($str, '<b>,<i>,<br>');
}

/**
 * Create PDF output
 *
 * @param array $responses
 * @return string Internal filename of generated PDF
 */
function feedback_pdf_generate_pdf($responses)
{
    require('generatepdf.php');
    return $pdffile;
}

/**
 * Send PDF to browser
 *
 * @param string $filename
 * @param string $type (file or string)
 * @param string $pdffile
 * @return void
 */
function feedback_pdf_send_pdf($filename, $type, $pdffile)
{
    header("Content-type: application/pdf");
    header('Content-Disposition: inline; filename="'.$filename.'"');
    header('Expires: 0');
    header('Pragma: public');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    if ($type == 'string') {
        echo $pdffile;
    } else {
        readfile($pdffile);
    }
    flush();
}

/**
 * Save response PDF to database
 *
 * @param array $responses
 * @param int $userid
 * @param string $pdffile
 * @return int
 */
function feedback_pdf_save_pdf($responses, $userid, $pdffile, $filename)
{
    global $DB;

    $rec = new stdClass();
    $rec->name = $responses[0]["feedback"]->name;
    $rec->feedbackid = $responses[0]["feedback"]->id;
    $rec->recordid = $responses[0]["record"]->id;
    $rec->userid = $userid;
    $rec->timecreated = $responses[0]["record"]->timecreated;
    $rec->filename = $filename;
    $rec->data = file_get_contents($pdffile);

    return $DB->insert_record('feedback_pdf', $rec);
}

/**
 * Submits a PDF file as an assignment submission for a specific user.
 *
 * @param int $assignid The ID of the assignment.
 * @param int $userid The ID of the user submitting the PDF.
 * @param string $pdffile The PDF file to be submitted.
 * @param string $filename The name of the PDF file to be saved.
 */
function feedback_pdf_submit_pdf($assignid, $userid, $pdffile, $filename) {
    global $DB;

    list($course, $assignment) = get_course_and_cm_from_cmid($assignid, 'assign');
    $context = context_module::instance($assignment->id);
    $assign = new assign($context, $assignment, $course);

    // Create a file submission with the pdf.
    $submission = $assign->get_user_submission($userid, true);
    $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
    $submission->timemodified = time();
    $DB->update_record('assign_submission', $submission);

    $fs = get_file_storage();
    $filerecord = array(
        'contextid' => $assign->get_context()->id,
        'component' => 'assignsubmission_file',
        'filearea' => ASSIGNSUBMISSION_FILE_FILEAREA,
        'itemid' => $submission->id,
        'filepath' => '/',
        'filename' => $filename
    );
    $content = file_get_contents($pdffile);

    // Delete the file if it already exists.
    if ($existingfile = $fs->get_file(...array_values($filerecord))) {
        $existingfile->delete();
    }

    // Create the new file.
    $fs->create_file_from_string((object) $filerecord, $content);

    /** @var \assign_submission_file $plugin */
    $plugin = $assign->get_submission_plugin_by_type('file');
    $plugin->save($submission, (object) []);

    // Mark the assignment as complete.
    $instance = $assign->get_instance();
    $completion = new completion_info($assign->get_course());
    if ($completion->is_enabled($assign->get_course_module()) && $instance->completionsubmit) {
        $completion->update_state($assign->get_course_module(), COMPLETION_COMPLETE, $userid);
    }
}

/**
 * Get course data
 *
 * @param int $ids comma delimited list of feedback activity IDs
 * @return array
 */
function feedback_pdf_get_responses($ids)
{
    global $DB, $USER, $PAGE;

    $ret = array();
    $ids = explode(",", $ids);
    $multiactivity = count($ids) > 1;

    foreach ($ids as $id) {
        $cm = get_coursemodule_from_id('feedback', $id);
        $context = context_module::instance($cm->id);
        $PAGE->set_context($context);

        if (!$feedback = $DB->get_record('feedback', array('id' => $cm->instance))) {
            print_error('invalidcoursemodule');
        }

        // Get feedback response
        $iscompleted = false;
        $params = array('userid' => $USER->id, 'feedback' => $feedback->id);
        if ($record = $DB->get_record('feedback_completed', $params)) {
            $iscompleted = true;
        }

        $responses = array();

        if ($multiactivity && empty($record)) {
            // don't append
            continue;
        }

        // Viewing individual response.
        $feedbackstructure = new mod_feedback_completion($feedback, $cm, 0, $iscompleted, $record->id ?? null, null, $USER->id);
        $form = new mod_feedback_complete_form(mod_feedback_complete_form::MODE_VIEW_RESPONSE,
        $feedbackstructure, 'feedback_viewresponse_form');

        foreach ($feedbackstructure->get_items() as $key => $item) {
            if (in_array($item->typ, array('label', 'captcha', 'pagebreak'))) {
                continue;
            }

            $itemobj = feedback_get_item_class($item->typ);

            if ($item->hasvalue) {
                $value = $feedbackstructure->get_item_value($item);
                $response = $itemobj->get_printval($item, (object) ['value' => $value]);
            } else {
                $response = "";
            }

            $responses[] = array(
                'q' => $itemobj->get_display_name($item),
                'a' => $response
            );
        }

        if (empty($record)) {
            $details = "Incomplete";
        } else {
            $record->timecreated = time();
            $date = date(FEEDBACK_PDF_TIMEFORMAT, $record->timecreated);
            $details = "Completed by {$USER->firstname} {$USER->lastname} on {$date}";
        }

        $ret[] = array(
            'feedback' => $feedback,
            'record' => $record,
            'name' => $feedback->name,
            "details" => $details,
            'responses' => $responses
        );
    }

    return $ret;
}
