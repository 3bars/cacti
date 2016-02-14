<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include ('./include/auth.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/utility.php');
include_once('./lib/template.php');

define('MAX_DISPLAY_PAGES', 21);

$ds_actions = array(
	1 => 'Delete',
	2 => 'Duplicate'
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'rrd_add':
		template_rrd_add();

		break;
	case 'rrd_remove':
		template_rrd_remove();

		break;
	case 'template_remove':
		template_remove();

		header('Location: data_templates.php?header=false');
		break;
	case 'template_edit':
		top_header();

		template_edit();

		bottom_footer();
		break;
	default:
		top_header();

		template();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_template')) {
		/* ================= input validation ================= */
		get_filter_request_var('data_input_id');
		get_filter_request_var('data_template_id');
		get_filter_request_var('data_template_data_id');
		get_filter_request_var('data_template_rrd_id');
		get_filter_request_var('data_source_type_id');
		get_filter_request_var('rrd_step');
		get_filter_request_var('rrd_heartbeat');
		/* ==================================================== */

		/* save: data_template */
		$save1['id']   = get_request_var('data_template_id');
		$save1['hash'] = get_hash_data_template(get_request_var('data_template_id'));
		$save1['name'] = form_input_validate(get_nfilter_request_var('template_name'), 'template_name', '', false, 3);

		/* save: data_template_data */
		$save2['id']            = get_request_var('data_template_data_id');
		$save2['local_data_template_data_id'] = 0;
		$save2['local_data_id'] = 0;

		$save2['data_input_id'] = form_input_validate(get_request_var('data_input_id'), 'data_input_id', '^[0-9]+$', true, 3);
		$save2['t_name']        = form_input_validate((isset_request_var('t_name') ? get_nfilter_request_var('t_name') : ''), 't_name', '', true, 3);
		$save2['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', (isset_request_var('t_name') ? true : false), 3);
		$save2['t_active']      = form_input_validate((isset_request_var('t_active') ? get_nfilter_request_var('t_active') : ''), 't_active', '', true, 3);
		$save2['active']        = form_input_validate((isset_request_var('active') ? get_nfilter_request_var('active') : ''), 'active', '', true, 3);
		$save2['t_rrd_step']    = form_input_validate((isset_request_var('t_rrd_step') ? get_nfilter_request_var('t_rrd_step') : ''), 't_rrd_step', '', true, 3);
		$save2['rrd_step']      = form_input_validate(get_request_var('rrd_step'), 'rrd_step', '^[0-9]+$', (isset_request_var('t_rrd_step') ? true : false), 3);
		$save2['t_rra_id']      = form_input_validate((isset_request_var('t_rra_id') ? get_nfilter_request_var('t_rra_id') : ''), 't_rra_id', '', true, 3);

		/* save: data_template_rrd */
		$save3['id']              = get_request_var('data_template_rrd_id');
		$save3['hash']            = get_hash_data_template(get_request_var('data_template_rrd_id'), 'data_template_item');
		$save3['local_data_template_rrd_id'] = 0;
		$save3['local_data_id']   = 0;

		$save3['t_rrd_maximum']   = form_input_validate((isset_request_var('t_rrd_maximum') ? get_nfilter_request_var('t_rrd_maximum') : ''), 't_rrd_maximum', '', true, 3);
		$save3['rrd_maximum']     = form_input_validate(get_nfilter_request_var('rrd_maximum'), 'rrd_maximum', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', (isset_request_var('t_rrd_maximum') ? true : false), 3);
		$save3['t_rrd_minimum']   = form_input_validate((isset_request_var('t_rrd_minimum') ? get_nfilter_request_var('t_rrd_minimum') : ''), 't_rrd_minimum', '', true, 3);
		$save3['rrd_minimum']     = form_input_validate(get_nfilter_request_var('rrd_minimum'), 'rrd_minimum', '^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$', (isset_request_var('t_rrd_minimum') ? true : false), 3);
		$save3['t_rrd_heartbeat'] = form_input_validate((isset_request_var('t_rrd_heartbeat') ? get_nfilter_request_var('t_rrd_heartbeat') : ''), 't_rrd_heartbeat', '', true, 3);
		$save3['rrd_heartbeat']   = form_input_validate(get_request_var('rrd_heartbeat'), 'rrd_heartbeat', '^[0-9]+$', (isset_request_var('t_rrd_heartbeat') ? true : false), 3);
		$save3['t_data_source_type_id'] = form_input_validate((isset_request_var('t_data_source_type_id') ? get_nfilter_request_var('t_data_source_type_id') : ''), 't_data_source_type_id', '', true, 3);
		$save3['data_source_type_id']   = form_input_validate(get_request_var('data_source_type_id'), 'data_source_type_id', '^[0-9]+$', true, 3);
		$save3['t_data_source_name']    = form_input_validate((isset_request_var('t_data_source_name') ? get_nfilter_request_var('t_data_source_name') : ''), 't_data_source_name', '', true, 3);
		$save3['data_source_name']      = form_input_validate(get_nfilter_request_var('data_source_name'), 'data_source_name', '^[a-zA-Z0-9_]{1,19}$', (isset_request_var('t_data_source_name') ? true : false), 3);
		$save3['t_data_input_field_id'] = form_input_validate((isset_request_var('t_data_input_field_id') ? get_nfilter_request_var('t_data_input_field_id') : ''), 't_data_input_field_id', '', true, 3);
		$save3['data_input_field_id']   = form_input_validate((isset_request_var('data_input_field_id') ? get_nfilter_request_var('data_input_field_id') : '0'), 'data_input_field_id', '', true, 3);

		/* ok, first pull out all 'input' values so we know how much to save */
		$input_fields = db_fetch_assoc_prepared("SELECT
			id,
			input_output,
			regexp_match,
			allow_nulls,
			type_code,
			data_name
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = 'in'", array(get_request_var('data_input_id')));

		/* pass 1 for validation */
		if (sizeof($input_fields) > 0) {
			foreach ($input_fields as $input_field) {
				$form_value = 'value_' . $input_field['data_name'];

				if ((isset_request_var($form_value)) && ($input_field['type_code'] == '')) {
					if ((isset_request_var('t_' . $form_value)) &&
						(get_nfilter_request_var('t_' . $form_value) == 'on')) {
						$not_required = true;
					}else if ($input_field['allow_nulls'] == 'on') {
						$not_required = true;
					}else{
						$not_required = false;
					}

					form_input_validate(get_nfilter_request_var($form_value), 'value_' . $input_field['data_name'], $input_field['regexp_match'], $not_required, 3);
				}
			}
		}

		if (!is_error_message()) {
			$data_template_id = sql_save($save1, 'data_template');

			if ($data_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			$save2['data_template_id'] = $data_template_id;
			$data_template_data_id = sql_save($save2, 'data_template_data');

			if ($data_template_data_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		/* update actual host template information for live hosts */
		if ((!is_error_message()) && ($save2['id'] > 0)) {
			db_execute_prepared('UPDATE data_template_data set data_input_id = ? WHERE data_template_id = ?', array(get_request_var('data_input_id'), get_request_var('data_template_id')));
		}

		if (!is_error_message()) {
			$save3['data_template_id'] = $data_template_id;
			$data_template_rrd_id = sql_save($save3, 'data_template_rrd');

			if ($data_template_rrd_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			/* save entries in 'selected rras' field */
			db_execute_prepared('DELETE FROM data_template_data_rra WHERE data_template_data_id = ?', array($data_template_data_id));

			if (isset_request_var('rra_id')) {
				for ($i=0; ($i < count(get_nfilter_request_var('rra_id'))); $i++) {
					/* ================= input validation ================= */
					$rra_id = $_REQUEST['rra_id'][$i];
					if (!is_numeric($rra_id)) {
						exit;
					}
					/* ==================================================== */

					db_execute_prepared('INSERT INTO data_template_data_rra (rra_id, data_template_data_id)
						VALUES (?, ?)', array($rra_id, $data_template_data_id));
				}
			}

			if (!isempty_request_var('data_template_id')) {
				/* push out all data source settings to child data source using this template */
				push_out_data_source($data_template_data_id);
				push_out_data_source_item($data_template_rrd_id);

				db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id = ?', array($data_template_data_id));

				reset($input_fields);
				if (sizeof($input_fields) > 0) {
				foreach ($input_fields as $input_field) {
					$form_value = 'value_' . $input_field['data_name'];

					if (isset_request_var($form_value)) {
						/* save the data into the 'host_template_data' table */
						if (isset_request_var('t_value_' . $input_field['data_name'])) {
							$template_this_item = 'on';
						}else{
							$template_this_item = '';
						}

						if ((!empty($form_value)) || (!isempty_request_var('t_value_' . $input_field['data_name']))) {
							db_execute_prepared('INSERT INTO data_input_data (data_input_field_id, data_template_data_id, t_value, value)
								values (?, ?, ?, ?)', array($input_field['id'], $data_template_data_id, $template_this_item, trim(get_nfilter_request_var($form_value)) ));
						}
					}
				}
				}

				/* push out all "custom data" for this data source template */
				push_out_data_source_custom_data($data_template_id);
				push_out_host(0, 0, $data_template_id);
			}
		}

		header('Location: data_templates.php?header=false&action=template_edit&id=' . (empty($data_template_id) ? get_request_var('data_template_id') : $data_template_id) . (isempty_request_var('current_rrd') ? '' : '&view_rrd=' . (get_nfilter_request_var('current_rrd') ? get_nfilter_request_var('current_rrd') : $data_template_rrd_id)));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $ds_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				$data_template_datas = db_fetch_assoc('SELECT id FROM data_template_data WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');

				if (sizeof($data_template_datas) > 0) {
				foreach ($data_template_datas as $data_template_data) {
					db_execute_prepared('DELETE FROM data_template_data_rra WHERE data_template_data_id = ?', array($data_template_data['id']));
				}
				}

				db_execute('DELETE FROM data_template_data WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');
				db_execute('DELETE FROM data_template_rrd WHERE ' . array_to_sql_or($selected_items, 'data_template_id') . ' AND local_data_id=0');
				db_execute('DELETE FROM snmp_query_graph_rrd WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
				db_execute('DELETE FROM snmp_query_graph_rrd_sv WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
				db_execute('DELETE FROM data_template WHERE ' . array_to_sql_or($selected_items, 'id'));

				/* "undo" any graph that is currently using this template */
				db_execute('UPDATE data_template_data set local_data_template_data_id=0,data_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
				db_execute('UPDATE data_template_rrd set local_data_template_rrd_id=0,data_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
				db_execute('UPDATE data_local set data_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'data_template_id'));
			}elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					duplicate_data_source(0, $selected_items[$i], get_nfilter_request_var('title_format'));
				}
			}
		}

		header('Location: data_templates.php?header=false');
		exit;
	}

	/* setup some variables */
	$ds_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$ds_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_template WHERE id = ?', array($matches[1]))) . '</li>';
			$ds_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_templates.php');

	html_start_box($ds_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($ds_array) && sizeof($ds_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to delete the following Data Template(s).  Any data sources attached
					to these templates will become individual Data Source(s) and all Templating benefits will be removed.</p>
					<p><ul>$ds_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Template(s)'>";
		}elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to duplicate the following Data Template(s). You can
					optionally change the title format for the new Data Template(s).</p>
					<p><ul>$ds_list</ul></p>
					<p>Title Format:<br>"; form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Data Template(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one data template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ----------------------------
    template - Data Templates
   ---------------------------- */

function template_rrd_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('data_template_id');
	/* ==================================================== */

	$children = db_fetch_assoc_prepared('SELECT id FROM data_template_rrd WHERE local_data_template_rrd_id = ? OR id = ?', array(get_request_var('id'), get_request_var('id')));

	if (sizeof($children) > 0) {
	foreach ($children as $item) {
		db_execute_prepared('DELETE FROM data_template_rrd WHERE id = ?', array($item['id']));
		db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE data_template_rrd_id = ?', array($item['id']));
		db_execute_prepared('UPDATE graph_templates_item SET task_item_id = 0 WHERE task_item_id = ?', array($item['id']));
	}
	}

	header('Location: data_templates.php?action=template_edit&id=' . get_request_var('data_template_id'));
}

function template_rrd_add() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('local_data_id');
	/* ==================================================== */

	$hash = get_hash_data_template(0, 'data_template_item');

	db_execute_prepared("INSERT IGNORE INTO data_template_rrd 
		(hash, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name) 
	    VALUES (?, ?, 0, 0, 600, 1, 'ds')", array($hash, get_request_var('id')));

	$data_template_rrd_id = db_fetch_insert_id();

	/* add this data template item to each data source using this data template */
	$children = db_fetch_assoc_prepared('SELECT local_data_id FROM data_template_data WHERE data_template_id = ? AND local_data_id > 0', array(get_request_var('id')));

	if (sizeof($children) > 0) {
	foreach ($children as $item) {
		db_execute_prepared("INSERT IGNORE INTO data_template_rrd 
			(local_data_template_rrd_id, local_data_id, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name) 
			VALUES (?, ?, ?, 0, 0, 600, 1, 'ds')", array($data_template_rrd_id, $item['local_data_id'], get_request_var('id')));
	}
	}

	header('Location: data_templates.php?action=template_edit&id=' . get_request_var('id') . "&view_rrd=$data_template_rrd_id");
}

function template_edit() {
	global $struct_data_source, $struct_data_source_item, $data_source_types, $fields_data_template_template_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('view_rrd');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$template_data = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE data_template_id = ? AND local_data_id = 0', array(get_request_var('id')));
		$template = db_fetch_row_prepared('SELECT * FROM data_template WHERE id = ?', array(get_request_var('id')));

		$header_label = '[edit: ' . $template['name'] . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('data_templates.php', 'data_templates');

	html_start_box('Data Templates ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => 'true'),
		'fields' => inject_form_variables($fields_data_template_template_edit, (isset($template) ? $template : array()), (isset($template_data) ? $template_data : array()), $_REQUEST)
		)
	);

	html_end_box();

	html_start_box('Data Source', '100%', '', '3', 'center', '');

	/* make sure 'data source path' doesn't show up for a template... we should NEVER template this field */
	unset($struct_data_source['data_source_path']);

	$form_array = array();

	while (list($field_name, $field_array) = each($struct_data_source)) {
		$form_array += array($field_name => $struct_data_source[$field_name]);

		if ($field_array['flags'] == 'ALWAYSTEMPLATE') {
			$form_array[$field_name]['description'] = '<em>This field is always templated.</em>';
		}else{
			$form_array[$field_name]['description'] = '';
			$form_array[$field_name]['sub_checkbox'] = array(
				'name' => 't_' . $field_name,
				'friendly_name' => 'Use Per-Data Source Value (Ignore this Value)',
				'value' => (isset($template_data{'t_' . $field_name}) ? $template_data{'t_' . $field_name} : '')
				);
		}

		$form_array[$field_name]['value'] = (isset($template_data[$field_name]) ? $template_data[$field_name] : '');
		$form_array[$field_name]['form_id'] = (isset($template_data) ? $template_data['data_template_id'] : '0');
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, (isset($template_data) ? $template_data : array()))
		)
	);

	html_end_box();

	/* fetch ALL rrd's for this data source */
	if (!isempty_request_var('id')) {
		$template_data_rrds = db_fetch_assoc_prepared('SELECT id, data_source_name FROM data_template_rrd WHERE data_template_id = ? AND local_data_id = 0 ORDER BY data_source_name', array(get_request_var('id')));
	}

	/* select the first "rrd" of this data source by default */
	if (isempty_request_var('view_rrd')) {
		set_request_var('view_rrd', (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0'));
	}

	/* get more information about the rrd we chose */
	if (!isempty_request_var('view_rrd')) {
		$template_rrd = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', array(get_request_var('view_rrd')));
	}

	$i = 0;
	if (isset($template_data_rrds)) {
		if (sizeof($template_data_rrds) > 1) {

		/* draw the data source tabs on the top of the page */
		print "<div class='tabs' style='float:left;'><nav><ul>\n";

		foreach ($template_data_rrds as $template_data_rrd) {
			$i++;
			print "<li>
				<a " . (($template_data_rrd['id'] == get_request_var('view_rrd')) ? "class='selected'" : "class=''") . " href='" . htmlspecialchars('data_templates.php?action=template_edit&id=' . get_request_var('id') . '&view_rrd=' . $template_data_rrd['id']) . "'>$i: " . htmlspecialchars($template_data_rrd['data_source_name']) . "</a>
				<a class='deleteMarker fa fa-remove' title='Delete' href='" . htmlspecialchars('data_templates.php?action=rrd_remove&id=' . $template_data_rrd['id'] . '&data_template_id=' . get_request_var('id')) . "'></a></li>\n";
		}

		print "
		</ul></nav>\n
		</div>\n";

		}elseif (sizeof($template_data_rrds) == 1) {
			set_request_var('view_rrd', $template_data_rrds[0]['id']);
		}
	}

	html_start_box('Data Source Item [' . (isset($template_rrd) ? htmlspecialchars($template_rrd['data_source_name']) : '') . ']', '100%', '', '0', 'center', (!isempty_request_var('id') ? 'data_templates.php?action=rrd_add&id=' . get_request_var('id'):''), 'New');

	/* data input fields list */
	if ((empty($template_data['data_input_id'])) ||
		((db_fetch_cell('SELECT type_id FROM data_input WHERE id=' . $template_data['data_input_id']) != '1') &&
		(db_fetch_cell('SELECT type_id FROM data_input WHERE id=' . $template_data['data_input_id']) != '5'))) {
		unset($struct_data_source_item['data_input_field_id']);
	}else{
		$struct_data_source_item['data_input_field_id']['sql'] = "SELECT id,CONCAT(data_name,' - ',name) AS name FROM data_input_fields WHERE data_input_id=" . $template_data['data_input_id'] . " AND input_output='out' AND update_rra='on' ORDER BY data_name,name";
	}

	$form_array = array();

	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		$form_array += array($field_name => $struct_data_source_item[$field_name]);

		$form_array[$field_name]['description'] = '';
		$form_array[$field_name]['value'] = (isset($template_rrd) ? $template_rrd[$field_name] : '');
		$form_array[$field_name]['sub_checkbox'] = array(
			'name' => 't_' . $field_name,
			'friendly_name' => 'Use Per-Data Source Value (Ignore this Value)',
			'value' => (isset($template_rrd) ? $template_rrd{'t_' . $field_name} : '')
			);
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $form_array + array(
				'data_template_rrd_id' => array(
					'method' => 'hidden',
					'value' => (isset($template_rrd) ? $template_rrd['id'] : '0')
				)
			)
		)
	);

	html_end_box();

	$i = 0;
	if (!isempty_request_var('id')) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc('SELECT * FROM data_input_fields WHERE data_input_id=' . $template_data['data_input_id'] . " AND input_output='in' ORDER BY name");

		html_start_box('Custom Data [data input: ' . htmlspecialchars(db_fetch_cell('SELECT name FROM data_input WHERE id=' . $template_data['data_input_id'])) . ']', '100%', '', '3', 'center', '');

		/* loop through each field found */
		if (sizeof($fields) > 0) {
			foreach ($fields as $field) {
				$data_input_data = db_fetch_row('SELECT t_value,value FROM data_input_data WHERE data_template_data_id=' . $template_data['id'] . ' AND data_input_field_id=' . $field['id']);

				if (sizeof($data_input_data) > 0) {
					$old_value = $data_input_data['value'];
				}else{
					$old_value = '';
				}

				form_alternate_row();

				?>
				<td style='width:50%;'>
					<strong><?php print $field['name'];?></strong><br>
					<?php form_checkbox('t_value_' . $field['data_name'], $data_input_data['t_value'], 'Use Per-Data Source Value (Ignore this Value)', '', '', get_request_var('id'));?>
				</td>
				<td>
					<?php form_text_box('value_' . $field['data_name'],$old_value,'','');?>
					<?php if ((preg_match('/^' . VALID_HOST_FIELDS . '$/i', $field['type_code'])) && ($data_input_data['t_value'] == '')) { print "<br><em>Value will be derived from the host if this field is left empty.</em>\n"; } ?>
				</td>
				<?php
				form_end_row();

				$i++;
			}
		}else{
			print '<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_templates.php', 'return');

	?>
	<script type='text/javascript'>
	$(function() {
		$('#rra_id').multiselect({
			selectedList: 1,
			noneSelectedText: 'Select Round Robin Archive(s)',
			header: false,
			height: 140,
			minWidth: 300
		});
	});
	</script>
	<?php
}

function template() {
	global $ds_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'name', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_data' => array(
			'filter' => FILTER_VALIDATE_REGEXP, 
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_dt');
	/* ================= input validation ================= */

	html_start_box('Data Templates', '100%', '', '3', 'center', 'data_templates.php?action=template_edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_data_template' action='data_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td class='nowrap'>
						Data Templates
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='has_data' <?php print (get_request_var('has_data') == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_data'>Has Data Sources</label>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print get_request_var('page');?>'>
		</form>
		</td>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'data_templates.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_data='+$('#has_data').is(':checked')+'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'data_templates.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#has_data').click(function() {
				applyFilter();
			});

			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
	
			$('#form_data_template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$rows_where = '';
	if (get_request_var('filter') != '') {
		$sql_where = " WHERE (dt.name like '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var('has_data') == 'true') {
		$sql_having = 'HAVING data_sources>0';
	}else{
		$sql_having = '';
	}

	form_start('data_templates.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(rows)
		FROM (SELECT
			COUNT(dt.id) rows,
			SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM data_template AS dt
			INNER JOIN data_template_data AS dtd
			ON dt.id=dtd.data_template_id
			LEFT JOIN data_input AS di
			ON dtd.data_input_id=di.id
			$sql_where
			GROUP BY dt.id
			$sql_having
		) AS rs");

	$template_list = db_fetch_assoc("SELECT dt.id, dt.name, 
		di.name AS data_input_method, dtd.active AS active,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_template AS dt
		INNER JOIN data_template_data AS dtd
		ON dt.id=dtd.data_template_id
		LEFT JOIN data_input AS di
		ON dtd.data_input_id=di.id
		$sql_where
		GROUP BY dt.id
		$sql_having
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('data_templates.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 7, 'Data Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('display' => 'Data Template Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this Data Template.'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Data Templates that are in use can not be Deleted.  In use is defined as being referenced by a Data Source.'), 
		'data_sources' => array('display' => 'Data Sources Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Sources using this Data Template.'),
		'data_input_method' => array('display' => 'Data Input Method', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The method that is used to place Data into the Data Source RRDfile.'),
		'active' => array('display' => 'Status', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'Data Sources based on Inactive Data Templates wont be updated when the poller runs.'),
		'id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal database ID for this Data Template.  Useful when performing automation or debugging.')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			if ($template['data_sources'] > 0) {
				$disabled = true;
			}else{
				$disabled = false;
			}
			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('data_templates.php?action=template_edit&id=' . $template['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['name'])) : htmlspecialchars($template['name'])) . '</a>', $template['id']);
			form_selectable_cell($disabled ? 'No':'Yes', $template['id'], '', 'text-align:right');
			form_selectable_cell(number_format($template['data_sources']), $template['id'], '', 'text-align:right');
			form_selectable_cell((empty($template['data_input_method']) ? '<em>None</em>': htmlspecialchars($template['data_input_method'])), $template['id']);
			form_selectable_cell((($template['active'] == 'on') ? 'Active' : 'Disabled'), $template['id']);
			form_selectable_cell($template['id'], $template['id'], '', 'text-align:right');
			form_checkbox_cell($template['name'], $template['id'], $disabled);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='6'><em>No Data Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	form_end();
}

