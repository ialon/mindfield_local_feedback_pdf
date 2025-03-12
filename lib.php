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
    ob_clean();
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
    $rec->dataid = $responses[0]["data"]->id;
    $rec->recordid = $responses[0]["record"]->id;
    $rec->userid = $userid;
    $rec->timecreated = $responses[0]["record"]->timecreated;
    $rec->filename = $filename;
    $rec->data = file_get_contents($pdffile);

    return $DB->insert_record('feedback_pdf', $rec);
}

/**
 * Initialize session for a given data activity
 *
 * @param [type] $id database activity ID
 * @return array
 */
function feedback_pdf_init($id) {
    global $DB;

    $ids = explode(",", $id); // if this was a list, return the first entry
    $id = $ids[0];

    $cm = get_coursemodule_from_id('data', $id);
    if (!$cm) {
        print_error('invalidcoursemodule');
    }

    $course = $DB->get_record('course', array('id' => $cm->course));
    if (!$course) {
        print_error('coursemisconf');
    }

    require_course_login($course, true, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/data:viewentry', $context);
    require_capability('local/feedback_pdf:view', $context);

    return [$cm, $course, $context];
}

/**
 * Get course data
 *
 * @param int $ids comma delimited list of data activity IDs
 * @return array
 */
function feedback_pdf_get_data_responses($ids)
{
    global $DB, $USER, $PAGE;

    $ret = array();
    $ids = explode(",", $ids);
    $multiactivity = count($ids) > 1;

    foreach ($ids as $id) {
        $PAGE = new moodle_page(); // reset moodle PAGE global
        list($cm, $course, $context) = feedback_pdf_init($id);

        if (!$data = $DB->get_record('data', array('id' => $cm->instance))) {
            print_error('invalidcoursemodule');
        }

        $search = '';
        $currentgroup = groups_get_activity_group($cm, true);
        list($records, $maxcount, $totalcount, $page, $nowperpage, $sort, $mode) = data_search_entries(
            $data,
            $cm,
            $context,
            'single',         // $mode
            $currentgroup,
            $search,
            DATA_TIMEADDED,   // $sort (DATA_TIMEMODIFIED / DATA_APPROVED)
            'desc',           // $order
            0,                // $page
            0,                // $perpage
            null,             // $advanced
            null,             // $search_array
            null              // $record
        );

        // adapted from mod/data/lib.php:data_print_template()
        // data_print_template('singletemplate', $records, $data);

        $responses = array();
        $record = array_pop($records);

        if ($multiactivity && empty($record)) {
            // don't append
            continue;
        }

        $fieldrecords = $DB->get_records('data_fields', array('dataid'=>$data->id));
        foreach ($fieldrecords as $fieldrecord) {
            $field = data_get_field($fieldrecord, $data);

            if (empty($record)) {
                $response = "";
            } else {
                $response = $field->display_browse_field($record->id, null);
            }

            $responses[] = array(
                'q' => $field->field->description,
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
            'data' => $data,
            'record' => $record,
            'name' => $data->name,
            "details" => $details,
            'responses' => $responses
        );
    }

    return $ret;
}
