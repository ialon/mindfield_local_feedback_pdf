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
    $rec->name = $responses[0]["data"]->name;
    $rec->feedbackid = $responses[0]["feedback"]->id;
    $rec->recordid = $responses[0]["record"]->id;
    $rec->userid = $userid;
    $rec->timecreated = $responses[0]["record"]->timecreated;
    $rec->filename = $filename;
    $rec->data = file_get_contents($pdffile);

    return $DB->insert_record('feedback_pdf', $rec);
}

/**
 * Initialize session for a given feedback activity
 *
 * @param [type] $id feedback activity ID
 * @return array
 */
function feedback_pdf_init($id) {
    global $DB;

    $ids = explode(",", $id); // if this was a list, return the first entry
    $id = $ids[0];

    $cm = get_coursemodule_from_id('feedback', $id);
    if (!$cm) {
        print_error('invalidcoursemodule');
    }

    $course = $DB->get_record('course', array('id' => $cm->course));
    if (!$course) {
        print_error('coursemisconf');
    }

    require_course_login($course, true, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/feedback:view', $context);
    require_capability('local/feedback_pdf:view', $context);

    return [$cm, $course, $context];
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
        $PAGE = new moodle_page(); // reset moodle PAGE global
        list($cm, $course, $context) = feedback_pdf_init($id);

        if (!$feedback = $DB->get_record('feedback', array('id' => $cm->instance))) {
            print_error('invalidcoursemodule');
        }

        // Get feedback response
        $params = array('userid' => $USER->id, 'feedback' => $feedback->id);
        $record = $DB->get_record('feedback_completed', $params);

        // Viewing individual response.
        $feedbackstructure = new mod_feedback_completion($feedback, $cm, 0, true, $record->id, false);
        $form = new mod_feedback_complete_form(mod_feedback_complete_form::MODE_VIEW_RESPONSE,
        $feedbackstructure, 'feedback_viewresponse_form');

        $responses = array();

        if ($multiactivity && empty($record)) {
            // don't append
            continue;
        }

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
