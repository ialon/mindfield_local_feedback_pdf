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

$plugin->version   = 2020010000;
$plugin->requires  = 2019052000;
$plugin->component = 'local_feedback_pdf';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = "2.0";

$plugin->dependencies = array(
    'mod_feedback' => ANY_VERSION,
);
