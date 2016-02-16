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

include('./include/auth.php');
include_once('./lib/utility.php');

$at_actions = array(
	1 => 'Delete'
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
    case 'movedown':
        automation_movedown();

        header('Location: automation_templates.php?header=false');
		break;
    case 'moveup':
        automation_moveup();

        header('Location: automation_templates.php?header=false');
		break;
    case 'remove':
        automation_remove();

        header('Location: automation_templates.php?header=false');
		break;
	case 'edit':
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

function automation_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */
	move_item_down('automation_templates', get_request_var('id'));
}

function automation_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */
	move_item_up('automation_templates', get_request_var('id'));
}

function automation_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */
	db_execute('DELETE FROM automation_templates WHERE id=' . get_request_var('id'));
}


function form_actions() {
	global $at_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */
	
	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM automation_templates WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: automation_templates.php?header=false');
		exit;
	}

	/* setup some variables */
	$at_list = ''; $i = 0;

	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$at_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT ht.name FROM automation_templates AS at INNER JOIN host_template AS ht ON ht.id=at.host_template WHERE at.id = ?', array($matches[1]))) . '</li>';
			$at_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('automation_templates.php');

	html_start_box($at_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($at_array) && sizeof($at_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>Click 'Continue' to delete the folling Automation Template(s).</p>
					<p><ul>$at_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Automation Template(s)'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one Automation Template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($at_array) ? serialize($at_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	if (isset_request_var('save_component_template')) {
		$redirect_back = false;

		$save['id'] = get_nfilter_request_var('id');
		$save['host_template'] = form_input_validate(get_nfilter_request_var('host_template'), 'host_template', '', false, 3);
		$save['availability_method']  = form_input_validate(get_nfilter_request_var('availability_method'), 'availability_method', '', false, 3);
		$save['sysDescr']      = get_nfilter_request_var('sysDescr');
		$save['sysName']       = get_nfilter_request_var('sysName');
		$save['sysOid']        = get_nfilter_request_var('sysOid');
		if (function_exists('filter_var')) {
			$save['sysDescr'] = filter_var($save['sysDescr'], FILTER_SANITIZE_STRING);
		} else {
			$save['sysDescr'] = strip_tags($save['sysDescr']);
		}

		if (!is_error_message()) {
			$template_id = sql_save($save, 'automation_templates');

			if ($template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message() || isempty_request_var('id')) {
			header('Location: automation_templates.php?header=false&id=' . (empty($template_id) ? get_nfilter_request_var('id') : $template_id));
		}else{
			header('Location: automation_templates.php?header=false');
		}
	}
}

function automation_get_child_branches($tree_id, $id, $spaces, $headers) {
	$items = db_fetch_assoc('SELECT id, title
		FROM graph_tree_items 
		WHERE graph_tree_id=' . $tree_id  . '
		AND host_id=0
		AND local_graph_id=0 
		AND parent=' . $id . '
		ORDER BY position');

	$spaces .= '--';

	if (sizeof($items)) {
	foreach($items as $i) {
		$headers['tr_' . $tree_id . '_bi_' . $i['id']] = $spaces . ' ' . $i['title'];
		$headers = automation_get_child_branches($tree_id, $i['id'], $spaces, $headers);
	}
	}
	
	return $headers;
}

function automation_get_tree_headers() {
	$headers = array();
	$trees   = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');
	foreach ($trees as $tree) {
		$headers['tr_' . $tree['id'] . '_br_0'] = $tree['name'];
		$spaces = '';
		$headers = automation_get_child_branches($tree['id'], 0, $spaces, $headers);
	}

	return $headers;
}

function template_edit() {
	global $availability_options;

	$host_template_names = db_fetch_assoc('SELECT id, name FROM host_template');
	$template_names = array();

	if (sizeof($host_template_names) > 0) {
		foreach ($host_template_names as $ht) {
			$template_names[$ht['id']] = $ht['name'];
		}
	}

	$fields_automation_template_edit = array(
		'host_template' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Host Template',
			'description' => 'Select a Device Template that Devices will be matched to.',
			'value' => '|arg1:host_template|',
			'array' => $template_names,
			),
		'availability_method' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Availability Method',
			'description' => 'Choose the Availability Method to use for Discovered Devices.',
			'value' => '|arg1:availability_method|',
			'default' => read_config_option('availability_method'),
			'array' => $availability_options,
			),
		'sysDescr' => array(
			'method' => 'textbox',
			'friendly_name' => 'System Description Match',
			'description' => 'This is a unique string that will be matched to a devices sysDescr string to pair it to this Discovery Template.  Any perl regular expression can be used in addition to any wildcardable SQL Where expression.',
			'value' => '|arg1:sysDescr|',
			'max_length' => '255',
			),
		'sysName' => array(
			'method' => 'textbox',
			'friendly_name' => 'System Name Match',
			'description' => 'This is a unique string that will be matched to a devices sysName string to pair it to this Automation Template.  Any perl regular expression can be used in addition to any wildcardable SQL Where expression.',
			'value' => '|arg1:sysName|',
			'max_length' => '128',
			),
		'sysOid' => array(
			'method' => 'textbox',
			'friendly_name' => 'System OID Match',
			'description' => 'This is a unique string that will be matched to a devices sysOid string to pair it to this Automation Template.  Any perl regular expression can be used in addition to any wildcardable SQL Where expression.',
			'value' => '|arg1:sysOid|',
			'max_length' => '128',
			),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
			),
		'save_component_template' => array(
			'method' => 'hidden',
			'value' => '1'
			)
		);

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	display_output_messages();

	if (!isempty_request_var('id')) {
		$host_template = db_fetch_row('SELECT * FROM automation_templates WHERE id=' . get_request_var('id'));
		$header_label = '[edit: ' . $template_names[$host_template['host_template']] . ']';
	}else{
		$header_label = '[new]';
		set_request_var('id', 0);
	}

	form_start('automation_templates.php', 'form_network');

	html_start_box("Automation Templates $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => 'true'),
		'fields' => inject_form_variables($fields_automation_template_edit, (isset($host_template) ? $host_template : array()))
		));

	html_end_box();

	form_save_button('automation_templates.php');
}

