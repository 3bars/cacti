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

include_once('./include/auth.php');

define('MAX_DISPLAY_PAGES', 21);

$aggregate_actions = array(
	1 => 'Delete',
	2 => 'Duplicate'
	);

/* set default action */
set_default_action();

switch ($_REQUEST['action']) {
	case 'save':
		aggregate_color_form_save();

		break;
	case 'actions':
		aggregate_color_form_actions();

		break;
	case 'template_edit':
		top_header();
		aggregate_color_template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		aggregate_color_template();
		bottom_footer();

		break;
}

/** draw_color_template_items_list 	- draws a nicely formatted list of color items for display
 *   								  on an edit form
 * @param array $item_list 			- an array representing the list of color items. this array should
 *   								  come directly from the output of db_fetch_assoc()
 * @param string $filename 			- the filename to use when referencing any external url
 * @param string $url_data 			- any extra GET url information to pass on when referencing any
 *   								  external url
 * @param bool $disable_controls 	- whether to hide all edit/delete functionality on this form
 */
function draw_color_template_items_list($item_list, $filename, $url_data, $disable_controls) {
	global $config;
	global $struct_color_template_item;

	$display_text = array(
		array('display' => 'Color Item', 'align' => 'left'),
		array('display' => 'Item Color', 'align' => 'left'),
	);

	html_header($display_text, 4);

	$i = 0;

	if (sizeof($item_list)) {
		foreach ($item_list as $item) {
			/* alternating row color */
			form_alternate_row();

			print '<td>';

			if ($disable_controls == false) { 
				print "<a class='linkEditMain' href='" . htmlspecialchars($filename . '?action=item_edit&color_template_item_id=' . $item['color_template_item_id'] . "&$url_data") . "'>"; 
			}

			print '<strong>Item # ' . ($i+1) . '</strong>';

			if ($disable_controls == false) { 
				print '</a>'; 
			}

			print "</td>\n";

			print "<td style='width:1%;" . ((isset($item['hex'])) ? "background-color:#" . $item['hex'] . ";'" : "") . "></td>\n";

			print "<td style='font-weight:bold;'>" . $item['hex'] . "</td>\n";

			if ($disable_controls == false) {
				print "<td class='right' style='padding-right:10px;'>";
				if ($i != sizeof($item_list)-1) {
					print "<a class='pic fa fa-arrow-down moveArrow' href='" . htmlspecialchars($filename . "?action=item_movedown&color_template_item_id=" . $item['color_template_item_id'] . "&$url_data") . "' title='Move Down'></a>\n";
				}else{
					print "<span class='moveArrowNone'></span>\n";
				}

				if ($i > 0) {
					print "<a class='pic fa fa-arrow-up moveArrow' href='" . htmlspecialchars($filename . "?action=item_moveup&color_template_item_id=" . $item['color_template_item_id'] . "&$url_data") . "' title='Move Up'></a>\n";
				}else{
					print "<span class='moveArrowNone'></span>\n";
				}
				print "</td>\n";
			}

			if ($disable_controls == false) {
				print "<td class='right' style='width:2%;'><a class='pic deleteMarker fa fa-remove' href='" . htmlspecialchars($filename . "?action=item_remove&color_template_item_id=" . $item['color_template_item_id'] . "&$url_data") . "' title='Delete'></a></td>\n";
			}

			form_end_row();

			$i++;
		}
	}else{
		print "<tr><td colspan='7'><em>No Items</em></td></tr>";
	}
}

/* --------------------------
    The Save Function
   -------------------------- */
/**
 * aggregate_color_form_save	the save function
 */
