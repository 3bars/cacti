<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_aggregate.php');
include_once('./lib/template.php');
include_once('./lib/html_tree.php');
include_once('./lib/html_form_template.php');
include_once('./lib/rrd.php');
include_once('./lib/data_query.php');

define('MAX_DISPLAY_PAGES', 21);

$graph_actions = array(
	1 => 'Delete',
	2 => 'Change Graph Template',
	5 => 'Change Device',
	6 => 'Reapply Suggested Names',
	7 => 'Resize Graphs',
	3 => 'Duplicate',
	4 => 'Convert to Graph Template',
    'aggregate' => 'Create Aggregate Graph',
    'aggregate_template' => 'Create Aggregate from Template',
	8 => 'Apply Automation Rules to Graph(s)'
);

$graph_actions = api_plugin_hook_function('graphs_action_array', $graph_actions);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'graph_diff':
		top_header();

		graph_diff();

		bottom_footer();
		break;
	case 'item':
		top_header();

		item();

		bottom_footer();
		break;
	case 'graph_remove':
		graph_remove();

		header('Location: graphs.php');
		break;
	case 'ajax_hosts':
		ajax_hosts();

		break;
	case 'ajax_hosts_noany':
		ajax_hosts(false);

		break;
	case 'lock':
	case 'unlock':
		$_SESSION['sess_graph_lock_id'] = $_REQUEST['id'];
		$_SESSION['sess_graph_locked']  = ($_REQUEST['action'] == 'lock' ? true:false);
	case 'graph_edit':
		top_header();

		graph_edit();

		bottom_footer();
		break;
	default:
		top_header();

		graph();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function ajax_hosts($include_any = true, $include_none = true) {
	$return    = array();
	$term      = $_REQUEST['term'];
	$sql_where = "hostname LIKE '%$term%' OR description LIKE '%$term%' OR notes LIKE '%$term%'";
	$hosts     = get_allowed_devices($sql_where, 'description', 30);

	if ($_REQUEST['term'] == '') {
		if ($include_any) {
			$return[] = array('label' => 'Any', 'value' => 'Any', 'id' => '-1');
		}
		if ($include_none) {
			$return[] = array('label' => 'None', 'value' => 'None', 'id' => '0');
		}
	}

	if (sizeof($hosts)) {
	foreach($hosts as $host) {
		$return[] = array('label' => $host['description'], 'value' => $host['description'], 'id' => $host['id']);
	}
	}

	print json_encode($return);
}

