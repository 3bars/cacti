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

$guest_account = true;

include('./include/auth.php');
include_once('./lib/html_tree.php');
include_once('./lib/html_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/timespan_settings.php');

/* set the default graph action */
set_default_graph_action();

/* process tree settings */
process_tree_settings();

/* setup realtime defaults if they are not set */
initialize_realtime_step_and_window();

function get_matching_nodes() {
	$my_matches = array();
	$match = array();

	$matching = db_fetch_assoc("SELECT gti.id, gti.parent, gti.graph_tree_id, IF(gti.title != '', '1', '0') AS node
		FROM graph_tree_items AS gti 
		LEFT JOIN host AS h
		ON h.id=gti.host_id
		LEFT JOIN graph_templates_graph AS gtg
		ON gtg.local_graph_id=gti.local_graph_id AND gtg.local_graph_id>0
		WHERE gtg.title_cache LIKE '%" . get_nfilter_request_var('str') . "%'
		OR h.description LIKE '%" . get_nfilter_request_var('str') . "%'
		OR h.hostname LIKE '%" . get_nfilter_request_var('str') . "%'
		OR gti.title LIKE '%" . get_nfilter_request_var('str') . "%'");

	if (sizeof($matching)) {
		foreach($matching as $row) {
			while ($row['parent'] != '0') {
				$match[] = 'tbranch-' . $row['parent'];
				$row = db_fetch_row("SELECT id, parent, graph_tree_id FROM graph_tree_items WHERE id=" . $row['parent']);
			}

			$match[]      = 'tree_anchor-' . $row['graph_tree_id'];
			$my_matches[] = array_reverse($match);
			$match        = array();
		}

		// Now flatten the list of nodes
		$final_array = array();
		$level = 0;
		while (true) {
			$found = 0;
			foreach($my_matches as $match) {
				if (isset($match[$level])) {
					if ($level == 0) {
						$final_array[$match[$level]][$match[$level]] = 1;
					}else{
						$final_array[$match[0]][$match[$level]] = 1;
					}
					$found++;
				}
			}
			$level++;

			if ($found == 0) break;
		}

		if (sizeof($final_array)) {
			foreach($final_array as $key => $matches) {
				foreach($matches as $branch => $dnc) {
					$fa[] = $branch;
				}
			}
		}
 
		header('Content-Type: application/json; charset=utf-8');

		print json_encode($fa);
		//print '[' . implode(', ', array_keys($matching)) . ']';
	}
}

switch (get_nfilter_request_var('action')) {
case 'ajax_hosts':
	get_allowed_ajax_hosts();

	break;
case 'ajax_search':
	get_matching_nodes(); exit;

	break;
case 'save':
	if (is_view_allowed('graph_settings')) {
		get_filter_request_var('columns');
		get_filter_request_var('predefined_timespan');
		get_filter_request_var('predefined_timeshift');
		get_filter_request_var('graphs');

		if (isset_request_var('predefined_timespan')) {
			set_graph_config_option('default_timespan', get_request_var('predefined_timespan'));
		}
		if (isset_request_var('predefined_timeshift')) {
			set_graph_config_option('default_timeshift', get_request_var('predefined_timeshift'));
		}
		if (isset_request_var('section') && get_request_var('section') == 'preview') {
			if (isset_request_var('columns')) {
				set_graph_config_option('num_columns', get_request_var('columns'));
			}
			if (isset_request_var('graphs')) {
				set_graph_config_option('preview_graphs_per_page', get_request_var('graphs'));
			}
			if (isset_request_var('thumbnails')) {
				set_graph_config_option('thumbnail_section_preview', get_request_var('thumbnails') == 'true' ? 'on':'');
			}
		}else{
			if (isset_request_var('columns')) {
				set_graph_config_option('num_columns_tree', get_request_var('columns'));
			}
			if (isset_request_var('graphs')) {
				set_graph_config_option('treeview_graphs_per_page', get_request_var('graphs'));
			}
			if (isset_request_var('thumbnails')) {
				set_graph_config_option('thumbnail_section_tree_2', get_request_var('thumbnails') == 'true' ? 'on':'');
			}
		}
	}

	break;
case 'tree':
	if (isset_request_var('tree_id')) {
		$_SESSION['sess_tree_id'] = get_request_var('tree_id');
	}

	top_graph_header();

	bottom_footer();

	break;
case 'get_node':
	$parent  = -1;
	$tree_id = 0;

	if (isset_request_var('tree_id')) {
		if (get_nfilter_request_var('tree_id') == 'default' || 
			get_nfilter_request_var('tree_id') == 'undefined' || 
			get_nfilter_request_var('tree_id') == '') {

			$tree_id = read_user_setting('default_tree_id');
		}elseif (get_nfilter_request_var('tree_id') == 0 && 
			substr_count(get_nfilter_request_var('id'), 'tree_anchor') > 0) {

			$ndata = explode('-', get_nfilter_request_var('id'));
			$tree_id = $ndata[1];
			input_validate_input_number($tree_id);
		}
	}else{
		$tree_id = read_user_setting('default_tree_id');
	}

	if (isset_request_var('id') && get_nfilter_request_var('id') != '#') {
		if (substr_count(get_nfilter_request_var('id'), 'tree_anchor')) {
			$parent = -1;
		}else{
			$ndata = explode('_', get_nfilter_request_var('id'));

			foreach($ndata as $node) {
				$pnode = explode('-', $node);
	
				if ($pnode[0] == 'tbranch') {
					$parent = $pnode[1];
					input_validate_input_number($parent);
					$tree_id = db_fetch_cell("SELECT graph_tree_id FROM graph_tree_items WHERE id=$parent");
					break;
				}
			}
		}
	}

	api_tree_get_main($tree_id, $parent);
	
	break;
case 'tree_content':
	html_validate_tree_vars();

	if (!is_view_allowed('show_tree')) {
		print "<font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font>"; return;
	}

	?>
	<script type='text/javascript'>
	var graph_start=<?php print get_current_graph_start();?>;
	var graph_end=<?php print get_current_graph_end();?>;
	var timeOffset=<?php print date('Z');?>

	// Adjust the height of the tree
	$(function() {
		navHeight    = $('.cactiTreeNavigationArea').height();
		windowHeight = $(window).height();
		navOffset    = $('.cactiTreeNavigationArea').offset();
		if (navHeight + navOffset.top < windowHeight) {
			$('.cactiTreeNavigationArea').height(windowHeight - navOffset.top);
		}
	});
	</script>
	<?php

	$access_denied = false;
	$tree_parameters = array();

	if (isset_request_var('nodeid')) {
		$_SESSION['sess_node_id'] = 'tbranch-' . get_request_var('nodeid');
	}

	if (isset_request_var('tree_id')) {
		if (!is_tree_allowed(get_request_var('tree_id'))) {
			header('Location: permission_denied.php');
			exit;
		}

        $_SESSION['sess_tree_id'] = get_request_var('tree_id');

		grow_right_pane_tree((isset_request_var('tree_id') ? get_request_var('tree_id') : 0), (isset_request_var('leaf_id') ? get_request_var('leaf_id') : 0), (isset_request_var('host_group_data') ? urldecode(get_request_var('host_group_data')) : 0));
	}

	break;
case 'preview':
	top_graph_header();

	if (!is_view_allowed('show_preview')) {
		print "<font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font>"; return;
	}

	html_graph_validate_preview_request_vars();

	/* include graph view filter selector */
	html_start_box('Graph Preview Filters' . (isset_request_var('style') && strlen(get_request_var('style')) ? ' [ Custom Graph List Applied - Filtering from List ]':''), '100%', '', '3', 'center', '');

	html_graph_preview_filter('graph_view.php', 'preview');

	html_end_box();

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	$sql_or = '';
	if (isset_request_var('style')) {
		if (get_request_var('style') == 'selective') {

			/* process selected graphs */
			if (!isempty_request_var('graph_list')) {
				foreach (explode(',', get_request_var('graph_list')) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}
			if (!isempty_request_var('graph_add')) {
				foreach (explode(',', get_request_var('graph_add')) as $item) {
					$graph_list[$item] = 1;
				}
			}
			/* remove items */
			if (!isempty_request_var('graph_remove')) {
				foreach (explode(',', get_request_var('graph_remove')) as $item) {
					unset($graph_list[$item]);
				}
			}

			$i = 0;
			foreach ($graph_list as $item => $value) {
				$graph_array[$i] = $item;
				$i++;
			}

			if ((isset($graph_array)) && (sizeof($graph_array) > 0)) {
				/* build sql string including each graph the user checked */
				$sql_or = array_to_sql_or($graph_array, 'gtg.local_graph_id');
			}
		}
	}

	$total_graphs = 0;

	$sql_where  = (strlen(get_request_var('filter')) ? "gtg.title_cache LIKE '%" . get_request_var('filter') . "%'":'');
	$sql_where .= (strlen($sql_or) && strlen($sql_where) ? ' AND ':'') . $sql_or;
	$sql_where .= (get_request_var('host_id') >= 0 ? (strlen($sql_where) ? ' AND':'') . ' gl.host_id=' . get_request_var('host_id'):'');
	$sql_where .= (get_request_var('graph_template_id') > 0 ? (strlen($sql_where) ? ' AND':'') . ' gl.graph_template_id=' . get_request_var('graph_template_id'):'');

	$limit      = (get_request_var('graphs')*(get_request_var('page')-1)) . ',' . get_request_var('graphs');
	$order      = 'gtg.title_cache';

	$graphs     = get_allowed_graphs($sql_where, $order, $limit, $total_graphs);	

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (preg_match('/page=[0-9]+/',basename($_SERVER['QUERY_STRING']))) {
		$nav_url = str_replace('&page=' . get_request_var('page'), '', get_browser_query_string());
	}else{
		$nav_url = get_browser_query_string() . '&host_id=' . get_request_var('host_id');
	}

	$nav_url = preg_replace('/((\?|&)host_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*)/', '', $nav_url);

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar($nav_url, MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('graphs'), $total_graphs, get_request_var('columns'), 'Graphs', 'page', 'main');

	print $nav;

	if (get_request_var('thumbnails') == 'true') {
		html_graph_thumbnail_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}else{
		html_graph_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}

	if ($total_graphs > 0) {
		print $nav;
	}

	html_end_box();

	if (!isset_request_var('header') || get_nfilter_request_var('header') == 'false') {
		bottom_footer();
	}

	break;
case 'list':
	top_graph_header();

	if (!is_view_allowed('show_list')) {
		print "<font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font>"; return;
	}

	/* reset the graph list on a new viewing */
	if (!isset_request_var('page')) {
		set_request_var('graph_list', '');
		set_request_var('page', 1);
	}

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
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '0'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'graph_list' => array(
			'filter' => FILTER_DEFAULT,
			'default' => ''
			),
		'graph_add' => array(
			'filter' => FILTER_DEFAULT,
			'default' => ''
			),
		'graph_remove' => array(
			'filter' => FILTER_DEFAULT,
			'default' => ''
			)
	);

	validate_store_request_vars($filters, 'sess_gl');
	/* ================= input validation ================= */

	/* save selected graphs into url */
	if (!isempty_request_var('graph_list')) {
		foreach (explode(',', get_request_var('graph_list')) as $item) {
			$graph_list[$item] = 1;
		}
	}else{
		$graph_list = array();
	}

	if (!isempty_request_var('graph_add')) {
		foreach (explode(',', get_request_var('graph_add')) as $item) {
			$graph_list[$item] = 1;
		}
	}

	/* remove items */
	if (!isempty_request_var('graph_remove')) {
		foreach (explode(',', get_request_var('graph_remove')) as $item) {
			unset($graph_list[$item]);
		}
	}

	/* update the revised graph list session variable */
	set_request_var('graph_list', implode(',', array_keys($graph_list)));
	load_current_session_value('graph_list', 'sess_graph_view_list_graph_list', '');

	/* display graph view filter selector */
	html_start_box('Graph List View Filters' . (isset_request_var('style') && strlen(get_request_var('style')) ? ' [ Custom Graph List Applied - Filter FROM List ]':''), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_graph_list' name='form_graph_list' method='post' action='graph_view.php?action=list'>
			<table class='filterTable'>
				<tr class='noprint'>
					<?php print html_host_filter(get_request_var('host_id'));?>
					<td>
						Template
					</td>
					<td>
						<select id='graph_template_id' name='graph_template_id' onChange='applyFilter()'>
							<option value='0'<?php print htmlspecialchars(get_request_var('filter'));?><?php if (get_request_var('host_id') == '0') {?> selected<?php }?>>Any</option>
							<?php

							$graph_templates = get_allowed_graph_templates();

							if (sizeof($graph_templates) > 0) {
								foreach ($graph_templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var('graph_template_id') == $template['id']) { print ' selected'; } print '>' . htmlspecialchars($template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Graphs
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters' onClick='applyFilter()'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters' onClick='clearFilter()'>
					</td>
					<td>
						<input type='button' value='View' title='View Graphs' onClick='viewGraphs()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='graph_add' value=''>
			<input type='hidden' name='graph_remove' value=''>
			<input type='hidden' name='graph_list' value='<?php print get_request_var('graph_list');?>'>
			<input type='hidden' id='page' name='page' value='<?php print get_request_var('page');?>'>
		</form>
		</td>
	</tr>
	<?php
	html_end_box();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		set_request_var('rows', read_user_setting('num_rows_table'));
	}

	/* create filter for sql */
	$sql_where  = '';
	$sql_where .= (isempty_request_var('filter') ? '' : " gtg.title_cache LIKE '%" . get_request_var('filter') . "%'");
	$sql_where .= (get_request_var('host_id') < 0 ? '' : (empty($sql_filter) ? '' : ' AND') . ' gl.host_id=' . get_request_var('host_id'));
	$sql_where .= (isempty_request_var('graph_template_id') ? '' : (empty($sql_filter) ? '' : ' AND') . ' gl.graph_template_id=' . get_request_var('graph_template_id'));

	$total_rows = 0;
	$limit      = (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows');

	$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', $limit, $total_rows);

	form_start('graph_view.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('graph_view.php?action=list', MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 5, 'Graphs', 'page', 'main');

	print $nav;

	html_header_checkbox(array('Graph Title', 'Device', 'Graph Template', 'Graph Size'), false);

	$i = 0;
	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('graph.php?local_graph_id=' . $graph['local_graph_id'] . '&rra_id=0') . "'>" . htmlspecialchars($graph['title_cache']) . '</a>', $graph['local_graph_id']);
			form_selectable_cell($graph['description'], $graph['local_graph_id']);
			form_selectable_cell($graph['template_name'], $graph['local_graph_id']);
			form_selectable_cell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id']);
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}

		print $nav;
	}

	html_end_box();

	?>
	<table align='right'>
	<tr>
		<td align='right'><img src='images/arrow.gif' alt=''>&nbsp;</td>
		<td align='right'><input type='button' value='View' title='View Graphs' onClick='viewGraphs()'></td>
	</tr>
	</table>
	<input type='hidden' name='style' value='selective'>
	<input type='hidden' name='action' value='preview'>
	<input type='hidden' id='graph_list' name='graph_list' value='<?php print get_request_var('graph_list'); ?>'>
	<input type='hidden' id='graph_add' name='graph_add' value=''>
	<input type='hidden' id='graph_remove' name='graph_remove' value=''>
	</form>
	<script type='text/javascript'>
	var graph_list_array = new Array(<?php print get_request_var('graph_list');?>);

	$(function() {
		initializeChecks();
	});

	function clearFilter() {
		strURL = 'graph_view.php?action=list&header=false&clear=1';
		loadPageNoHeader(strURL);
	}

	function applyFilter() {
		strURL = 'graph_view.php?action=list&header=false&page=1';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += url_graph('');
		loadPageNoHeader(strURL);
	}

	function initializeChecks() {
		for (var i = 0; i < graph_list_array.length; i++) {
			$('#line'+graph_list_array[i]).addClass('selected');
			$('#chk_'+graph_list_array[i]).prop('checked', true);
			$('#chk_'+graph_list_array[i]).parent().addClass('selected');
		}
	}

	function viewGraphs() {
		graphList = $('#graph_list').val();
		$('input[id^=chk_]').each(function(data) {
			graphID = $(this).attr('id').replace('chk_','');
			if ($(this).is(':checked')) {
				graphList += (graphList.length > 0 ? ',':'') + graphID;
			}
		});
		$('#graph_list').val(graphList);

		document.chk.submit();
	}

	function url_graph(strNavURL) {
		var strURL = '';
		var strAdd = '';
		var strDel = '';
		$('input[id^=chk_]').each(function(data) {
			graphID = $(this).attr('id').replace('chk_','');
			if ($(this).is(':checked')) {
				strAdd += (strAdd.length > 0 ? ',':'') + graphID;
			} else if (graphChecked(graphID)) {
				strDel += (strDel.length > 0 ? ',':'') + graphID;
			}
		});

		strURL = '&graph_list=<?php print get_request_var('graph_list');?>&graph_add=' + strAdd + '&graph_remove=' + strDel;

		return strNavURL + strURL;
	}

	function graphChecked(graph_id) {
		for(var i = 0; i < graph_list_array.length; i++) {
			if (graph_list_array[i] == graph_id) {
				return true;
			}
		}

		return false;
	}

	function form_graph(objForm,objFormSubmit) {
		var strAdd = '';
		var strDel = '';
		$('input[id^=chk_]').each(function(data) {
			graphID = $(this).attr('id').replace('chk_','');
			if ($(this).is(':checked')) {
				strAdd += (strAdd.length > 0 ? ',':'') + graphID;
			} else if (graphChecked(graphID)) {
				strAdd += (strAdd.length > 0 ? ',':'') + graphID;
			}
		});
		objFormSubmit.graph_add.value = strAdd;
		objFormSubmit.graph_remove.value = strDel;
	}

	$(function() {
		$('#form_graph_list').on('submit', function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	bottom_footer();

	break;
}

