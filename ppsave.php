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
require_once($CFG->dirroot . '/mod/data/locallib.php');
require_once('lib.php');

require_login();

$file = @$_FILES['pdf'];
$ok = @$file['error'] === 0
    && is_int(@$file['size'])
    && @$file['size'] > 0
    && @$file['type'] == 'application/pdf'
    && @$file['name'] == 'blob'
    && !empty(@$file['tmp_name'])
    && is_readable(@$file['tmp_name']);

if (!$ok) {
    die(json_encode(["success" => false]));
}

$rec = new stdClass();
$rec->name = 'Practice Profile Report';
$rec->dataid = -1;
$rec->recordid = -1;
$rec->userid = $USER->id;
$rec->timecreated = time();
$rec->filename = 'Practice Profile Report.pdf';
$rec->data = file_get_contents($file['tmp_name']);

$id = $DB->insert_record('feedback_pdf', $rec);

die(json_encode([
    "success" => true,
    "id" => $id
]));
