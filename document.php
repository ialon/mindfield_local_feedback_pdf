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

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once('lib.php');

require_login();

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/feedback_pdf/document.php'));
$PAGE->set_pagelayout('frontpage');
$PAGE->set_cacheable(false);

/****************************************************************************/
if ($id) {
/****************************************************************************/
    $rec = $DB->get_record('feedback_pdf', ['id'=>$id, 'userid'=>$USER->id]);
	// if that doesn't work, check
	if (!$rec) {
        if (is_siteadmin()) {
			// authorized so pull up the pdf record again w/out userid
			$rec = $DB->get_record('feedback_pdf', ['id'=>$id]);
        }
	}
	// we should have the record now, if not, we're not authorized.
    if ($rec) {
        feedback_pdf_send_pdf($rec->filename, 'string', $rec->data);
    } else {
		print_error('filenotfound');
    }

/****************************************************************************/
} else {
/****************************************************************************/
    course_view(context_course::instance(SITEID));
    $title = "My Documents";
    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    echo $OUTPUT->header();

    $table = new html_table();
    $table->head = array('Documents', 'Date', 'Download');
    $table->align = array("left", "left", "right");
    $table->data = array();

    $dbrecs = $DB->get_records('feedback_pdf', array('userid'=>$USER->id), 'id desc', 'id, recordid, name, timecreated');
    foreach ($dbrecs as $rec) {
        $displayed[$rec->recordid] = $rec->id;

        $pdfurl = $PAGE->url.'?id='.$rec->id;
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

    echo $OUTPUT->footer();
}