function template() {
	global $at_actions, $item_rows, $availability_options;

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
			)
	);

	validate_store_request_vars($filters, 'sess_autot');
	/* ================= input validation ================= */

	html_start_box("Device Automation Templates", '100%', '', '3', 'center', 'automation_templates.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_at' action='automation_templates.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						Templates
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
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
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'automation_templates.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_graphs='+$('#has_graphs').is(':checked')+'&header=false';
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'automation_templates.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_at').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	form_start('automation_templates.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', 'automation_templates.php?action=edit');

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM automation_templates $sql_where");

	$dts = db_fetch_assoc("SELECT at.*, '' AS sysName, ht.name
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		$sql_where
		ORDER BY sequence " . 
		' LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('automation_templates.php', MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 7, 'Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		array('display' => 'Template Name', 'align' => 'left'),
		array('display' => 'Availability Method', 'align' => 'left'),
		array('display' => 'System Description Match', 'align' => 'left'),
		array('display' => 'System Name Match', 'align' => 'left'),
		array('display' => 'System ObjectId Match', 'align' => 'left'),
		array('display' => 'Action', 'align' => 'right'));

	html_header_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 1;
	if (sizeof($dts)) {
		foreach ($dts as $dt) {
			if ($dt['name'] == '') {
				$name = 'Unknown Template';
			}else{
				$name = $dt['name'];
			}
			form_alternate_row('line' . $dt['id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('automation_templates.php?action=edit&id=' . $dt['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($name)) : htmlspecialchars($name)) . '</a>', $dt['id']);
			form_selectable_cell($availability_options[$dt['availability_method']], $dt['id']);
			form_selectable_cell(htmlspecialchars($dt['sysDescr']), $dt['id']);
			form_selectable_cell(htmlspecialchars($dt['sysName']), $dt['id']);
			form_selectable_cell(htmlspecialchars($dt['sysOid']), $dt['id']);

			if (get_request_var('filter') == '') {
				if ($i < $total_rows && $total_rows > 1) {
					$form_data = '<a class="pic fa fa-arrow-down moveArrow" href="' . htmlspecialchars('automation_templates.php?action=movedown&id=' . $dt['id']) . '" title="Move Down"></a>';
				}else{
					$form_data = '<span class="moveArrowNone"></span>';
				}

				if ($i > 1 && $i <= $total_rows) {
					$form_data .= '<a class="pic fa fa-arrow-up moveArrow" href="' . htmlspecialchars('automation_templates.php?action=moveup&id=' . $dt['id']) . '" title="Move Up"></a>';
				}else{
					$form_data .= '<span class="moveArrowNone"></span>';
				}
			}else{
				$form_data = '';
			}

			form_selectable_cell($form_data, $dt['id'], '', 'text-align:right');
			form_checkbox_cell($name, $dt['id']);
			form_end_row();

			$i++;
		}
	}else{
		print "<tr><td><em>No Automation Device Templates</em></td></tr>\n";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($at_actions);

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('img.action').click(function() {
			strURL = $(this).attr('href');
			loadPageNoHeader(strURL);
		});
	});
	</script>
	<?php
}

?>
