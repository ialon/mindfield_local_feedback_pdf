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

require __DIR__ . '/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

global $OUTPUT;

$color1 = '#0FA0AE'; // Web Teal
$color2 = '#01838D'; // Web Teal Dark
$color3 = '#CC4030'; // Orange

$logourl = $OUTPUT->get_logo_url(288);

$html = "";

foreach ($responses as $act) {
    $html .=
        '<page backtop="100pt" backbottom="20pt" backleft="20pt" backright="25pt">'.
        '<page_header>'.
            '<table>'.
            '<tr>'.
                '<td><img src="' . $loogurl . '" width="130"></td>'.
                '<td><h3>'.$act['name'].'</h3>'.
                     $act['details'].
                '</td>'.
            '</tr>'.
            '</table>'.
        '</page_header>'.
        '<page_footer>'.
            '<div align="right;font-size:10;padding-bottom:20pt;padding-right:10pt">'.
                'Page [[page_cu]] / [[page_nb]]'.
            '</div>'.
        '</page_footer>'
        ;

    $i = 0;
    foreach ($act['responses'] as $r) {
        $i++;
        $html .=
            '<div style="margin-bottom:30px;border:1px solid '.$color1.';padding:5px">'.
                '<div style="font-weight:bold;font-size:13pt;color:'.$color2.';border-bottom:1px solid {$color2};padding-bottom:5px">'.
                    $i.'. '.$r["q"].
                '</div>'.
                '<div style="font-size:11pt;padding-bottom:5px">'.
                    ($r["a"] ?: '&nbsp;').
                '</div>'.
            '</div>'
            ;
    }

    $html .= '</page>';
}

$pdffile = tempnam('', 'feedbackpdf').".pdf";
$pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'Letter', 'en', true, 'UTF-8', array(7, 7, 7, 7));
$pdf->writeHTML($html);
$pdf->output($pdffile, 'F');