function add_tree_names_to_actions_array() {
	global $graph_actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

	if (sizeof($trees) > 0) {
	foreach ($trees as $tree) {
		$graph_actions{'tr_' . $tree['id']} = 'Place on a Tree (' . $tree['name'] . ')';
	}
	}
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST['save_component_graph_new'])) && (!empty($_POST['graph_template_id']))) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('graph_template_id'));
		/* ==================================================== */

		$save['id'] = $_POST['local_graph_id'];
		$save['graph_template_id'] = $_POST['graph_template_id'];
		$save['host_id'] = $_POST['host_id'];

		$local_graph_id = sql_save($save, 'graph_local');

		change_graph_template($local_graph_id, $_POST['graph_template_id'], true);

		/* update the title cache */
		update_graph_title_cache($local_graph_id);
	}

	if (isset($_POST['save_component_graph'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('graph_template_id'));
		input_validate_input_number(get_request_var_post('_graph_template_id'));
		/* ==================================================== */

		$save1['id'] = $_POST['local_graph_id'];
		$save1['host_id'] = $_POST['host_id'];
		$save1['graph_template_id'] = $_POST['graph_template_id'];

		$save2['id'] = $_POST['graph_template_graph_id'];
		$save2['local_graph_template_graph_id'] = $_POST['local_graph_template_graph_id'];
		$save2['graph_template_id'] = $_POST['graph_template_id'];
		$save2['image_format_id'] = form_input_validate($_POST['image_format_id'], 'image_format_id', '', true, 3);
		$save2['title'] = form_input_validate($_POST['title'], 'title', '', false, 3);
		$save2['height'] = form_input_validate($_POST['height'], 'height', '^[0-9]+$', false, 3);
		$save2['width'] = form_input_validate($_POST['width'], 'width', '^[0-9]+$', false, 3);
		$save2['upper_limit'] = form_input_validate($_POST['upper_limit'], 'upper_limit', "^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$", ((strlen($_POST['upper_limit']) === 0) ? true : false), 3);
		$save2['lower_limit'] = form_input_validate($_POST['lower_limit'], 'lower_limit', "^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$", ((strlen($_POST['lower_limit']) === 0) ? true : false), 3);
		$save2['vertical_label'] = form_input_validate($_POST['vertical_label'], 'vertical_label', '', true, 3);
		$save2['slope_mode'] = form_input_validate((isset($_POST['slope_mode']) ? $_POST['slope_mode'] : ''), 'slope_mode', '', true, 3);
		$save2['auto_scale'] = form_input_validate((isset($_POST['auto_scale']) ? $_POST['auto_scale'] : ''), 'auto_scale', '', true, 3);
		$save2['auto_scale_opts'] = form_input_validate($_POST['auto_scale_opts'], 'auto_scale_opts', '', true, 3);
		$save2['auto_scale_log'] = form_input_validate((isset($_POST['auto_scale_log']) ? $_POST['auto_scale_log'] : ''), 'auto_scale_log', '', true, 3);
		$save2['scale_log_units'] = form_input_validate((isset($_POST['scale_log_units']) ? $_POST['scale_log_units'] : ''), 'scale_log_units', '', true, 3);
		$save2['auto_scale_rigid'] = form_input_validate((isset($_POST['auto_scale_rigid']) ? $_POST['auto_scale_rigid'] : ''), 'auto_scale_rigid', '', true, 3);
		$save2['auto_padding'] = form_input_validate((isset($_POST['auto_padding']) ? $_POST['auto_padding'] : ''), 'auto_padding', '', true, 3);
		$save2['base_value'] = form_input_validate($_POST['base_value'], 'base_value', '^[0-9]+$', false, 3);
		$save2['export'] = form_input_validate((isset($_POST['export']) ? $_POST['export'] : ''), 'export', '', true, 3);
		$save2['unit_value'] = form_input_validate($_POST['unit_value'], 'unit_value', '', true, 3);
		$save2['unit_exponent_value'] = form_input_validate($_POST['unit_exponent_value'], 'unit_exponent_value', '^-?[0-9]+$', true, 3);

		if (!is_error_message()) {
			$local_graph_id = sql_save($save1, 'graph_local');
		}

		if (!is_error_message()) {
			$save2['local_graph_id'] = $local_graph_id;
			$graph_templates_graph_id = sql_save($save2, 'graph_templates_graph');

			if ($graph_templates_graph_id) {
				raise_message(1);

				/* if template information chanegd, update all necessary template information */
				if ($_POST['graph_template_id'] != $_POST['_graph_template_id']) {
					/* check to see if the number of graph items differs, if it does; we need user input */
					if ((!empty($_POST['graph_template_id'])) && (!empty($_POST['local_graph_id'])) && (sizeof(db_fetch_assoc_prepared('SELECT id FROM graph_templates_item WHERE local_graph_id = ?', array($local_graph_id))) != sizeof(db_fetch_assoc_prepared('SELECT id from graph_templates_item WHERE local_graph_id = 0 AND graph_template_id = ?', array($_POST['graph_template_id']))))) {
						/* set the template back, since the user may choose not to go through with the change
						at this point */
						db_execute_prepared('UPDATE graph_local SET graph_template_id = ? WHERE id = ?', array($_POST['_graph_template_id'], $local_graph_id));
						db_execute_prepared('UPDATE graph_templates_graph SET graph_template_id = ? WHERE local_graph_id = ?', array($_POST['_graph_template_id'], $local_graph_id));

						header('Location: graphs.php?action=graph_diff&id=$local_graph_id&graph_template_id=' . $_POST['graph_template_id']);
						exit;
					}
				}
			}else{
				raise_message(2);
			}

			/* update the title cache */
			update_graph_title_cache($local_graph_id);
		}

		if ((!is_error_message()) && ($_POST['graph_template_id'] != $_POST['_graph_template_id'])) {
			change_graph_template($local_graph_id, $_POST['graph_template_id'], true);
		}elseif (!empty($_POST['graph_template_id'])) {
			update_graph_data_query_cache($local_graph_id);
		}
	}

	if (isset($_POST['save_component_input'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('local_graph_id'));
		/* ==================================================== */

		/* first; get the current graph template id */
		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id FROM graph_local WHERE id = ?', array($_POST['local_graph_id']));

		/* get all inputs that go along with this graph template, if templated */
		if ($graph_template_id > 0) {
			$input_list = db_fetch_assoc_prepared('SELECT id, column_name FROM graph_template_input WHERE graph_template_id = ?', array($graph_template_id));
			
			if (sizeof($input_list) > 0) {
				foreach ($input_list as $input) {
					/* we need to find out which graph items will be affected by saving this particular item */
					$item_list = db_fetch_assoc_prepared('SELECT
						graph_templates_item.id
						FROM (graph_template_input_defs, graph_templates_item)
						WHERE graph_template_input_defs.graph_template_item_id = graph_templates_item.local_graph_template_item_id
						AND graph_templates_item.local_graph_id = ?
						AND graph_template_input_defs.graph_template_input_id = ?', array($_POST['local_graph_id'], $input['id']));
					
					/* loop through each item affected and update column data */
					if (sizeof($item_list) > 0) {
						foreach ($item_list as $item) {
							/* if we are changing templates, the POST vars we are searching for here will not exist.
							 this is because the db and form are out of sync here, but it is ok to just skip over saving
							 the inputs in this case. */
							if (isset($_POST{$input['column_name'] . '_' . $input['id']})) {
								db_execute_prepared('UPDATE graph_templates_item SET ' . $input['column_name'] . ' = ? WHERE id = ?', array($_POST{$input['column_name'] . '_' . $input['id']}, $item['id']));
							}
						}
					}
				}
			}
		}
	}

	if (isset($_POST['save_component_graph_diff'])) {
		if ($_POST['type'] == '1') {
			$intrusive = true;
		}elseif ($_POST['type'] == '2') {
			$intrusive = false;
		}

		change_graph_template($_POST['local_graph_id'], $_POST['graph_template_id'], $intrusive);
	}

	if ((isset($_POST['save_component_graph_new'])) && (empty($_POST['graph_template_id']))) {
		header('Location: graphs.php?action=graph_edit&host_id=' . $_POST['host_id'] . '&new=1');
	}elseif ((is_error_message()) || (empty($_POST['local_graph_id'])) || (isset($_POST['save_component_graph_diff'])) || ($_POST['graph_template_id'] != $_POST['_graph_template_id']) || ($_POST['host_id'] != $_POST['_host_id'])) {
		header('Location: graphs.php?action=graph_edit&id=' . (empty($local_graph_id) ? $_POST['local_graph_id'] : $local_graph_id) . (isset($_POST['host_id']) ? '&host_id=' . $_POST['host_id'] : ''));
	}else{
		header('Location: graphs.php');
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $graph_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			if (!isset($_POST['delete_type'])) { $_POST['delete_type'] = 1; }

			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
			}

			switch ($_POST['delete_type']) {
				case '2': /* delete all data sources referenced by this graph */
					$data_sources = array_rekey(db_fetch_assoc('SELECT data_template_data.local_data_id
						FROM (data_template_rrd, data_template_data, graph_templates_item)
						WHERE graph_templates_item.task_item_id=data_template_rrd.id
						AND data_template_rrd.local_data_id=data_template_data.local_data_id
						AND ' . array_to_sql_or($selected_items, 'graph_templates_item.local_graph_id') . '
						AND data_template_data.local_data_id > 0'), 'local_data_id', 'local_data_id');

					if (sizeof($data_sources)) {
						api_data_source_remove_multi($data_sources);
						api_plugin_hook_function('data_source_remove', $data_sources);
					}

					break;
			}

			api_graph_remove_multi($selected_items);

			api_plugin_hook_function('graphs_remove', $selected_items);
		}elseif ($_POST['drp_action'] == '2') { /* change graph template */
			input_validate_input_number(get_request_var_post('graph_template_id'));
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				change_graph_template($selected_items[$i], $_POST['graph_template_id'], true);
			}
		}elseif ($_POST['drp_action'] == '3') { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				duplicate_graph($selected_items[$i], 0, $_POST['title_format']);
			}
		}elseif ($_POST['drp_action'] == '4') { /* graph -> graph template */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				graph_to_graph_template($selected_items[$i], $_POST['title_format']);
			}
		}elseif (preg_match('/^tr_([0-9]+)$/', $_POST['drp_action'], $matches)) { /* place on tree */
			input_validate_input_number(get_request_var_post('tree_id'));
			input_validate_input_number(get_request_var_post('tree_item_id'));
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_tree_item_save(0, $_POST['tree_id'], TREE_ITEM_TYPE_GRAPH, $_POST['tree_item_id'], '', $selected_items[$i], read_graph_config_option('default_rra_id'), 0, 0, 0, false);
			}
		}elseif ($_POST['drp_action'] == '5') { /* change host */
			input_validate_input_number(get_request_var_post('host_id'));
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute_prepared('UPDATE graph_local SET host_id = ? WHERE id = ?', array($_POST['host_id'], $selected_items[$i]));
				update_graph_title_cache($selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == '6') { /* reapply suggested naming */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_reapply_suggested_graph_title($selected_items[$i]);
				update_graph_title_cache($selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == '7') { /* resize graphs */
			input_validate_input_number(get_request_var_post('graph_width'));
			input_validate_input_number(get_request_var_post('graph_height'));
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_resize_graphs($selected_items[$i], $_POST['graph_width'], $_POST['graph_height']);
			}
		}elseif ($_POST['drp_action'] == 'aggregate' || $_POST['drp_action'] == 'aggregate_template') {
			if (!isset($_POST['selected_items']) || sizeof($_POST['selected_items']) < 1) {
				return null;
			}

			/* get common info - not dependant on template/no template*/
			$local_graph_id = 0; // this will be a new graph
			$member_graphs  = unserialize(stripslashes($_POST['selected_items']));
			$graph_title    = sql_sanitize(form_input_validate(htmlspecialchars($_POST['title_format']), 'title_format', '', true, 3));

			/* future aggregate_graphs entry */
			$ag_data = array();
			$ag_data['id'] = 0;
			$ag_data['title_format'] = $graph_title;
			$ag_data['user_id']      = $_SESSION['sess_user_id'];

			if ($_POST['drp_action'] == 'aggregate') {
				if (!isset($_POST['aggregate_total_type']))   $_POST['aggregate_total_type']   = 0;
				if (!isset($_POST['aggregate_total']))        $_POST['aggregate_total']        = 0;
				if (!isset($_POST['aggregate_total_prefix'])) $_POST['aggregate_total_prefix'] = '';
				if (!isset($_POST['aggregate_order_type']))   $_POST['aggregate_order_type']   = 0;
	
				$item_no = form_input_validate(htmlspecialchars($_POST['item_no']), 'item_no', '^[0-9]+$', true, 3);

				$ag_data['aggregate_template_id'] = 0;
				$ag_data['template_propogation']  = '';
				$ag_data['graph_template_id']     = form_input_validate(htmlspecialchars($_POST['graph_template_id']), 'graph_template_id', '^[0-9]+$', true, 3);
				$ag_data['gprint_prefix']         = sql_sanitize(form_input_validate(htmlspecialchars($_POST['gprint_prefix']), 'gprint_prefix', '', true, 3));
				$ag_data['graph_type']            = form_input_validate(htmlspecialchars($_POST['aggregate_graph_type']), 'aggregate_graph_type', '^[0-9]+$', true, 3);
				$ag_data['total']                 = form_input_validate(htmlspecialchars($_POST['aggregate_total']), 'aggregate_total', '^[0-9]+$', true, 3);
				$ag_data['total_type']            = form_input_validate(htmlspecialchars($_POST['aggregate_total_type']), 'aggregate_total_type', '^[0-9]+$', true, 3);
				$ag_data['total_prefix']          = form_input_validate(htmlspecialchars($_POST['aggregate_total_prefix']), 'aggregate_total_prefix', '', true, 3);
				$ag_data['order_type']            = form_input_validate(htmlspecialchars($_POST['aggregate_order_type']), 'aggregate_order_type', '^[0-9]+$', true, 3);
			} else {
				$template_data = db_fetch_row('SELECT * FROM aggregate_graph_templates WHERE id=' . $_POST['aggregate_template_id']);

				$item_no = db_fetch_cell('SELECT COUNT(*) FROM aggregate_graph_templates_item WHERE aggregate_template_id=' . $_POST['aggregate_template_id']);

				$ag_data['aggregate_template_id'] = $_POST['aggregate_template_id'];
				$ag_data['template_propogation']  = 'on';
				$ag_data['graph_template_id']     = $template_data['graph_template_id'];
				$ag_data['gprint_prefix']         = $template_data['gprint_prefix'];
				$ag_data['graph_type']            = $template_data['graph_type'];
				$ag_data['total']                 = $template_data['total'];
				$ag_data['total_type']            = $template_data['total_type'];
				$ag_data['total_prefix']          = $template_data['total_prefix'];
				$ag_data['order_type']            = $template_data['order_type'];
			}

			/* create graph in cacti tables */
			$local_graph_id = aggregate_graph_save(
				$local_graph_id,
				$ag_data['graph_template_id'],
				$graph_title,
				$ag_data['aggregate_template_id']
			);

			$ag_data['local_graph_id'] = $local_graph_id;
			$aggregate_graph_id = sql_save($ag_data, 'aggregate_graphs');
			$ag_data['aggregate_graph_id'] = $aggregate_graph_id;

			// 	/* save member graph info */
			// 	$i = 1;
			// 	foreach($member_graphs as $graph_id) {
			// 		db_execute("INSERT INTO aggregate_graphs_items 
			// 			(aggregate_graph_id, local_graph_id, sequence) 
			// 			VALUES
			// 			($aggregate_graph_id, $graph_id, $i)"
			// 		);
			// 		$i++;
			// 	}

			/* save aggregate graph graph items */
			if ($_POST['drp_action'] == 'aggregate') {
				/* get existing item ids and sequences from graph template */
				$graph_templates_items = array_rekey(
					db_fetch_assoc('SELECT id, sequence FROM graph_templates_item WHERE local_graph_id=0 AND graph_template_id=' . $ag_data['graph_template_id']),
					'id', array('sequence')
				);

				/* update graph template item values with posted values */
				aggregate_validate_graph_items($_POST, $graph_templates_items);

				$aggregate_graph_items = array();
				foreach ($graph_templates_items as $item_id => $data) {
					$item_new = array();
					$item_new['aggregate_graph_id'] = $aggregate_graph_id;
					$item_new['graph_templates_item_id'] = $item_id;

					$item_new['color_template'] = isset($data['color_template']) ? $data['color_template']:-1;
					$item_new['item_skip']      = isset($data['item_skip']) ? 'on':'';
					$item_new['item_total']     = isset($data['item_total']) ? 'on':'';
					$item_new['sequence']       = isset($data['sequence']) ? $data['sequence']:-1;

					$aggregate_graph_items[] = $item_new;
				}

				aggregate_graph_items_save($aggregate_graph_items, 'aggregate_graphs_graph_item');
			} else {
				$aggregate_graph_items = db_fetch_assoc('SELECT * FROM aggregate_graph_templates_item WHERE aggregate_template_id=' . $ag_data['aggregate_template_id']);
			}

			$attribs = $ag_data;
			$attribs['graph_title'] = $ag_data['title_format'];
			$attribs['reorder'] = $ag_data['order_type'];
			$attribs['item_no'] = $item_no;
			$attribs['color_templates'] = array();
			$attribs['skipped_items']   = array();
			$attribs['total_items']     = array();
			$attribs['graph_item_types']= array();
			$attribs['cdefs']           = array();
			foreach ($aggregate_graph_items as $item) {
				if (isset($item['color_template']) && $item['color_template'] > 0)
					$attribs['color_templates'][ $item['sequence'] ] = $item['color_template'];

				if (isset($item['item_skip']) && $item['item_skip'] == 'on')
					$attribs['skipped_items'][ $item['sequence'] ] = $item['sequence'];

				if (isset($item['item_total']) && $item['item_total'] == 'on')
					$attribs['total_items'][ $item['sequence'] ] = $item['sequence'];

				if (isset($item['cdef_id']) && isset($item['t_cdef_id']) && $item['t_cdef_id'] == 'on')
					$attribs['cdefs'][ $item['sequence'] ] = $item['cdef_id'];

				if (isset($item['graph_type_id']) && isset($item['t_graph_type_id']) && $item['t_graph_type_id'] == 'on')
					$attribs['graph_item_types'][ $item['sequence'] ] = $item['graph_type_id'];
			}

			/* create actual graph items */
			aggregate_create_update($local_graph_id, $member_graphs, $attribs);

			header("Location: aggregate_graphs.php?action=edit&tab=details&id=$local_graph_id");
			exit;
		}elseif ($action == 8) { /* automation */
			cacti_log('automation_graph_action_execute called: ' . $action, true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

			/* find out which (if any) hosts have been checked, so we can tell the user */
			if (isset($_POST['selected_items'])) {
				$selected_items = unserialize(stripslashes($_POST['selected_items']));

				/* work on all selected graphs */
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					/* now handle tree rules for that graph */
					execute_graph_create_tree($selected_items[$i]);
				}
			}
		} else {
			api_plugin_hook_function('graphs_action_execute', $_POST['drp_action']);
		}

		/* update snmpcache */
		snmpagent_graphs_action_bottom(array($_POST['drp_action'], $selected_items));
		api_plugin_hook_function('graphs_action_bottom', array($_POST['drp_action'], $selected_items));

		header('Location: graphs.php');
		exit;
	}

	/* setup some variables */
	$graph_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$graph_list .= '<li>' . htmlspecialchars(get_graph_title($matches[1])) . '</li>';
			$graph_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	html_start_box('<strong>' . $graph_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	print "<form action='graphs.php' method='post'>\n";

	if (isset($graph_array) && sizeof($graph_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			$graphs = array();

			/* find out which (if any) data sources are being used by this graph, so we can tell the user */
			if (isset($graph_array) && sizeof($graph_array)) {
				$data_sources = db_fetch_assoc('select
					data_template_data.local_data_id,
					data_template_data.name_cache
					from (data_template_rrd,data_template_data,graph_templates_item)
					where graph_templates_item.task_item_id=data_template_rrd.id
					and data_template_rrd.local_data_id=data_template_data.local_data_id
					and ' . array_to_sql_or($graph_array, 'graph_templates_item.local_graph_id') . '
					and data_template_data.local_data_id > 0
					group by data_template_data.local_data_id
					order by data_template_data.name_cache');
			}

			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Graph(s) will be deleted.  Please note, Data Source(s) should be deleted only if they are only used by these Graph(s)
						and not others.</p>
						<p><ul>$graph_list</ul></p>";

						if (isset($data_sources) && sizeof($data_sources)) {
							print "<tr><td class='textArea'><p>The following Data Source(s) are in use by these Graph(s):</p>\n";

							print '<ul>';
							foreach ($data_sources as $data_source) {
								print '<li><strong>' . $data_source['name_cache'] . "</strong></li>\n";
							}
							print '</ul>';

							print '<br>';
							form_radio_button('delete_type', '1', '2', "Leave the Data Source(s) untouched.  Not applicable for Graphs created under 'New Graphs' or WHERE the Graphs were created automatically.", '2'); print '<br>';
							form_radio_button('delete_type', '2', '2', 'Delete all <strong>Data Source(s)</strong> referenced by these Graph(s).', '2'); print '<br>';
							print '</td></tr>';
						}
					print "
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Graph(s)'>";
		}elseif ($_POST['drp_action'] == '2') { /* change graph template */
			print "	<tr>
					<td class='textArea'>
						<p>Choose a Graph Template and click \"Continue\" to change the Graph Template for
						the following Graph(s). Be aware that all warnings will be suppressed during the
						conversion, so Graph data loss is possible.</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>New Graph Template:</strong><br>"; form_dropdown('graph_template_id',db_fetch_assoc('SELECT graph_templates.id,graph_templates.name FROM graph_templates ORDER BY name'),'name','id','','','0'); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Change Graph Template'>";
		}elseif ($_POST['drp_action'] == '3') { /* duplicate */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Graph(s) will be duplicated. You can
						optionally change the title format for the new Graph(s).</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>Title Format:</strong><br>"; form_text_box('title_format', '<graph_title> (1)', '', '255', '30', 'text'); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Graph(s)'>";
		}elseif ($_POST['drp_action'] == '4') { /* graph -> graph template */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Graph(s) will be converted into Graph Template(s).
						You can optionally change the title format for the new Graph Template(s).</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>Title Format:</strong><br>"; form_text_box('title_format', '<graph_title> Template', '', '255', '30', 'text'); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Convert to Graph Template'>";
		}elseif (preg_match('/^tr_([0-9]+)$/', $_POST['drp_action'], $matches)) { /* place on tree */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Graph(s) will be placed under the Tree Branch selected below.</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>Destination Branch:</strong><br>"; grow_dropdown_tree($matches[1], '0', 'tree_item_id', '0'); print "</p>
					</td>
				</tr>\n
				<input type='hidden' name='tree_id' value='" . $matches[1] . "'>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Place Graph(s) on Tree'>";
		}elseif ($_POST['drp_action'] == '5') { /* change host */
			print "	<tr>
					<td class='textArea'>
						<p>Choose a new Device for these Graph(s) and click \"Continue\"</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>New Device:</strong><br>"; form_dropdown('host_id',db_fetch_assoc("SELECT id,CONCAT_WS('',description,' (',hostname,')') as name FROM host ORDER BY description,hostname"),'name','id','','','0'); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Change Graph(s) Associated Device'>";
		}elseif ($_POST['drp_action'] == '6') { /* reapply suggested naming to host */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Graph(s) will have thier suggested naming convensions
						recalculated and applied to the Graph(s).</p>
						<p><ul>$graph_list</ul></p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Reapply Suggested Naming to Graph(s)'>";
		}elseif ($_POST['drp_action'] == '7') { /* resize graphs */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Graph(s) will be resized per your specifications.</p>
						<p><ul>$graph_list</ul></p>
						<p><strong>Graph Height:</strong><br>"; form_text_box('graph_height', '', '', '255', '30', 'text'); print '</p>
						<p><strong>Graph Width:</strong><br>'; form_text_box('graph_width', '', '', '255', '30', 'text'); print "</p>
					</td>
				</tr>\n
				";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Resize Selected Graph(s)'>";
		} elseif ($_POST['drp_action'] == 'aggregate') {
			include_once('./lib/api_aggregate.php');

			cacti_log(__FUNCTION__ . '  called. Parameters: ' . serialize($_POST), true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

			/* initialize return code and graphs array */
			$return_code    = false;
			$graphs         = array();
			$data_sources   = array();
			$graph_template = '';

			if (aggregate_get_data_sources($_POST, $data_sources, $graph_template)) {
				html_end_box();

				# provide a new prefix for GPRINT lines
				$gprint_prefix = '|host_hostname|';

				# open a new html_start_box ...
				html_start_box('', '100%', '', '3', 'center', '');

				/* list affected graphs */
				print '<tr>';
				print "<td class='textArea'>
					<p>Are you sure you want to aggregate the following graphs?</p>
					<ul>" . $_POST['graph_list'] . "</ul>
				</td>\n";

				/* list affected data sources */
				if (sizeof($data_sources) > 0) {
					print "<td class='textArea'>" .
					'<p>The following data sources are in use by these graphs:</p>
						<ul>';
					foreach ($data_sources as $data_source) {
						print '<li>' . $data_source['name_cache'] . "</li>\n";
					}
					print "</ul></td>\n";
				}
				print "</tr>\n";

				/* aggregate form */
				$_aggregate_defaults = array(
					'title_format' 	=> auto_title($_POST['graph_array'][0]),
					'graph_template_id' => $graph_template, 
					'gprint_prefix'	=> $gprint_prefix
				);

				draw_edit_form(array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($struct_aggregate, $_aggregate_defaults)
				));

				html_end_box();

				# draw all graph items of first graph, including a html_start_box
				draw_aggregate_graph_items_list(0, $graph_template);

				# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
				html_start_box('<strong>Please confirm</strong>', '100%', '', '3', 'center', '');

				?>
				<script type='text/javascript'>
				<!--
				function changeTotals() {
					switch ($('#aggregate_total').val()) {
						case '<?php print AGGREGATE_TOTAL_NONE;?>':
							$('#aggregate_total_type').prop('disabled', true);
							$('#aggregate_total_prefix').prop('disabled', true);
							$('#aggregate_order_type').prop('disabled', false);
							break;
						case '<?php print AGGREGATE_TOTAL_ALL;?>':
							$('#aggregate_total_type').prop('disabled', false);
							$('#aggregate_total_prefix').prop('disabled', false);
							$('#aggregate_order_type').prop('disabled', false);
							changeTotalsType();
							break;
						case '<?php print AGGREGATE_TOTAL_ONLY;?>':
							$('#aggregate_total_type').prop('disabled', false);
							$('#aggregate_total_prefix').prop('disabled', false);
							$('#aggregate_order_type').prop('disabled', true);
							changeTotalsType();
							break;
					}
				}

				function changeTotalsType() {
					if (($('#aggregate_total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR;?>)) {
						$('#aggregate_total_prefix').attr('value', 'Total');
					} else if (($('#aggregate_total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_ALL;?>)) {
						$('#aggregate_total_prefix').attr('value', 'All Items');
					}
				}

				$().ready(function() {
					$('#aggregate_total').change(function() {
						changeTotals();
					});

					$('#aggregate_total_type').change(function() {
						changeTotalsType();
					});

					changeTotals();
				});
				-->
				</script>
				<?php
			}
		}elseif ($_POST['drp_action'] == 'aggregate_template') { /* aggregate template */
			include_once('./lib/api_aggregate.php');

			cacti_log(__FUNCTION__ . '  called. Parameters: ' . serialize($_POST), true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

			/* initialize return code and graphs array */
			$graphs         = array();
			$data_sources   = array();
			$graph_template = '';

			/* find out which (if any) data sources are being used by this graph, so we can tell the user */
			if (aggregate_get_data_sources($graph_array, $data_sources, $graph_template)) {
				$aggregate_templates = db_fetch_assoc("SELECT id, name FROM aggregate_graph_templates WHERE graph_template_id=$graph_template ORDER BY name");

				if (sizeof($aggregate_templates)) {
					/* list affected graphs */
					print "<tr>
						<td class='textArea'>
							<p>Select the Aggregate Template to use and press 'Continue' to create your Aggregate Graph.  Otherwise press 'Cancel' to return.</p>
							<ul>" . $graph_list . "</ul>
						</td>
					</tr>\n";

					print "<tr><td><table><tr>
						<td><strong>Graph Title:</strong></td>
						<td><input name='title_format' size='40'></td>
						</tr>
						<tr>
							<td><strong>Aggregate Template:</strong></td>
							<td>
								<select name='aggregate_template_id'>\n";
									html_create_list($aggregate_templates, 'name', 'id', $aggregate_templates[0]['id']);
						print "</select>
							</td>
						</tr></table></td></tr>\n";

					$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Create Aggregate'>";

					# now everything is fine
				}else{
					/* present an error message as there are no templates */
					print "<tr>
						<td colspan='2' class='textArea'>
							<p>There are presently no Aggregate Templates defined for this Graph Template.  Please either first
							create an Aggregate Template for the selected Graph's Graph Template and try again, or 
							simply crease an un-templated Aggregate Graph.</p>
						</td>
					</tr>\n";

					html_end_box();

					# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
					html_start_box("<strong>Press 'Return' to return</strong>", '60%', '', '3', 'center', '');

					?>
					<script type='text/javascript'>
					$().ready(function() {
						$('#continue').hide();
						$('#cancel').attr('value', 'Return');
					});
					</script>
					<?php
				}
			}
		}elseif ($save['drp_action'] == 8) { /* automation */
			cacti_log('automation_graph_action_prepare called: ' . $save['drp_action'], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

			/* find out which (if any) hosts have been checked, so we can tell the user */
			if (isset($save['graph_array'])) {
				print '<tr>';
				print "<td class='textArea'>
					<p>Are you sure you want to apply <strong>Automation Rules</strong> to the following graphs?</p>
					<ul>" . $save['graph_list'] . '</ul></td>';
				print '</tr>';
			}
		} else {
			$save['drp_action'] = $_POST['drp_action'];
			$save['graph_list'] = $graph_list;
			$save['graph_array'] = (isset($graph_array) ? $graph_array : array());

			api_plugin_hook_function('graphs_action_prepare', $save);

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one graph.</span></td></tr>\n";

		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($graph_array) ? serialize($graph_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	bottom_footer();
}

/* -----------------------
    item - Graph Items
   ----------------------- */

function item() {
	global $consolidation_functions, $graph_item_types, $struct_graph_item;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	if (empty($_REQUEST['id'])) {
		$template_item_list = array();

		$header_label = '[new]';
	}else{
		$template_item_list = db_fetch_assoc_prepared('SELECT
			graph_templates_item.id,
			graph_templates_item.text_format,
			graph_templates_item.value,
			graph_templates_item.hard_return,
			graph_templates_item.graph_type_id,
			graph_templates_item.consolidation_function_id,
			data_template_rrd.data_source_name,
			cdef.name AS cdef_name,
			colors.hex
			FROM graph_templates_item
			LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id = data_template_rrd.id)
			LEFT JOIN data_local ON (data_template_rrd.local_data_id = data_local.id)
			LEFT JOIN data_template_data ON (data_local.id = data_template_data.local_data_id)
			LEFT JOIN cdef ON (cdef_id = cdef.id)
			LEFT JOIN colors ON (color_id = colors.id)
			WHERE graph_templates_item.local_graph_id = ?
			ORDER BY graph_templates_item.sequence', array($_REQUEST['id']));

		$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', array($_REQUEST['id']));
		$header_label = '[edit: ' . htmlspecialchars(get_graph_title($_REQUEST['id'])) . ']';
	}

	$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id FROM graph_local WHERE id = ?', array($_REQUEST['id']));

	if (empty($graph_template_id)) {
		$add_text = 'graphs_items.php?action=item_edit&local_graph_id=' . $_REQUEST['id'] . "&host_id=$host_id";
	}else{
		$add_text = '';
	}

	html_start_box("<strong>Graph Items</strong> $header_label", '100%', '', '3', 'center', $add_text);
	draw_graph_items_list($template_item_list, 'graphs_items.php', 'local_graph_id=' . $_REQUEST['id'], (empty($graph_template_id) ? false : true));
	html_end_box();
}

/* ------------------------------------
    graph - Graphs
   ------------------------------------ */

function graph_diff() {
	global  $struct_graph_item, $graph_item_types, $consolidation_functions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('graph_template_id'));
	/* ==================================================== */

	$template_query = "SELECT
		graph_templates_item.id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.consolidation_function_id,
		graph_templates_item.graph_type_id,
		CONCAT_WS(' - ', data_template_data.name, data_template_rrd.data_source_name) AS task_item_id,
		cdef.name AS cdef_id,
		colors.hex AS color_id
		FROM graph_templates_item
		LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id = data_template_rrd.id)
		LEFT JOIN data_local ON (data_template_rrd.local_data_id = data_local.id)
		LEFT JOIN data_template_data ON (data_local.id = data_template_data.local_data_id)
		LEFT JOIN cdef ON (cdef_id = cdef.id)
		LEFT JOIN colors ON (color_id = colors.id)";

	/* first, get information about the graph template as that's what we're going to model this
	graph after */
	$graph_template_items = db_fetch_assoc_prepared("
		$template_query
		WHERE graph_templates_item.graph_template_id = ?
		AND graph_templates_item.local_graph_id = 0
		ORDER BY graph_templates_item.sequence", array($_REQUEST['graph_template_id']));

	/* next, get information about the current graph so we can make the appropriate comparisons */
	$graph_items = db_fetch_assoc_prepared("
		$template_query
		WHERE graph_templates_item.local_graph_id = ?
		ORDER BY graph_templates_item.sequence", array($_REQUEST['id']));

	$graph_template_inputs = db_fetch_assoc_prepared('SELECT
		graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		FROM (graph_template_input, graph_template_input_defs)
		WHERE graph_template_input.id = graph_template_input_defs.graph_template_input_id
		AND graph_template_input.graph_template_id = ?', array($_REQUEST['graph_template_id']));

	/* ok, we want to loop through the array with the GREATEST number of items so we don't have to worry
	about tacking items on the end */
	if (sizeof($graph_template_items) > sizeof($graph_items)) {
		$items = $graph_template_items;
	}else{
		$items = $graph_items;
	}

	?>
	<table class='tableConfirmation' width='100%' align='center'>
		<tr>
			<td class='textArea'>
				The template you have selected requires some changes to be made to the structure of
				your graph. Below is a preview of your graph along with changes that need to be completed
				as shown in the left-hand column.
			</td>
		</tr>
	</table>
	<br>
	<?php

	html_start_box('<strong>Graph Preview</strong>', '100%', '', '3', 'center', '');

	$graph_item_actions = array('normal' => '', 'add' => '+', 'delete' => '-');

	$group_counter = 0; $i = 0; $mode = 'normal'; $_graph_type_name = '';

	if (sizeof($items) > 0) {
	foreach ($items as $item) {
		reset($struct_graph_item);

		/* graph grouping display logic */
		$bold_this_row = false; $use_custom_row_color = false; $action_css = ''; unset($graph_preview_item_values);

		if ((sizeof($graph_template_items) > sizeof($graph_items)) && ($i >= sizeof($graph_items))) {
			$mode = 'add';
			$user_message = "When you click save, the items marked with a '<strong>+</strong>' will be added <strong>(Recommended)</strong>.";
		}elseif ((sizeof($graph_template_items) < sizeof($graph_items)) && ($i >= sizeof($graph_template_items))) {
			$mode = 'delete';
			$user_message = "When you click save, the items marked with a '<strong>-</strong>' will be removed <strong>(Recommended)</strong>.";
		}

		/* here is the fun meshing part. first we check the graph template to see if there is an input
		for each field of this row. if there is, we revert to the value stored in the graph, if not
		we revert to the value stored in the template. got that? ;) */
		for ($j=0; ($j < count($graph_template_inputs)); $j++) {
			if ($graph_template_inputs[$j]['graph_template_item_id'] == (isset($graph_template_items[$i]['id']) ? $graph_template_items[$i]['id'] : '')) {
				/* if we find out that there is an "input" covering this field/item, use the
				value FROM the graph, not the template */
				$graph_item_field_name = (isset($graph_template_inputs[$j]['column_name']) ? $graph_template_inputs[$j]['column_name'] : '');
				$graph_preview_item_values[$graph_item_field_name] = (isset($graph_items[$i][$graph_item_field_name]) ? $graph_items[$i][$graph_item_field_name] : '');
			}
		}

		/* go back through each graph field and find out which ones haven't been covered by the
		"inputs" above. for each one, use the value FROM the template */
		while (list($field_name, $field_array) = each($struct_graph_item)) {
			if ($mode == 'delete') {
				$graph_preview_item_values[$field_name] = (isset($graph_items[$i][$field_name]) ? $graph_items[$i][$field_name] : '');
			}elseif (!isset($graph_preview_item_values[$field_name])) {
				$graph_preview_item_values[$field_name] = (isset($graph_template_items[$i][$field_name]) ? $graph_template_items[$i][$field_name] : '');
			}
		}

		/* "prepare" array values */
		$consolidation_function_id = $graph_preview_item_values['consolidation_function_id'];
		$graph_type_id = $graph_preview_item_values['graph_type_id'];

		/* color logic */
		if (($graph_item_types[$graph_type_id] != 'GPRINT') && ($graph_item_types[$graph_type_id] != $_graph_type_name)) {
			$bold_this_row = true; $use_custom_row_color = true; $hard_return = '';

			if ($group_counter % 2 == 0) {
				$alternate_color_1 = 'graphItemGr1Alt1';
				$alternate_color_2 = 'graphItemGr1Alt2';
				$custom_row_color  = 'graphItemGr1Cust';
			}else{
				$alternate_color_1 = 'graphItemGr2Alt1';
				$alternate_color_2 = 'graphItemGr2Alt2';
				$custom_row_color  = 'graphItemGr2Cust';
			}

			$group_counter++;
		}

		$_graph_type_name = $graph_item_types[$graph_type_id];

		if ($use_custom_row_color == false) {
			if ($i % 2 == 0) {
				$action_column_color = $alternate_color_1;
			}else{
				$action_column_color = $alternate_color_2;
			}
		}else{
			$action_column_color = $custom_row_color;
		}

		print "<tr class='#$action_column_color'>"; $i++;

		/* make the left-hand column blue or red depending on if "add"/"remove" mode is set */
		if ($mode == 'add') {
			$action_column_color = 'graphItemAdd';
			$action_css = '';
		}elseif ($mode == 'delete') {
			$action_column_color = 'graphItemDel';
			$action_css = 'text-decoration: line-through;';
		}

		if ($bold_this_row == true) {
			$action_css .= ' font-weight:bold;';
		}

		/* draw the TD that shows the user whether we are going to: KEEP, ADD, or DROP the item */
		print "<td width='1%' class='#$action_column_color' style='font-weight: bold; color: white;'>" . $graph_item_actions[$mode] . '</td>';
		print "<td style='$action_css'><strong>Item # " . $i . "</strong></td>\n";

		if (empty($graph_preview_item_values['task_item_id'])) { $graph_preview_item_values['task_item_id'] = 'No Task'; }

		switch (true) {
		case preg_match('/(AREA|STACK|GPRINT|LINE[123])/', $_graph_type_name):
			$matrix_title = '(' . $graph_preview_item_values['task_item_id'] . '): ' . $graph_preview_item_values['text_format'];
			break;
		case preg_match('/(HRULE|VRULE)/', $_graph_type_name):
			$matrix_title = 'HRULE: ' . $graph_preview_item_values['value'];
			break;
		case preg_match('/(COMMENT)/', $_graph_type_name):
			$matrix_title = 'COMMENT: ' . $graph_preview_item_values['text_format'];
			break;
		}

		/* use the cdef name (if in use) if all else fails */
		if ($matrix_title == '') {
			if ($graph_preview_item_values['cdef_id'] != '') {
				$matrix_title .= 'CDEF: ' . $graph_preview_item_values['cdef_id'];
			}
		}

		if ($graph_preview_item_values['hard_return'] == 'on') {
			$hard_return = "<strong><font class='graphItemHR'>&lt;HR&gt;</font></strong>";
		}

		print "<td style='$action_css'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
		print "<td style='$action_css'>" . $graph_item_types{$graph_preview_item_values['graph_type_id']} . "</td>\n";
		print "<td style='$action_css'>" . $consolidation_functions{$graph_preview_item_values['consolidation_function_id']} . "</td>\n";
		print '<td' . ((!empty($graph_preview_item_values['color_id'])) ? " bgcolor='#" . $graph_preview_item_values['color_id'] . "'" : '') . " width='1%'>&nbsp;</td>\n";
		print "<td style='$action_css'>" . $graph_preview_item_values['color_id'] . "</td>\n";

		print '</tr>';
	}
	}else{
		print "<tr class='tableRow'><td colspan='7'><em>No Items</em></td></tr>\n";
	}
	html_end_box();

	?>
	<form action="graphs.php" method="post">
	<table class='tableConfirmation' width="100%" align="center">
		<tr>
			<td class="textArea">
				<input type='radio' name='type' value='1' checked>&nbsp;<?php print $user_message;?><br>
				<input type='radio' name='type' value='2'>&nbsp;When you click save, the graph items will remain untouched (could cause inconsistencies).
			</td>
		</tr>
	</table>
	<br>
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="save_component_graph_diff" value="1">
	<input type="hidden" name="local_graph_id" value="<?php print $_REQUEST['id'];?>">
	<input type="hidden" name="graph_template_id" value="<?php print $_REQUEST['graph_template_id'];?>">
	<?php

	form_save_button('graphs.php?action=graph_edit&id=' . $_REQUEST['id']);
}

function graph_edit() {
	global $struct_graph, $image_types, $consolidation_functions, $graph_item_types, $struct_graph_item;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	$locked = 'false';
	$use_graph_template = true;

	if (!empty($_REQUEST['id'])) {
		$_SESSION['sess_graph_lock_id'] = $_REQUEST['id'];

		$local_graph_template_graph_id = db_fetch_cell_prepared('SELECT local_graph_template_graph_id FROM graph_templates_graph WHERE local_graph_id = ?', array($_REQUEST['id']));

		if ($_REQUEST['id'] != $_SESSION['sess_graph_lock_id'] && !empty($local_graph_template_graph_id)) {
			$locked = 'true';
			$_SESSION['sess_graph_locked'] = $locked;
		}elseif (empty($local_graph_template_graph_id)) {
			$locked = 'false';
			$_SESSION['sess_graph_locked'] = $locked;
		}elseif (isset($_SESSION['sess_graph_locked'])) {
			$locked = $_SESSION['sess_graph_locked'];
		}else{
			$locked = 'true';
			$_SESSION['sess_graph_locked'] = $locked;
		}

		$graphs = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', array($_REQUEST['id']));
		$graphs_template = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE id = ?', array($local_graph_template_graph_id));

		$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', array($_REQUEST['id']));
		$header_label = '[edit: ' . htmlspecialchars(get_graph_title($_REQUEST['id'])) . ']';

		if ($graphs['graph_template_id'] == '0') {
			$use_graph_template = 'false';
		}
	}else{
		$header_label = '[new]';
		$use_graph_template = false;
	}

	/* handle debug mode */
	if (isset($_REQUEST['debug'])) {
		if ($_REQUEST['debug'] == '0') {
			kill_session_var('graph_debug_mode');
		}elseif ($_REQUEST['debug'] == '1') {
			$_SESSION['graph_debug_mode'] = true;
		}
	}

	if (!empty($_REQUEST['id'])) {
		?>
		<table width="100%" align="center">
			<tr>
				<td class="textInfo" colspan="2" valign="top">
					<?php print htmlspecialchars(get_graph_title($_REQUEST['id']));?>
				</td>
				<td class="textInfo" align="right" valign="top">
					<span class="linkMarker">*<a class='hyperLink' href='<?php print htmlspecialchars('graphs.php?action=graph_edit&id=' . (isset($_REQUEST['id']) ? $_REQUEST['id'] : '0') . '&debug=' . (isset($_SESSION['graph_debug_mode']) ? '0' : '1'));?>'>Turn <strong><?php print (isset($_SESSION['graph_debug_mode']) ? 'Off' : 'On');?></strong> Graph Debug Mode.</a></span><br>
					<?php
						if (!empty($graphs['graph_template_id'])) {
							?><span class="linkMarker">*<a class='hyperLink' href='<?php print htmlspecialchars('graph_templates.php?action=template_edit&id=' . (isset($graphs['graph_template_id']) ? $graphs['graph_template_id'] : '0'));?>'>Edit Graph Template.</a></span><br><?php
						}
						if (!empty($_REQUEST['host_id']) || !empty($host_id)) {
							?><span class="linkMarker">*<a class='hyperLink' href='<?php print htmlspecialchars('host.php?action=edit&id=' . (isset($_REQUEST['host_id']) ? $_REQUEST['host_id'] : $host_id));?>'>Edit Device.</a></span><br><?php
						}
						if ($locked == 'true') {
							?><span class="linkMarker">* <span class='hyperLink' id='unlockid'>Unlock Graph</span></span><?php
						}else{
							?><span class="linkMarker">* <span class='hyperLink' id='lockid'>Lock Graph</span></span><?php
						}
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	html_start_box("<strong>Graph Template Selection</strong> $header_label", '100%', '', '3', 'center', '');

	$form_array = array(
		'graph_template_id' => array(
			'method' => 'drop_sql',
			'friendly_name' => 'Selected Graph Template',
			'description' => 'Choose a Graph Template to apply to this Graph. Please note that Graph Data may be lost if you change the Graph Template after one is already applied.',
			'value' => (isset($graphs) ? $graphs['graph_template_id'] : '0'),
			'none_value' => 'None',
			'sql' => 'SELECT graph_templates.id,graph_templates.name FROM graph_templates ORDER BY name'
			),
		'host_id' => array(
			'method' => 'drop_callback',
			'friendly_name' => 'Device',
			'description' => 'Choose the Device that this Graph belongs to.',
			'sql' => "SELECT id,CONCAT_WS('',description,' (',hostname,')') as name FROM host ORDER BY description,hostname",
			'action' => 'ajax_hosts_noany',
			'id' => (isset($_REQUEST['host_id']) ? $_REQUEST['host_id'] : $host_id),
			'value' => db_fetch_cell_prepared('SELECT description AS name FROM host WHERE id = ?', (isset($_REQUEST['host_id']) ? array($_REQUEST['host_id']) : array($host_id))),
			),
		'graph_template_graph_id' => array(
			'method' => 'hidden',
			'value' => (isset($graphs) ? $graphs['id'] : '0')
			),
		'local_graph_id' => array(
			'method' => 'hidden',
			'value' => (isset($graphs) ? $graphs['local_graph_id'] : '0')
			),
		'local_graph_template_graph_id' => array(
			'method' => 'hidden',
			'value' => (isset($graphs) ? $graphs['local_graph_template_graph_id'] : '0')
			),
		'_graph_template_id' => array(
			'method' => 'hidden',
			'value' => (isset($graphs) ? $graphs['graph_template_id'] : '0')
			),
		'_host_id' => array(
			'method' => 'hidden',
			'value' => (isset($host_id) ? $host_id : '0')
			)
		);

	draw_edit_form(
		array(
			'config' => array(),
			'fields' => $form_array
			)
		);

	html_end_box();

	/* only display the "inputs" area if we are using a graph template for this graph */
	if (!empty($graphs['graph_template_id'])) {
		html_start_box('<strong>Supplemental Graph Template Data</strong>', '100%', '', '3', 'center', '');

		draw_nontemplated_fields_graph($graphs['graph_template_id'], $graphs, '|field|', '<strong>Graph Fields</strong>', true, true, 0);
		draw_nontemplated_fields_graph_item($graphs['graph_template_id'], $_REQUEST['id'], '|field|_|id|', '<strong>Graph Item Fields</strong>', true, $locked);

		html_end_box();
	}

	/* graph item list goes here */
	if ((!empty($_REQUEST['id'])) && (empty($graphs['graph_template_id']))) {
		item();
	}

	if (!empty($_REQUEST['id'])) {
		?>
		<table width="100%" align="center">
			<tr>
				<td align="center" class="textInfo" colspan="2">
					<img src="<?php print htmlspecialchars('graph_image.php?action=edit&local_graph_id=' . $_REQUEST['id'] . '&rra_id=' . read_graph_config_option('default_rra_id'));?>" alt="">
				</td>
				<?php
				if ((isset($_SESSION['graph_debug_mode'])) && (isset($_REQUEST['id']))) {
					$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_STDERR;
					$graph_data_array['print_source'] = 1;
					?>
					<td>
						<span class="textInfo">RRDTool Command:</span><br>
						<pre><?php print @rrdtool_function_graph($_REQUEST['id'], 1, $graph_data_array);?></pre>
						<span class="textInfo">RRDTool Says:</span><br>
						<?php unset($graph_data_array['print_source']);?>
						<pre><?php print @rrdtool_function_graph($_REQUEST['id'], 1, $graph_data_array);?></pre>
					</td>
					<?php
				}
				?>
			</tr>
		</table>
		<br>
		<?php
	}

	if (((isset($_REQUEST['id'])) || (isset($_REQUEST['new']))) && (empty($graphs['graph_template_id']))) {
		html_start_box('<strong>Graph Configuration</strong>', '100%', '', '3', 'center', '');

		$form_array = array();

		while (list($field_name, $field_array) = each($struct_graph)) {
			$form_array += array($field_name => $struct_graph[$field_name]);

			$form_array[$field_name]['value'] = (isset($graphs) ? $graphs[$field_name] : '');
			$form_array[$field_name]['form_id'] = (isset($graphs) ? $graphs['id'] : '0');

			if (!(($use_graph_template == false) || ($graphs_template{'t_' . $field_name} == 'on'))) {
				$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
				$form_array[$field_name]['description'] = '';
			}
		}

		draw_edit_form(
			array(
				'config' => array(
					'no_form_tag' => true
					),
				'fields' => $form_array
				)
			);

		html_end_box();
	}

	if ((isset($_REQUEST['id'])) || (isset($_REQUEST['new']))) {
		form_hidden_box('save_component_graph','1','');
		form_hidden_box('save_component_input','1','');
	}else{
		form_hidden_box('save_component_graph_new','1','');
	}

	form_hidden_box('rrdtool_version', read_config_option('rrdtool_version'), '');
	form_save_button('graphs.php');

	//Now we need some javascript to make it dynamic
	?>
	<script type='text/javascript'>

	dynamic();

	function dynamic() {
		if ($('#scale_log_units').is(':checked')) {
			$('#scale_log_units').prop('disabled', true);
			if ($('#auto_scale_log').is(':checked')) {
				$('#scale_log_units').prop('disabled', false);
			}
		}
	}

	function changeScaleLog() {
		if ($('#scale_log_units').is(':checked')) {
			$('#scale_log_units').prop('disabled', true);
			if ($('#auto_scale_log').is(':checked')) {
				$('#scale_log_units').prop('disabled', false);
			}
		}
	}

	$(function() {
		$('#unlockid').click(function(event) {
			event.preventDefault;

			$('body').append("<div id='modal' class='ui-widget-overlay ui-front' style='z-index: 100;'><i style='position:absolute;top:50%;left:50%;' class='fa fa-spin fa-circle-o-notch'/></div>");

			$.get('graphs.php?action=unlock&header=false&id='+$('#local_graph_id').val(), function(data) {
				$('#modal').remove();
				$('#main').html(data);
				applySkin();
			});
		});

		$('#lockid').click(function(event) {
			event.preventDefault;

			$.get('graphs.php?action=lock&header=false&id='+$('#local_graph_id').val(), function(data) {
				$('#main').html(data);
				applySkin();
			});
		});
	});

	if (<?php print ($locked == true ? 'true':'false');?> == true) {
		$('input, select').not('input[value="Cancel"]').prop('disabled', true);
		$('#host_id_wrap').addClass('ui-selectmenu-disabled ui-state-disabled');
	}
	</script>
	<?php
}

function graph() {
	global $graph_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('template_id'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_graph_current_page');
		kill_session_var('sess_graph_filter');
		kill_session_var('sess_graph_sort_column');
		kill_session_var('sess_graph_sort_direction');
		kill_session_var('sess_graph_host_id');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_graph_template_id');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['template_id']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_graph_current_page', '1');
	load_current_session_value('filter', 'sess_graph_filter', '');
	load_current_session_value('sort_column', 'sess_graph_sort_column', 'title_cache');
	load_current_session_value('sort_direction', 'sess_graph_sort_direction', 'ASC');
	load_current_session_value('host_id', 'sess_graph_host_id', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('template_id', 'sess_graph_template_id', '-1');

	/* if the number of rows is -1, set it to the default */
	if (get_request_var_request('rows') == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type="text/javascript">

	function applyFilter() {
		strURL = 'graphs.php?host_id=' + $('#host_id').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&template_id=' + $('#template_id').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'graphs.php?clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
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

		$('#form_graphs').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	if (read_config_option('grds_creation_method') == 1) {
		$add_url = htmlspecialchars('graphs.php?action=graph_edit&host_id=' . get_request_var_request('host_id'));
	}else{
		$add_url = '';
	}

	html_start_box('<strong>Graph Management</strong>', '100%', '', '3', 'center', $add_url);

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_graphs' name='form_graphs' action='graphs.php'>
			<table cellpadding='2' cellspacing='0' border='0'>
				<tr>
					<?php print html_host_filter($_REQUEST['host_id']);?>
					<td>
						Template
					</td>
					<td>
						<select id='template_id' name='template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var_request('template_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var_request('template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$templates = get_allowed_graph_templates();
							if (sizeof($templates) > 0) {
								foreach ($templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var_request('template_id') == $template['id']) { print ' selected'; } print '>' . title_trim(htmlspecialchars($template['name']), 40) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' name='clear_x' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<table cellpadding='2' cellspacing='0' border='0'>
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td>
						Graphs
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = " WHERE (gtg.title_cache LIKE '%" . get_request_var_request('filter') . "%'" .
			" OR gt.name LIKE '%" . get_request_var_request('filter') . "%')";
	}

	if (get_request_var_request('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gl.host_id=0';
	}elseif (!empty($_REQUEST['host_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gl.host_id=' . get_request_var_request('host_id');
	}

	if (get_request_var_request('template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
	}elseif (!empty($_REQUEST['template_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=' . get_request_var_request('template_id');
	}

	/* don't allow aggregates to be view here */
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' ag.local_graph_id IS NULL';

	/* allow plugins to modify sql_where */
	$sql_where = api_plugin_hook_function('graphs_sql_where', $sql_where);

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='graphs.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(gtg.id)
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id=gt.id
		LEFT JOIN aggregate_graphs AS ag
		ON ag.local_graph_id=gl.id
		$sql_where");

	$graph_list = db_fetch_assoc("SELECT gtg.id, gtg.local_graph_id, gtg.height, gtg.width,
		gtg.title_cache, gt.name, gl.host_id
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id=gt.id
		LEFT JOIN aggregate_graphs AS ag
		ON ag.local_graph_id=gl.id
		$sql_where
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('graphs.php?filter=' . get_request_var_request('filter') . '&host_id=' . get_request_var_request('host_id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 5, 'Graphs', 'page', 'main');

	print $nav;

	$display_text = array(
		'title_cache' => array('display' => 'Graph Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The Title of this Graph.  Generally programatically generated from the Graph Template defition or Suggested Naming rules.  The max length of the Title is controlled under Settings->Visual.'),
		'local_graph_id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal database ID fro this Graph.  Useful when performing automation or debugging.'),
		'name' => array('display' => 'Template Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The Graph Template that this Graph was based upon.'),
		'height' => array('display' => 'Size', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The size of this Graph when not in Preview mode.'));

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($graph_list) > 0) {
		foreach ($graph_list as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			$template_name = ((empty($graph['name'])) ? '<em>None</em>' : htmlspecialchars($graph['name']));
			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('graphs.php?action=graph_edit&id=' . $graph['local_graph_id']) . "'>" . ((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", title_trim(htmlspecialchars($graph['title_cache']), read_config_option('max_title_length'))) : title_trim(htmlspecialchars($graph['title_cache']), read_config_option('max_title_length'))) . '</a>', $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id'], '', 'text-align:right');
			form_selectable_cell(((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $template_name) : $template_name), $graph['local_graph_id']);
			form_selectable_cell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id']);
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='5'><em>No Graphs Found</em></td></tr>";
	}

	html_end_box(false);

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($graph_actions);

	print "</form>\n";
}

