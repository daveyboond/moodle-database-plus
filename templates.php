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
 * This file is part of the Database module for Moodle
 *
 * @copyright 2005 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod-data
 */

require_once('../../config.php');
require_once('lib.php');

$id    = optional_param('id', 0, PARAM_INT);  // course module id
$d     = optional_param('d', 0, PARAM_INT);   // database id
$mode  = optional_param('mode', 'singletemplate', PARAM_ALPHA);
$disableeditor = optional_param('switcheditor', false, PARAM_RAW);
$enableeditor = optional_param('useeditor', false, PARAM_RAW);

$url = new moodle_url('/mod/data/templates.php');
if ($mode !== 'singletemplate') {
    $url->param('mode', $mode);
}

if ($id) {
    $url->param('id', $id);
    $PAGE->set_url($url);
    if (! $cm = get_coursemodule_from_id('data', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
        print_error('coursemisconf');
    }
    if (! $data = $DB->get_record('data', array('id'=>$cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    $url->param('d', $d);
    $PAGE->set_url($url);
    if (! $data = $DB->get_record('data', array('id'=>$d))) {
        print_error('invalidid', 'data');
    }
    if (! $course = $DB->get_record('course', array('id'=>$data->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('data', $data->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_login($course->id, false, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/data:managetemplates', $context);

if (!$DB->count_records('data_fields', array('dataid'=>$data->id))) {      // Brand new database!
    redirect($CFG->wwwroot.'/mod/data/field.php?d='.$data->id);  // Redirect to field entry
}

add_to_log($course->id, 'data', 'templates view', "templates.php?id=$cm->id&amp;d=$data->id", $data->id, $cm->id);


/// Print the page header

$strdata = get_string('modulenameplural','data');

// For the javascript for inserting template tags: initialise the default textarea to
// 'edit_template' - it is always present in all different possible views.

if ($mode == 'singletemplate') {
    $PAGE->navbar->add(get_string($mode,'data'));
}

$PAGE->requires->js('/mod/data/data.js');
$PAGE->set_title($data->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($data->name));


/// Groups needed for Add entry tab
$currentgroup = groups_get_activity_group($cm);
$groupmode = groups_get_activity_groupmode($cm);

/// Print the tabs.
$currenttab = 'templates';
include('tabs.php');

/// Processing submitted data, i.e updating form.
$resettemplate = false;

if (($mytemplate = data_submitted()) && confirm_sesskey()) {
    $newtemplate = new stdClass();
    $newtemplate->id = $data->id;
    $newtemplate->{$mode} = $mytemplate->template;

    if (!empty($mytemplate->defaultform)) {
        // Reset the template to default, but don't save yet.
        $resettemplate = true;
        $data->{$mode} = data_generate_default_template($data, $mode, 0, false, false);
        if ($mode == 'listtemplate') {
            $data->listtemplateheader = '';
            $data->listtemplatefooter = '';
        }
    } else {
        if (isset($mytemplate->listtemplateheader)){
            $newtemplate->listtemplateheader = $mytemplate->listtemplateheader;
        }
        if (isset($mytemplate->listtemplatefooter)){
            $newtemplate->listtemplatefooter = $mytemplate->listtemplatefooter;
        }
        if (isset($mytemplate->rsstitletemplate)){
            $newtemplate->rsstitletemplate = $mytemplate->rsstitletemplate;
        }

        // Check for multiple tags, only need to check for add template.
        if ($mode != 'addtemplate' or data_tags_check($data->id, $newtemplate->{$mode})) {
            // if disableeditor or enableeditor buttons click, don't save instance
            if (empty($disableeditor) && empty($enableeditor)) {
                $DB->update_record('data', $newtemplate);
                echo $OUTPUT->notification(get_string('templatesaved', 'data'), 'notifysuccess');
                add_to_log($course->id, 'data', 'templates saved', "templates.php?id=$cm->id&amp;d=$data->id", $data->id, $cm->id);
            }
        }
    }
} else {
    echo '<div class="littleintro" style="text-align:center">'.get_string('header'.$mode,'data').'</div>';
}

/// If everything is empty then generate some defaults
if (empty($data->addtemplate) and empty($data->singletemplate) and
    empty($data->listtemplate) and empty($data->rsstemplate)) {
    data_generate_default_template($data, 'singletemplate');
    data_generate_default_template($data, 'listtemplate');
    data_generate_default_template($data, 'addtemplate');
    data_generate_default_template($data, 'asearchtemplate');           //Template for advanced searches.
    data_generate_default_template($data, 'rsstemplate');
}

editors_head_setup();
$format = FORMAT_HTML;

if ($mode === 'csstemplate' or $mode === 'jstemplate') {
    $disableeditor = true;
}

if ($disableeditor) {
    $format = FORMAT_PLAIN;
}
$editor = editors_get_preferred_editor($format);
$strformats = format_text_menu();
$formats =  $editor->get_supported_formats();
foreach ($formats as $fid) {
    $formats[$fid] = $strformats[$fid];
}
$options = array();
$options['trusttext'] = false;
$options['forcehttps'] = false;
$options['subdirs'] = false;
$options['maxfiles'] = 0;
$options['maxbytes'] = 0;
$options['changeformat'] = 0;
$options['noclean'] = false;

echo '<form id="tempform" action="templates.php?d='.$data->id.'&amp;mode='.$mode.'" method="post">';
echo '<div>';
echo '<input name="sesskey" value="'.sesskey().'" type="hidden" />';
// Print button to autogen all forms, if all templates are empty

if (!$resettemplate) {
    // Only reload if we are not resetting the template to default.
    $data = $DB->get_record('data', array('id'=>$d));
}
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo '<table cellpadding="4" cellspacing="0" border="0">';

/// Add the HTML editor(s).
$usehtmleditor = can_use_html_editor() && ($mode != 'csstemplate') && ($mode != 'jstemplate') && !$disableeditor;
if ($mode == 'listtemplate'){
    // Print the list template header.
    echo '<tr>';
    echo '<td>&nbsp;</td>';
    echo '<td>';
    echo '<div style="text-align:center"><label for="edit-listtemplateheader">'.get_string('header','data').'</label></div>';

    $field = 'listtemplateheader';
    $editor->use_editor($field, $options);
    echo '<div><textarea id="'.$field.'" name="'.$field.'" rows="15" cols="80">'.s($data->listtemplateheader).'</textarea></div>';

    echo '</td>';
    echo '</tr>';
}

// Print the main template.

echo '<tr><td valign="top">';
if ($mode != 'csstemplate' and $mode != 'jstemplate') {
    // Add all the available fields for this data.
    echo '<label for="availabletags">'.get_string('availabletags','data').'</label>';
    echo $OUTPUT->help_icon('availabletags', 'data');
    echo '<br />';


    echo '<select name="fields1[]" id="availabletags" size="12" onclick="insert_field_tags(this)">';

    $fields = $DB->get_records('data_fields', array('dataid'=>$data->id));
    echo '<optgroup label="'.get_string('fields', 'data').'">';
    foreach ($fields as $field) {
        echo '<option value="[['.$field->name.']]" title="'.$field->description.'">'.$field->name.' - [['.$field->name.']]</option>';
    }
    echo '</optgroup>';

    if ($mode == 'addtemplate') {
        echo '<optgroup label="'.get_string('fieldids', 'data').'">';
        foreach ($fields as $field) {
            if (in_array($field->type, array('picture', 'checkbox', 'date', 'latlong', 'radiobutton'))) {
                continue; //ids are not usable for these composed items
            }
            echo '<option value="[['.$field->name.'#id]]" title="'.$field->description.' id">'.$field->name.' id - [['.$field->name.'#id]]</option>';
        }
        echo '</optgroup>';
    }

    // Print special tags. fix for MDL-7031
    if ($mode != 'addtemplate' && $mode != 'asearchtemplate') {             //Don't print special tags when viewing the advanced search template and add template.
        echo '<optgroup label="'.get_string('buttons', 'data').'">';
        echo '<option value="##edit##">' .get_string('edit', 'data'). ' - ##edit##</option>';
        echo '<option value="##delete##">' .get_string('delete', 'data'). ' - ##delete##</option>';
        echo '<option value="##approve##">' .get_string('approve', 'data'). ' - ##approve##</option>';
        if ($mode != 'rsstemplate') {
            echo '<option value="##export##">' .get_string('export', 'data'). ' - ##export##</option>';
        }
        if ($mode != 'singletemplate') {
            // more points to single template - not useable there
            echo '<option value="##more##">' .get_string('more', 'data'). ' - ##more##</option>';
            echo '<option value="##moreurl##">' .get_string('moreurl', 'data'). ' - ##moreurl##</option>';
        }
        echo '</optgroup>';
        echo '<optgroup label="'.get_string('other', 'data').'">';
        echo '<option value="##timeadded##">'.get_string('timeadded', 'data'). ' - ##timeadded##</option>';
        echo '<option value="##timemodified##">'.get_string('timemodified', 'data'). ' - ##timemodified##</option>';
        echo '<option value="##user##">' .get_string('user'). ' - ##user##</option>';
        echo '<option value="##email##">' .get_string('email'). ' - ##email##</option>';
        if ($mode != 'singletemplate') {
            // more points to single template - not useable there
            echo '<option value="##comments##">' .get_string('comments', 'data'). ' - ##comments##</option>';
        }
        echo '</optgroup>';
    }

    if ($mode == 'asearchtemplate') {
        echo '<optgroup label="'.get_string('other', 'data').'">';
        echo '<option value="##firstname##">' .get_string('authorfirstname', 'data'). ' - ##firstname##</option>';
        echo '<option value="##lastname##">' .get_string('authorlastname', 'data'). ' - ##lastname##</option>';
        echo '</optgroup>';
    }

    echo '</select>';
    echo '<br /><br /><br /><br /><input type="submit" name="defaultform" value="'.get_string('resettemplate','data').'" />';
    if (can_use_html_editor()) {
        echo '<br /><br />';
        if ($usehtmleditor) {
            $switcheditor = get_string('editordisable', 'data');
            echo '<input type="submit" name="switcheditor" value="'.s($switcheditor).'" />';
        } else {
            $switcheditor = get_string('editorenable', 'data');
            echo '<input type="submit" name="useeditor" value="'.s($switcheditor).'" />';
        }
    }
} else {
    echo '<br /><br /><br /><br /><input type="submit" name="defaultform" value="'.get_string('resettemplate','data').'" />';
}
echo '</td>';

echo '<td valign="top">';
if ($mode == 'listtemplate'){
    echo '<div style="text-align:center"><label for="edit-template">'.get_string('multientry','data').'</label></div>';
} else {
    echo '<div style="text-align:center"><label for="edit-template">'.get_string($mode,'data').'</label></div>';
}

$field = 'template';
$editor->use_editor($field, $options);
echo '<div><textarea id="'.$field.'" name="'.$field.'" rows="15" cols="80">'.s($data->{$mode}).'</textarea></div>';
echo '</td>';
echo '</tr>';

if ($mode == 'listtemplate'){
    echo '<tr>';
    echo '<td>&nbsp;</td>';
    echo '<td>';
    echo '<div style="text-align:center"><label for="edit-listtemplatefooter">'.get_string('footer','data').'</label></div>';

    $field = 'listtemplatefooter';
    $editor->use_editor($field, $options);
    echo '<div><textarea id="'.$field.'" name="'.$field.'" rows="15" cols="80">'.s($data->listtemplatefooter).'</textarea></div>';
    echo '</td>';
    echo '</tr>';
} else if ($mode == 'rsstemplate') {
    echo '<tr>';
    echo '<td>&nbsp;</td>';
    echo '<td>';
    echo '<div style="text-align:center"><label for="edit-rsstitletemplate">'.get_string('rsstitletemplate','data').'</label></div>';

    $field = 'rsstitletemplate';
    $editor->use_editor($field, $options);
    echo '<div><textarea id="'.$field.'" name="'.$field.'" rows="15" cols="80">'.s($data->rsstitletemplate).'</textarea></div>';
    echo '</td>';
    echo '</tr>';
}

echo '<tr><td style="text-align:center" colspan="2">';
echo '<input type="submit" value="'.get_string('savetemplate','data').'" />&nbsp;';

echo '</td></tr></table>';


echo $OUTPUT->box_end();
echo '</div>';
echo '</form>';

/// Finish the page
echo $OUTPUT->footer();