function aggregate_color_form_save() {
	if (isset($_POST['save_component_color'])) {
		if (isset($_POST['color_template_id'])) {
			$save1['color_template_id'] = $_POST['color_template_id'];
		} else {
			$save1['color_template_id'] = 0;
		}

		$save1['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);

		cacti_log('Saved ID: ' . $save1['color_template_id'] . ' Name: ' . $save1['name'], FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

		if (!is_error_message()) {
			$color_template_id = sql_save($save1, 'color_templates', 'color_template_id');

			cacti_log('Saved ID: ' . $color_template_id, FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

			if ($color_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
	}

	header('Location: color_templates.php?header=false&action=template_edit&color_template_id=' . (empty($color_template_id) ? $_POST['color_template_id'] : $color_template_id));
}

/* ------------------------
    The 'actions' function
   ------------------------ */
/**
 * aggregate_color_form_actions		the action function
 */
function aggregate_color_form_actions() {
	global $aggregate_actions, $config;
	include_once($config['base_path'] . '/lib/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == '1') { /* delete */
				db_execute('DELETE FROM color_templates WHERE ' . array_to_sql_or($selected_items, 'color_template_id'));
				db_execute('DELETE FROM color_template_items WHERE ' . array_to_sql_or($selected_items, 'color_template_id'));
			}elseif ($_POST['drp_action'] == '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					duplicate_color_template($selected_items[$i], $_POST['title_format']);
				}
			}
		}

		header('Location: color_templates.php?header=false');
		exit;
	}

	/* setup some variables */
	$color_list = ''; $i = 0;

	/* loop through each of the color templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$color_list .= '<li>' . db_fetch_cell('SELECT name FROM color_templates WHERE color_template_id=' . $matches[1]) . '</li>';
			$color_array[] = $matches[1];
		}
	}

	top_header();

	form_start('color_templates.php');

	html_start_box($aggregate_actions{$_POST['drp_action']}, '60%', '', '3', 'center', '');

	if (isset($color_array) && sizeof($color_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to delete the following Color Template(s)?</p>
					<p><ul>$color_list</ul></p>
				</td>
			</tr>\n";
	
			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Color Template(s)'>";
		}elseif ($_POST['drp_action'] == '2') { /* duplicate */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to duplicate the following Color Template(s). You can
					optionally change the title format for the new color templates.</p>
					<p><ul>$color_list</ul></p>
					<p>Title Format:<br>"; form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";
	
			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Color Template(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one Color Template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($color_array) ? serialize($color_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/**
 * aggregate_color_item		show all color template items
 */
function aggregate_color_item() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('color_template_id'));
	/* ==================================================== */

	if (empty($_GET['color_template_id'])) {
		$template_item_list = array();

		$header_label = '[new]';
	}else{
		$template_item_list = db_fetch_assoc('SELECT
			cti.color_template_item_id, cti.sequence, colors.hex
			FROM color_template_items AS cti
			LEFT JOIN colors 
			ON cti.color_id=colors.id
			WHERE cti.color_template_id=' . $_GET['color_template_id'] . '
			ORDER BY cti.sequence ASC');

		$header_label = '[edit: ' . db_fetch_cell('SELECT name FROM color_templates WHERE color_template_id=' . $_GET['color_template_id']) . ']';
	}

	html_start_box("Color Template Items $header_label", '100%', '', '3', 'center', 'color_templates_items.php?action=item_edit&color_template_id=' . htmlspecialchars($_GET['color_template_id']));

	draw_color_template_items_list($template_item_list, 'color_templates_items.php', 'color_template_id=' . htmlspecialchars($_GET['color_template_id']), false);

	html_end_box();
}

/* ----------------------------
    template - Color Templates
   ---------------------------- */
/**
 * aggregate_color_template_edit	edit the color template
 */
function aggregate_color_template_edit() {
	global $config, $image_types, $fields_color_template_template_edit, $struct_aggregate;
	include_once($config['base_path'] . '/lib/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('color_template_id'));
	/* ==================================================== */
	if (!empty($_GET['color_template_id'])) {
		$template = db_fetch_row('SELECT * FROM color_templates WHERE color_template_id=' . $_GET['color_template_id']);
		$header_label = '[edit: ' . $template['name'] . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('color_templates.php', 'color_template_edit');

	html_start_box("Color Template $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_color_template_template_edit, (isset($template) ? $template : array()))
		));

	html_end_box();
	form_hidden_box('color_template_id', (isset($template['color_template_id']) ? $template['color_template_id'] : '0'), '');
	form_hidden_box('save_component_color', '1', '');

	/* color item list goes here */
	if (!empty($_GET['color_template_id'])) {
		aggregate_color_item();
	}

	form_save_button('color_templates.php', 'return', 'color_template_id');
}


/**
 * aggregate_color_template		maintain color templates
 */
function aggregate_color_template() {
	global $aggregate_actions, $item_rows, $config;
	include_once($config['base_path'] . '/lib/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('page'));
	input_validate_input_number(get_request_var('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up search string */
	if (isset($_REQUEST['has_graphs'])) {
		$_REQUEST['has_graphs'] = sanitize_search_string(get_request_var('has_graphs'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	} else {
		$_REQUEST['sort_column'] = 'name';
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	} else {
		$_REQUEST['sort_direction'] = 'ASC';
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_color_template_current_page');
		kill_session_var('sess_color_template_filter');
		kill_session_var('sess_color_template_has_graphs');
		kill_session_var('sess_color_template_sort_column');
		kill_session_var('sess_color_template_sort_direction');
		kill_session_var('sess_default_rows');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['has_graphs']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['sess_default_rows']);
	}else{
		$changed = 0;
		$changed += check_changed('has_graphs', 'sess_color_template_has_graphs');
		$changed += check_changed('rows',       'sess_default_rows');
		$changed += check_changed('filter',     'sess_color_template_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_color_template_current_page', '1');
	load_current_session_value('filter', 'sess_color_template_filter', '');
	load_current_session_value('has_graphs', 'sess_color_template_has_graphs', '');
	load_current_session_value('sort_column', 'sess_color_template_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_color_template_sort_direction', 'ASC');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	print ('<form id="form_template" action="color_templates.php" method="get">');

	html_start_box('Color Templates', '100%', '', '3', 'center', 'color_templates.php?action=template_edit');

	$filter_html = '<tr class="even">
					<td>
					<table class="filterTable">
						<tr>
							<td>
								Search
							</td>
							<td>
								<input type="text" id="filter" size="25" value="' . get_request_var('filter') . '">
							</td>
							<td>
								Color Templates
							</td>
							<td>
								<select id="rows" onChange="applyFilter()">
								<option value="-1"';
	if (get_request_var('rows') == '-1') {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Default</option>';
	if (sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";
			if (get_request_var('rows') == $key) {
				$filter_html .= ' selected';
			}
			$filter_html .= '>' . $value . "</option>\n";
		}
	}
	$filter_html .= '					</select>
							</td>
							<td>
								<input type="checkbox" id="has_graphs" ' . ($_REQUEST['has_graphs'] == 'true' ? 'checked':'') . ' onChange="applyFilter()">
							</td>
							<td>
								<label for="has_graphs">Has Graphs</label>
							</td>
							<td>
								<input type="button" id="refresh" value="Go">
							</td>
							<td>
								<input type="button" id="clear" value="Clear">
							</td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" id="page" value="' . $_REQUEST['page'] . '"></td>
				</tr>';

	print $filter_html;

	html_end_box();

	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (ct.name LIKE '%%" . $_REQUEST['filter'] . "%%')";
	}

	if ($_REQUEST['has_graphs'] == 'true') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (templates>0 OR graphs>0)';
	}

	form_start('color_templates.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(ct.color_template_id)
		FROM color_templates AS ct
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS templates 
			FROM aggregate_graph_templates_item 
			GROUP BY color_template
		) AS templates
		ON ct.color_template_id=templates.color_template
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS graphs
			FROM aggregate_graphs_graph_item
			GROUP BY color_template
		) AS graphs
		ON ct.color_template_id=graphs.color_template
		$sql_where");

	$template_list = db_fetch_assoc("SELECT
		ct.color_template_id, ct.name, templates.templates, graphs.graphs
		FROM color_templates AS ct
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS templates 
			FROM aggregate_graph_templates_item 
			GROUP BY color_template
		) AS templates
		ON ct.color_template_id=templates.color_template
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS graphs
			FROM aggregate_graphs_graph_item
			GROUP BY color_template
		) AS graphs
		ON ct.color_template_id=graphs.color_template
		$sql_where
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction'] .
		' LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('color_templates.php', MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 5, 'Color Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('Template Title', 'ASC'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Color Templates that are in use can not be Deleted.  In use is defined as being referenced by an Aggregate Template.'),
		'graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC'),
		'templates' => array('display' => 'Templates', 'align' => 'right', 'sort' => 'DESC')
	);

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			if ($template['templates'] > 0) {
				$disabled = true;
			}else{
				$disabled = false;
			}

			form_alternate_row('line' . $template['color_template_id'], true);

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('color_templates.php?action=template_edit&color_template_id=' . $template['color_template_id'] . '&page=1') . "'>" . (get_request_var('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['name'])) : htmlspecialchars($template['name'])) . '</a>', $template['color_template_id']);
			form_selectable_cell($disabled ? 'No':'Yes', $template['color_template_id'], '', 'text-align:right');
            form_selectable_cell(number_format($template['graphs']), $template['color_template_id'], '', 'text-align:right;');
            form_selectable_cell(number_format($template['templates']), $template['color_template_id'], '', 'text-align:right;');
			form_checkbox_cell($template['name'], $template['color_template_id'], $disabled);
			form_end_row();
		}
		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Color Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($aggregate_actions);

	form_end();

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'color_templates.php';
		strURL += '?rows=' + $('#rows').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&has_graphs=' + $('#has_graphs').is(':checked');
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'color_templates.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#filter').change(function() {
			applyFilter();
		});

		$('#form_template').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php
}

