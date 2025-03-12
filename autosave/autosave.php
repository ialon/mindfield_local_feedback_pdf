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

/*
Add this to the Javascript Template of the Database Activity:
-------------------------------------------------------------
var cpso_autosave_entityid = 58;
var cpso_autosave_js = document.createElement("script");
cpso_autosave_js.src = '/local/cpsopdf/autosave/autosave.js';
document.head.appendChild(cpso_autosave_js);

Add this table/index to the database:
-------------------------------------------------------------
CREATE TABLE [cpsomoodle].[mdl_cpsoautosave](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[entityid] [bigint] NOT NULL,
	[userid] [bigint] NOT NULL,
	[timecreated] [bigint] NOT NULL,
	[data] [nvarchar](max) NOT NULL,
 CONSTRAINT [PK_mdl_cpsoautosave] PRIMARY KEY CLUSTERED
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

CREATE UNIQUE NONCLUSTERED INDEX [IX_mdl_cpsoautosave] ON [cpsomoodle].[mdl_cpsoautosave]
(
	[entityid] ASC,
	[userid] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, SORT_IN_TEMPDB = OFF, IGNORE_DUP_KEY = OFF, DROP_EXISTING = OFF, ONLINE = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
GO
*/

require_once(dirname(__FILE__) . '../../../../config.php');
require_login();

$entityid = @$_REQUEST['entityid'];
if (!filter_var($entityid, FILTER_VALIDATE_INT) || $entityid == 0) {
    die(json_encode([
        "success" => false,
        "message" => "entityid missing"
    ]));
}

$mode = trim(@$_REQUEST['mode']);
$cond = ['entityid'=>$entityid, 'userid'=>$USER->id];

/****************************************************************************/
if ($mode == 'load') {
/****************************************************************************/
    $rec = $DB->get_record('cpsoautosave', $cond, 'timecreated, data');
    if (empty($rec)) {
        die(json_encode([
            "success" => false
        ]));
    } else {
        die(json_encode([
            "success" => true,
            "timecreated" => date("M dS, Y h:iA", $rec->timecreated),
            "data" => json_decode($rec->data)
        ]));
    }

/****************************************************************************/
} elseif ($mode == 'save') {
/****************************************************************************/
    $data = @$_REQUEST['data'];
    if (!is_array($data)) {
        die(json_encode([
            "success" => false,
            "message" => "data missing or invalid"
        ]));
    }

    $data = json_encode($data);
    $rec = $DB->get_record('cpsoautosave', $cond);
    if (@$rec->id) {
        $rec->timecreated = time();
        $rec->data = $data;
        $DB->update_record('cpsoautosave', $rec);

    } else {
        $rec = new stdClass();
        $rec->entityid = $entityid;
        $rec->userid = $USER->id;
        $rec->timecreated = time();
        $rec->data = $data;
        $DB->insert_record('cpsoautosave', $rec);
    }

    die(json_encode([
        "success" => true
    ]));

/****************************************************************************/
} elseif ($mode == 'delete') {
/****************************************************************************/
    $DB->delete_records('cpsoautosave', $cond);
    die(json_encode([
        "success" => true
    ]));

/****************************************************************************/
} else {
/****************************************************************************/
    die(json_encode([
        "success" => false,
        "message" => "invalid mode"
    ]));
}
