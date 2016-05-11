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
include_once('./lib/boost.php');

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'clear_poller_cache':
		/* obtain timeout settings */
		$max_execution = ini_get('max_execution_time');
		ini_set('max_execution_time', '0');
		repopulate_poller_cache();
		ini_set('max_execution_time', $max_execution);
		header('Location: utilities.php?action=view_poller_cache');exit;
		break;
	case 'view_snmp_cache':
		top_header();
		utilities_view_snmp_cache();
		bottom_footer();
		break;
	case 'view_poller_cache':
		top_header();
		utilities_view_poller_cache();
		bottom_footer();
		break;
	case 'view_logfile':
		utilities_view_logfile();
		break;
	case 'clear_logfile':
		utilities_clear_logfile();
		utilities_view_logfile();
		break;
	case 'view_cleaner':
		top_header();
		utilities_view_cleaner();
		bottom_footer();
		break;
	case 'view_user_log':
		top_header();
		utilities_view_user_log();
		bottom_footer();
		break;
	case 'clear_user_log':
		utilities_clear_user_log();
		utilities_view_user_log();
		break;
	case 'view_tech':
		$php_info = utilities_php_modules();

		top_header();
		utilities_view_tech($php_info);
		bottom_footer();
		break;
	case 'view_boost_status':
		top_header();
		boost_display_run_status();
		bottom_footer();
		break;
	case 'view_snmpagent_cache':
		top_header();
		snmpagent_utilities_run_cache();
		bottom_footer();
		break;
	case 'rebuild_snmpagent_cache';
		snmpagent_cache_rebuilt();
		header('Location: utilities.php?action=view_snmpagent_cache');exit;
		break;
	case 'view_snmpagent_events':
		top_header();
		snmpagent_utilities_run_eventlog();
		bottom_footer();
		break;
	default:
		if (!api_plugin_hook_function('utilities_action', get_request_var('action'))) {
			top_header();
			utilities();
			bottom_footer();
		}
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

function utilities_php_modules() {

	/*
	   Gather phpinfo into a string variable - This has to be done before
	   any headers are sent to the browser, as we are going to do some
	   output buffering fun
	*/

	ob_start();
	phpinfo(INFO_MODULES);
	$php_info = ob_get_contents();
	ob_end_clean();

	/* Remove nasty style sheets, links and other junk */
	$php_info = str_replace("\n", '', $php_info);
	$php_info = preg_replace('/^.*\<body\>/', '', $php_info);
	$php_info = preg_replace('/\<\/body\>.*$/', '', $php_info);
	$php_info = preg_replace('/\<a.*\>/U', '', $php_info);
	$php_info = preg_replace('/\<\/a\>/', '<hr>', $php_info);
	$php_info = preg_replace('/\<img.*\>/U', '', $php_info);
	$php_info = preg_replace('/\<\/?address\>/', '', $php_info);

	return $php_info;
}

function memory_bytes($val) {
	$val = trim($val);
	$last = strtolower($val{strlen($val)-1});
	switch($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function memory_readable($val) {

	if ($val < 1024) {
		$val_label = 'bytes';
	}elseif ($val < 1048576) {
		$val_label = 'K';
		$val /= 1024;
	}elseif ($val < 1073741824) {
		$val_label = 'M';
		$val /= 1048576;
	}else{
		$val_label = 'G';
		$val /= 1073741824;
	}

	return $val . $val_label;
}

function utilities_view_tech($php_info = '') {
	global $database_default, $config, $rrdtool_versions, $poller_options, $input_types;

	/* Get table status */
	$tables = db_fetch_assoc_prepared('SELECT * 
		FROM information_schema.tables 
		WHERE table_schema = ?', array($database_default));

	/* Get poller stats */
	$poller_item = db_fetch_assoc('SELECT action, count(action) AS total 
		FROM poller_item 
		GROUP BY action');

	/* Get System Memory */
	$memInfo = array();
	if ($config['cacti_server_os'] == 'win32') {
		exec('wmic os get FreePhysicalMemory', $memInfo['FreePhysicalMemory']);
		exec('wmic os get FreeSpaceInPagingFiles', $memInfo['FreeSpaceInPagingFiles']);
		exec('wmic os get FreeVirtualMemory', $memInfo['FreeVirtualMemory']);
		exec('wmic os get SizeStoredInPagingFiles', $memInfo['SizeStoredInPagingFiles']);
		exec('wmic os get TotalVirtualMemorySize', $memInfo['TotalVirtualMemorySize']);
		exec('wmic os get TotalVisibleMemorySize', $memInfo['TotalVisibleMemorySize']);
		if (sizeof($memInfo)) {
			foreach($memInfo as $key => $values) {
				$memInfo[$key] = $values[1];
			}
		}
	}else{
		$data = explode("\n", file_get_contents('/proc/meminfo'));
		foreach($data as $l) {
			if (trim($l) != '') {
				list($key, $val) = explode(':', $l);
				$val = trim($val, " kBb\r\n");
				$memInfo[$key] = round($val * 1024,0);
			}
		}
	}

	/* Get system stats */
	$host_count  = db_fetch_cell('SELECT COUNT(*) FROM host');
	$graph_count = db_fetch_cell('SELECT COUNT(*) FROM graph_local');
	$data_count  = db_fetch_assoc('SELECT i.type_id, COUNT(i.type_id) AS total 
		FROM data_template_data AS d, data_input AS i 
		WHERE d.data_input_id = i.id 
		AND local_data_id <> 0 
		GROUP BY i.type_id');

	/* Get RRDtool version */
	$rrdtool_version = 'Unknown';
	if ((file_exists(read_config_option('path_rrdtool'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_rrdtool'))))) {

		$out_array = array();
		exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')), $out_array);
		if (sizeof($out_array) > 0) {
			if (preg_match('/^RRDtool ([1-9]\.[0-9])/', $out_array[0], $m)) {
				$rrdtool_version = 'rrd-'. $m[1] .'.x';
			}
		}
	}

	/* Get SNMP cli version */
	if ((file_exists(read_config_option('path_snmpget'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_snmpget'))))) {
		$snmp_version = shell_exec(cacti_escapeshellcmd(read_config_option('path_snmpget')) . ' -V 2>&1');
	}else{
		$snmp_version = "<span class='deviceDown'>NET-SNMP Not Installed or its paths are not set.  Please install if you wish to monitor SNMP enabled devices.</span>";
	}

	/* Check RRDTool issues */
	$rrdtool_error = '';
	if ($rrdtool_version != read_config_option('rrdtool_version')) {
		$rrdtool_error .= "<br><span class='deviceDown'>ERROR: Installed RRDTool version does not match configured version.<br>Please visit the <a href='" . htmlspecialchars('settings.php?tab=general') . "'>Configuration Settings</a> and select the correct RRDTool Utility Version.</span><br>";
	}
	$graph_gif_count = db_fetch_cell('SELECT COUNT(*) FROM graph_templates_graph WHERE image_format_id = 2');
	if ($graph_gif_count > 0) {
		$rrdtool_error .= "<br><span class='deviceDown'>ERROR: RRDTool 1.2.x+ does not support the GIF images format, but " . $graph_gif_count . ' graph(s) and/or templates have GIF set as the image format.</span><br>';
	}

	/* Get spine version */
	$spine_version = 'Unknown';
	if ((file_exists(read_config_option('path_spine'))) && ((function_exists('is_executable')) && (is_executable(read_config_option('path_spine'))))) {
		$out_array = array();
		exec(read_config_option('path_spine') . ' --version', $out_array);
		if (sizeof($out_array) > 0) {
			$spine_version = $out_array[0];
		}
	}

	/* ================= input validation ================= */
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'summary'  => 'Summary',
		'database' => 'Database',
		'phpinfo'  => 'PHP Info',
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_ts_tabs', 'summary');
	$current_tab = get_nfilter_request_var('tab');

	$header_label = 'Technical Support [ ' . $tabs[get_request_var('tab')] . ' ]';

	if (sizeof($tabs)) {
		/* draw the tabs */
		print "<div class='tabs'><nav><ul>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") . 
				" href='" . htmlspecialchars($config['url_path'] .
				'utilities.php?action=view_tech' .
				'&tab=' . $tab_short_name) .
				"'>$tabs[$tab_short_name]</a></li>\n";
		}

		api_plugin_hook('user_admin_tab');

		print "</ul></nav></div>\n";
	}

	/* Display tech information */
	html_start_box($header_label, '100%', '', '3', 'center', '');

	if (get_request_var('tab') == 'summary') {
		html_header(array('General Information'), 2);
		form_alternate_row();
		print "<td>Date</td>\n";
		print "<td>" . date('r') . "</td>\n";
		form_end_row();

		api_plugin_hook_function('custom_version_info');

		form_alternate_row();
		print "<td>Cacti Version</td>\n";
		print "<td>" . $config['cacti_version'] . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Cacti OS</td>\n";
		print "<td>" . $config['cacti_server_os'] . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>RSA Fingerprint</td>\n";
		print "<td>" . read_config_option('rsa_fingerprint') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>NET-SNMP Version</td>\n";
		print "<td>" . $snmp_version . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>RRDTool Version</td>\n";
		print "<td>" . $rrdtool_versions[$rrdtool_version] . ' ' . $rrdtool_error . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Devices</td>\n";
		print "<td>" . $host_count . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Graphs</td>\n";
		print "<td>" . $graph_count . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Data Sources</td>\n";
		print "<td>";
		$data_total = 0;
		if (sizeof($data_count)) {
			foreach ($data_count as $item) {
				print $input_types[$item['type_id']] . ': ' . $item['total'] . '<br>';
				$data_total += $item['total'];
			}
			print 'Total: ' . $data_total;
		}else{
			print "<span class='deviceDown'>0</span>";
		}
		print "</td>\n";
		form_end_row();

		html_header(array('Poller Information'), 2);

		form_alternate_row();
		print "<td>Interval</td>\n";
		print "<td>" . read_config_option('poller_interval') . "</td>\n";
		if (file_exists(read_config_option('path_spine')) && $poller_options[read_config_option('poller_type')] == 'spine') {
			$type = $spine_version;
		} else {
			$type = $poller_options[read_config_option('poller_type')];
		}
		form_end_row();

		form_alternate_row();
		print "<td>Type</td>\n";
		print "<td>" . $type . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Items</td>\n";
		print "<td>";
		$total = 0;
		if (sizeof($poller_item)) {
			foreach ($poller_item as $item) {
				print 'Action[' . $item['action'] . ']: ' . $item['total'] . '<br>';
				$total += $item['total'];
			}
			print 'Total: ' . $total;
		}else{
			print "<span class='deviceDown'>No items to poll</span>";
		}
		print "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Concurrent Processes</td>\n";
		print "<td>" . read_config_option('concurrent_processes') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Max Threads</td>\n";
		print "<td>" . read_config_option('max_threads') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>PHP Servers</td>\n";
		print "<td>" . read_config_option('php_servers') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Script Timeout</td>\n";
		print "<td>" . read_config_option('script_timeout') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Max OID</td>\n";
		print "<td>" . read_config_option('max_get_size') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>Last Run Statistics</td>\n";
		print "<td>" . read_config_option('stats_poller') . "</td>\n";
		form_end_row();

		html_header(array('System Memory'), 2);
		$i = 0;
		foreach($memInfo as $name => $value) {
			if ($config['cacti_server_os'] == 'win32') {
				form_alternate_row();
				print "<td>$name</td>\n";
				print "<td>" . round($value/1024/1024,0) . " MB</td>\n";
				form_end_row();
			}else{
				switch($name) {
				case 'SwapTotal':
				case 'SwapFree':
				case 'MemTotal':
				case 'MemFree':
				case 'Buffers':
				case 'Active':
				case 'Inactive':
					form_alternate_row();
					print "<td>$name</td>\n";
					print "<td>" . number_format($value/1024/1024,0) . " MB</td>\n";
					form_end_row();
				}
			}
			$i++;
		}
		print "</td>\n";
		form_end_row();

		html_header(array('PHP Information'), 2);

		form_alternate_row();
		print "<td>PHP Version</td>\n";
		if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
			print "<td>" . PHP_VERSION . "</td>\n";
		}else{
			print "<td>" . PHP_VERSION . "</br><span class='deviceDown'>PHP Version 5.5.0+ is recommended due to strong password hashing support.</span></td>\n";
		}
		form_end_row();

		form_alternate_row();
		print "<td>PHP OS</td>\n";
		print "<td>" . PHP_OS . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>PHP uname</td>\n";
		print "<td>";
		if (function_exists('php_uname')) {
			print php_uname();
		}else{
			print 'N/A';
		}
		print "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>PHP SNMP</td>\n";
		print "<td>";
		if (function_exists('snmpget')) {
			print 'Installed';
		} else {
			print 'Not Installed';
		}
		print "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>max_execution_time</td>\n";
		print "<td>" . ini_get('max_execution_time') . "</td>\n";
		form_end_row();

		form_alternate_row();
		print "<td>memory_limit</td>\n";
		print "<td>" . ini_get('memory_limit');

		/* Calculate memory suggestion based off of data source count */
		$memory_suggestion = $data_total * 32768;

		/* Set minimum - 16M */
		if ($memory_suggestion < 16777216) {
			$memory_suggestion = 16777216;
		}
		/* Set maximum - 512M */
		if ($memory_suggestion > 536870912) {
			$memory_suggestion = 536870912;
		}
		/* Suggest values in 8M increments */
		$memory_suggestion = round($memory_suggestion / 8388608) * 8388608;
		if (memory_bytes(ini_get('memory_limit')) < $memory_suggestion) {
			print "<br><span class='deviceDown'>";
			if ((ini_get('memory_limit') == -1)) {
				print "You've set memory limit to 'unlimited'.<br/>";
			}
			print 'It is highly suggested that you alter you php.ini memory_limit to ' . memory_readable($memory_suggestion) . ' or higher. <br/>
				This suggested memory value is calculated based on the number of data source present and is only to be used as a suggestion, actual values may vary system to system based on requirements.';
			print '</span><br>';
		}
		print "</td>\n";
		form_end_row();

		// MySQL Important Variables
		$variables = array_rekey(db_fetch_assoc('SHOW GLOBAL VARIABLES'), 'Variable_name', 'Value');

		$recommendations = array(
			'version' => array(
				'value' => '5.6',
				'measure' => 'gt',
				'comment' => 'MySQL 5.6 is great release, and a very good version to choose.  
					Other choices today include MariaDB which is very popular and addresses some issues
					with the C API that negatively impacts spine in MySQL 5.5, and for some reason
					Oracle has chosen not to fix in MySQL 5.5.  So, avoid MySQL 5.5 at all costs.'
				)
		);

		if ($variables['version'] < '5.6') {
			$recommendations += array(
				'collation_server' => array(
					'value' => 'utf8_general_ci',
					'measure' => 'equal',
					'comment' => 'When using Cacti with languages other than english, it is important to use
						the utf8_general_ci collation type as some characters take more than a single byte.'
					),
				'character_set_client' => array(
					'value' => 'utf8',
					'measure' => 'equal',
					'comment' => 'When using Cacti with languages other than english, it is important ot use
						the utf8 character set as some characters take more than a single byte.'
					)
			);
		}else{
			$recommendations += array(
				'collation_server' => array(
					'value' => 'utf8mb4_col',
					'measure' => 'equal',
					'comment' => 'When using Cacti with languages other than english, it is important to use
						the utf8mb4_col collation type as some characters take more than a single byte.'
					),
				'character_set_client' => array(
					'value' => 'utf8mb4',
					'measure' => 'equal',
					'comment' => 'When using Cacti with languages other than english, it is important ot use
						the utf8mb4 character set as some characters take more than a single byte.'
					)
			);
		}
	
		$recommendations += array(
			'max_connections' => array(
				'value'   => '100', 
				'measure' => 'gt', 
				'comment' => 'Depending on the number of logins and use of spine data collector, 
					MySQL will need many connections.  The calculation for spine is:
					total_connections = total_processes * (total_threads + script_servers + 1), then you
					must leave headroom for user connections, which will change depending on the number of
					concurrent login accounts.'
				),
			'table_cache' => array(
				'value'   => '200',
				'measure' => 'gt',
				'comment' => 'Keeping the table cache larger means less file open/close operations when
					using innodb_file_per_table.'
				),
			'max_allowed_packet' => array(
				'value'   => 16777216,
				'measure' => 'gt',
				'comment' => 'With Remote polling capabilities, large amounts of data 
					will be synced from the main server to the remote pollers.  
					Therefore, keep this value at or above 16M.'
				),
			'tmp_table_size' => array(
				'value'   => '64M',
				'measure' => 'gtm',
				'comment' => 'When executing subqueries, having a larger temporary table size, 
					keep those temporary tables in memory.'
				),
			'join_buffer_size' => array(
				'value'   => '64M',
				'measure' => 'gtm',
				'comment' => 'When performing joins, if they are below this size, they will 
					be kept in memory and never writen to a temporary file.'
				),
			'innodb_file_per_table' => array(
				'value'   => 'ON',
				'measure' => 'equal',
				'comment' => 'When using InnoDB storage it is important to keep your table spaces
					separate.  This makes managing the tables simpler for long time users of MySQL.
					If you are running with this currently off, you can migrate to the per file storage
					by enabling the feature, and then running an alter statement on all InnoDB tables.'
				),
			'innodb_buffer_pool_size' => array(
				'value'   => '25',
				'measure' => 'pmem',
				'comment' => 'InnoDB will hold as much tables and indexes in system memory as is possible.
					Therefore, you should make the innodb_buffer_pool large enough to hold as much
					of the tables and index in memory.  Checking the size of the /var/lib/mysql/cacti
					directory will help in determining this value.  We are recommending 25% of your systems
					total memory, but your requirements will vary depending on your systems size.'
				),
			'innodb_doublewrite' => array(
				'value'   => 'OFF',
				'measure' => 'equal',
				'comment' => 'With modern SSD type storage, this operation actually degrades the disk
					more rapidly and adds a 50% overhead on all write operations.'
				),
			'innodb_additional_mem_pool_size' => array(
				'value'   => '80M',
				'measure' => 'gtm',
				'comment' => 'This is where metadata is stored. If you had a lot of tables, it would be useful to increase this.'
				),
			'innodb_flush_log_at_trx_commit' => array(
				'value'   => '2',
				'measure' => 'equal',
				'comment' => 'Setting this value to 2 means that you will flush all transactions every
					second rather than at commit.  This allows MySQL to perform writing less often'
				),
			'innodb_lock_wait_timeout' => array(
				'value'   => '50',
				'measure' => 'gt',
				'comment' => 'Rogue queries should not for the database to go offline to others.  Kill these
					queries before they kill your system.'
				),
		);

		if ($variables['version'] < '5.6') {
			$recommendations += array(
				'innodb_file_io_threads' => array(
					'value'   => '16',
					'measure' => 'gt',
					'comment' => 'With modern SSD type storage, having multiple io threads is advantagious for
						applications with high io characteristics.'
					)
			);
		}else{
			$recommendations += array(
				'innodb_read_io_threads' => array(
					'value'   => '32',
					'measure' => 'gt',
					'comment' => 'With modern SSD type storage, having multiple read io threads is advantagious for
						applications with high io characteristics.'
					),
				'innodb_write_io_threads' => array(
					'value'   => '16',
					'measure' => 'gt',
					'comment' => 'With modern SSD type storage, having multiple write io threads is advantagious for
						applications with high io characteristics.'
					),
				'innodb_buffer_pool_instances' => array(
					'value' => '16',
					'measure' => 'present',
					'comment' => 'MySQL will divide the innodb_buffer_pool into memory regions to improve performance.
						The max value is 64.  When your innodb_buffer_pool is less than 1GB, you should use the pool size
						divided by 128MB.  Continue to use this equation upto the max of 64.'
					)
			);
		}

		html_header(array('MySQL Tuning (/etc/my.cnf) - [ <a class="linkOverDark" href="https://dev.mysql.com/doc/refman/' . substr($variables['version'],0,3) . '/en/server-system-variables.html">Documentation</a> ] Note: Many changes below require a database restart'), 2);

		form_alternate_row();
		print "<td colspan='2' style='text-align:left;padding:0px'>";
		print "<table id='mysql' class='cactiTable' style='width:100%'>\n";
		print "<thead>\n";
		print "<tr class='tableHeader'>\n";
		print "  <th class='tableSubHeaderColumn'>Variable</th>\n";
		print "  <th class='tableSubHeaderColumn'>Current Value</th>\n";
		print "  <th class='tableSubHeaderColumn'>Recommended Value</th>\n";
		print "  <th class='tableSubHeaderColumn'>Comments</th>\n";
		print "</tr>\n";
		print "</thead>\n";

		foreach($recommendations as $name => $r) {
			if (isset($variables[$name])) {
				$class = '';

				form_alternate_row();
				switch($r['measure']) {
				case 'gtm':
					$value = trim($r['value'], 'M') * 1024 * 1024;
					if ($variables[$name] < $value) {
						$class = 'deviceDown';
					}

					print "<td>" . $name . "</td>\n";
					print "<td class='$class'>" . ($variables[$name]/1024/1024) . "M</td>\n";
					print "<td>>= " . $r['value'] . "</td>\n";
					print "<td class='$class'>" . $r['comment'] . "</td>\n";

					break;
				case 'gt':
					if ($variables[$name] < $r['value']) {
						$class = 'deviceDown';
					}

					print "<td>" . $name . "</td>\n";
					print "<td class='$class'>" . $variables[$name] . "</td>\n";
					print "<td>>= " . $r['value'] . "</td>\n";
					print "<td class='$class'>" . $r['comment'] . "</td>\n";

					break;
				case 'equal':
					if ($variables[$name] != $r['value']) {
						$class = 'deviceDown';
					}

					print "<td>" . $name . "</td>\n";
					print "<td class='$class'>" . $variables[$name] . "</td>\n";
					print "<td>=" . $r['value'] . "</td>\n";
					print "<td class='$class'>" . $r['comment'] . "</td>\n";

					break;
				case 'pmem':
					if (isset($memInfo['MemTotal'])) {
						$totalMem = $memInfo['MemTotal'];
					}else{
						$totalMem = $memInfo['TotalVisibleMemorySize'];
					}

					if ($variables[$name] < ($r['value']*$totalMem/100)) {
						$class = 'deviceDown';
					}

					print "<td>" . $name . "</td>\n";
					print "<td class='$class'>" . round($variables[$name]/1024/1024,0) . "M</td>\n";
					print "<td>>=" . round($r['value']*$totalMem/100/1024/1024,0) . "M</td>\n";
					print "<td class='$class'>" . $r['comment'] . "</td>\n";

					break;
				}
				form_end_row();
			}
		}
		print "</table>\n";
		print "</td>\n";
		form_end_row();

	}elseif (get_request_var('tab') == 'database') {

		html_header(array('MySQL Table Information - Sizes in KBytes'), 2);

		form_alternate_row();
		print "		<td colspan='2' style='text-align:left;padding:0px'>";
		if (sizeof($tables) > 0) {
			print "<table id='tables' class='cactiTable' style='width:100%'>\n";
			print "<thead>\n";
			print "<tr class='tableHeader'>\n";
			print "  <th class='tableSubHeaderColumn'>Name</th>\n";
			print "  <th class='tableSubHeaderColumn'>Engine</th>\n";
			print "  <th class='tableSubHeaderColumn' style='text-align:right;'>Rows</th>\n";
			print "  <th class='tableSubHeaderColumn'>Avg Row Length</th>\n";
			print "  <th class='tableSubHeaderColumn'>Data Length</th>\n";
			print "  <th class='tableSubHeaderColumn'>Index Length</th>\n";
			print "  <th class='tableSubHeaderColumn'>Collation</th>\n";
			print "  <th class='tableSubHeaderColumn'>Comment</th>\n";
			print "</tr>\n";
			print "</thead>\n";
			foreach ($tables as $table) {
				form_alternate_row();
				print '<td>' . $table['TABLE_NAME'] . "</td>\n";
				print '<td>' . $table['ENGINE'] . "</td>\n";
				print '<td class="right">' . number_format($table['TABLE_ROWS']) . "</td>\n";
				print '<td class="right">' . number_format($table['AVG_ROW_LENGTH']/1024) . "</td>\n";
				print '<td class="right">' . number_format($table['DATA_LENGTH']/1024) . "</td>\n";
				print '<td class="right">' . number_format($table['INDEX_LENGTH']/1024) . "</td>\n";
				print '<td>' . $table['TABLE_COLLATION'] . "</td>\n";
				print '<td>' . $table['TABLE_COMMENT'] . "</td>\n";
				form_end_row();
			}

			print "</table>\n";
		}else{
			print 'Unable to retrieve table status';
		}
		print "</td>\n";
		form_end_row();

	}else{

		html_header(array('PHP Module Information'), 2);
		form_alternate_row();
		print "<td colspan='2'>" . $php_info . "</td>\n";
		form_end_row();

	}

	html_end_box();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#tables').tablesorter({
			widgets: ['zebra'],
			widgetZebra: { css: ['even', 'odd'] },
			headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
			cssIconAsc: 'fa-sort-asc',
			cssIconDesc: 'fa-sort-desc',
			cssIconNone: 'fa-sort',
			cssIcon: 'fa'
		});
	});
	</script>
	<?php
}

function utilities_view_user_log() {
	global $auth_realms, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
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
			'default' => 'time', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'username' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => '-1', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'result' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_userlog');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	?>
	<script type="text/javascript">
	function clearFilter() {
		strURL = '?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function purgeLog() {
		strURL = '?action=clear_user_log&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_userlog').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL  = '?username=' + $('#username').val();
		strURL += '&result=' + $('#result').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&action=view_user_log';
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}
	</script>
	<?php

	html_start_box('User Login History', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_userlog' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td>
						User
					</td>
					<td>
						<select id='username' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('username') == '-1') {?> selected<?php }?>>All</option>
							<option value='-2'<?php if (get_request_var('username') == '-2') {?> selected<?php }?>>Deleted/Invalid</option>
							<?php
							$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth ORDER BY username');

							if (sizeof($users) > 0) {
							foreach ($users as $user) {
								print "<option value='" . $user['username'] . "'"; if (get_request_var('username') == $user['username']) { print ' selected'; } print '>' . $user['username'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Result
					</td>
					<td>
						<select id='result' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('result') == '-1') {?> selected<?php }?>>Any</option>
							<option value='1'<?php if (get_request_var('result') == '1') {?> selected<?php }?>>Success - Pswd</option>
							<option value='2'<?php if (get_request_var('result') == '2') {?> selected<?php }?>>Success - Token</option>
							<option value='0'<?php if (get_request_var('result') == '0') {?> selected<?php }?>>Failed</option>
						</select>
					</td>
					<td>
						Attempts
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?>
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
					<td>
						<input type='button' id='purge' value='Purge' title='Purge User Log'>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' name='action' value='view_user_log'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by username */
	if (get_request_var('username') == '-2') {
		$sql_where = 'WHERE ul.username NOT IN (SELECT DISTINCT username FROM user_auth)';
	}elseif (get_request_var('username') != '-1') {
		$sql_where = "WHERE ul.username='" . get_request_var('username') . "'";
	}

	/* filter by result */
	if (get_request_var('result') != '-1') {
		if (strlen($sql_where)) {
			$sql_where .= ' AND ul.result=' . get_request_var('result');
		}else{
			$sql_where = 'WHERE ul.result=' . get_request_var('result');
		}
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		if (strlen($sql_where)) {
			$sql_where .= " AND (ul.username LIKE '%%" . get_request_var('filter') . "%%'
				OR ul.time LIKE '%%" . get_request_var('filter') . "%%'
				OR ua.full_name LIKE '%%" . get_request_var('filter') . "%%'
				OR ul.ip LIKE '%%" . get_request_var('filter') . "%%')";
		}else{
			$sql_where = "WHERE (ul.username LIKE '%%" . get_request_var('filter') . "%%'
				OR ul.time LIKE '%%" . get_request_var('filter') . "%%'
				OR ua.full_name LIKE '%%" . get_request_var('filter') . "%%'
				OR ul.ip LIKE '%%" . get_request_var('filter') . "%%')";
		}
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where");

	$user_log_sql = "SELECT ul.username, ua.full_name, ua.realm,
		ul.time, ul.result, ul.ip
		FROM user_auth AS ua
		RIGHT JOIN user_log AS ul
		ON ua.username=ul.username
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$user_log = db_fetch_assoc($user_log_sql);

	$nav = html_nav_bar('utilities.php?action=view_user_log&username=' . get_request_var('username') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, 'User Logins', 'page', 'main');

	print $nav;

	$display_text = array(
		'username' => array('User', 'ASC'),
		'full_name' => array('Full Name', 'ASC'),
		'realm' => array('Authentication Realm', 'ASC'),
		'time' => array('Date', 'ASC'),
		'result' => array('Result', 'DESC'),
		'ip' => array('IP Address', 'DESC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=view_user_log');

	if (sizeof($user_log) > 0) {
		foreach ($user_log as $item) {
			form_alternate_row('', true);
			?>
			<td class='nowrap'>
				<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['username']))) : htmlspecialchars($item['username']));?>
			</td>
			<td class='nowrap'>
				<?php if (isset($item['full_name'])) {
						print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['full_name']))) : htmlspecialchars($item['full_name']));
					}else{
						print '(User Removed)';
					}
				?>
			</td>
			<td class='nowrap'>
				<?php if (isset($auth_realms[$item['realm']])) {
						print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", $auth_realms[$item['realm']])) : $auth_realms[$item['realm']]);
					}else{
						print 'N/A';
					}
				?>
			</td>
			<td class='nowrap'>
				<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['time']))) : htmlspecialchars($item['time']));?>
			</td>
			<td class='nowrap'>
				<?php print ($item['result'] == 0 ? 'Failed':($item['result'] == 1 ? 'Success - Pswd':'Success - Token'));?>
			</td>
			<td class='nowrap'>
				<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['ip']))) : htmlspecialchars($item['ip']));?>
			</td>
			</tr>
			<?php
		}

		print $nav;
	}

	html_end_box();
}

function utilities_clear_user_log() {
	$users = db_fetch_assoc('SELECT DISTINCT username FROM user_auth');

	if (sizeof($users)) {
		/* remove active users */
		foreach ($users as $user) {
			$total_rows = db_fetch_cell_prepared('SELECT COUNT(username) FROM user_log WHERE username = ? AND result = 1', array($user['username']));
			if ($total_rows > 1) {
				db_execute_prepared('DELETE FROM user_log WHERE username = ? AND result = 1 ORDER BY time LIMIT ' . ($total_rows - 1), array($user['username']));
			}
			db_execute_prepared('DELETE FROM user_log WHERE username = ? AND result = 0', array($user['username']));
		}

		/* delete inactive users */
		db_execute('DELETE FROM user_log WHERE user_id NOT IN (SELECT id FROM user_auth) OR username NOT IN (SELECT username FROM user_auth)');

	}
}

function utilities_view_logfile() {
	global $log_tail_lines, $page_refresh_interval, $refresh;

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/rrd.log';
	}

	/* helps determine output color */
	$linecolor = True;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'tail_lines' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'message_type' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'reverse' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '1'
			),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => read_config_option('log_refresh_interval')
			)
	);

	validate_store_request_vars($filters, 'sess_log');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page'] = 'utilities.php?action=view_logfile&header=false';

	top_header();

	?>
	<script type="text/javascript">
    var refreshIsLogout=false;
    var refreshPage='<?php print $refresh['page'];?>';
    var refreshMSeconds=<?php print $refresh['seconds']*1000;?>;

	function purgeLog() {
		strURL = '?action=view_logfile&purge=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refreshme').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeLog();
		});

		$('#form_logfile').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	function applyFilter() {
		strURL  = '?tail_lines=' + $('#tail_lines').val();
		strURL += '&message_type=' + $('#message_type').val();
		strURL += '&refresh=' + $('#refresh').val();
		strURL += '&reverse=' + $('#reverse').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&action=view_logfile';
		strURL += '&header=false';
		refreshMSeconds=$('#refresh').val()*1000;
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL  = '?clear=1';
		strURL += '&action=view_logfile';
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}
	</script>
	<?php

	html_start_box('Log File Filters', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_logfile' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td class='nowrap'>
						Tail Lines
					</td>
					<td>
						<select id='tail_lines' onChange='applyFilter()'>
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if (get_request_var('tail_lines') == $tail_lines) { print ' selected'; } print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td class='nowrap'>
						Message Type
					</td>
					<td>
						<select id='message_type' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('message_type') == '-1') {?> selected<?php }?>>All</option>
							<option value='1'<?php if (get_request_var('message_type') == '1') {?> selected<?php }?>>Stats</option>
							<option value='2'<?php if (get_request_var('message_type') == '2') {?> selected<?php }?>>Warnings</option>
							<option value='3'<?php if (get_request_var('message_type') == '3') {?> selected<?php }?>>Errors</option>
							<option value='4'<?php if (get_request_var('message_type') == '4') {?> selected<?php }?>>Debug</option>
							<option value='5'<?php if (get_request_var('message_type') == '5') {?> selected<?php }?>>SQL Calls</option>
						</select>
					</td>
					<td>
						<input type='button' id='refreshme' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters'>
					</td>
					<td>
						<input type='button' id='purge' value='Purge' title='Purge Log File'>
					</td>
				</tr>
				<tr>
					<td>
						Refresh
					</td>
					<td>
						<select id='refresh' onChange='applyFilter()'>
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if (get_request_var('refresh') == $seconds) { print ' selected'; } print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td class='nowrap'>
						Display Order
					</td>
					<td>
						<select id='reverse' onChange='applyFilter()'>
							<option value='1'<?php if (get_request_var('reverse') == '1') {?> selected<?php }?>>Newest First</option>
							<option value='2'<?php if (get_request_var('reverse') == '2') {?> selected<?php }?>>Oldest First</option>
						</select>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' size='75' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='view_logfile'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* read logfile into an array and display */
	$logcontents = tail_file($logfile, get_request_var('tail_lines'), get_request_var('message_type'), get_request_var('filter'));

	if (get_request_var('reverse') == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (get_request_var('message_type') > 0) {
		$start_string = '<strong>Log File</strong> [Total Lines: ' . sizeof($logcontents) . ' - Non-Matching Items Hidden]';
	}else{
		$start_string = '<strong>Log File</strong> [Total Lines: ' . sizeof($logcontents) . ' - All Items Shown]';
	}

	html_start_box($start_string, '100%', '', '3', 'center', '');

	$i = 0;
	$j = 0;
	$linecolor = false;
	foreach ($logcontents as $item) {
		$host_start = strpos($item, 'Device[');
		$ds_start   = strpos($item, 'DS[');

		$new_item = '';

		if ((!$host_start) && (!$ds_start)) {
			$new_item = htmlspecialchars($item);
		}else{
			while ($host_start) {
				$host_end   = strpos($item, ']', $host_start);
				$host_id    = substr($item, $host_start+5, $host_end-($host_start+5));
				$new_item   = $new_item . htmlspecialchars(substr($item, 0, $host_start + 5)) . "<a href='" . htmlspecialchars('host.php?action=edit&id=' . $host_id) . "'>" . htmlspecialchars(substr($item, $host_start + 5, $host_end-($host_start + 5))) . '</a>';
				$item       = substr($item, $host_end);
				$host_start = strpos($item, 'Device[');
			}

			$ds_start = strpos($item, 'DS[');
			while ($ds_start) {
				$ds_end   = strpos($item, ']', $ds_start);
				$ds_id    = substr($item, $ds_start+3, $ds_end-($ds_start+3));
				$new_item = $new_item . htmlspecialchars(substr($item, 0, $ds_start + 3)) . "<a href='" . htmlspecialchars('data_sources.php?action=ds_edit&id=' . $ds_id) . "'>" . htmlspecialchars(substr($item, $ds_start + 3, $ds_end-($ds_start + 3))) . '</a>';
				$item     = substr($item, $ds_end);
				$ds_start = strpos($item, 'DS[');
			}

			$new_item = $new_item . htmlspecialchars($item);
		}

		/* get the background color */
		if ((substr_count($new_item, 'ERROR')) || (substr_count($new_item, 'FATAL'))) {
			$class = 'clogError';
		}elseif (substr_count($new_item, 'WARN')) {
			$class = 'clogWarning';
		}elseif (substr_count($new_item, ' SQL ')) {
			$class = 'clogSQL';
		}elseif (substr_count($new_item, 'DEBUG')) {
			$class = 'clogDebug';
		}elseif (substr_count($new_item, 'STATS')) {
			$class = 'clogStats';
		}else{
			if ($linecolor) {
				$class = 'odd';
			}else{
				$class = 'even';
			}
			$linecolor = !$linecolor;
		}

		print "<tr class='" . $class . "'><td>" . $new_item . "</td></tr>\n";

		$j++;
		$i++;

		if ($j > 1000) {
			print "<tr class='clogLimit'><td>>>>>  LINE LIMIT OF 1000 LINES REACHED!!  <<<<</td></tr>\n";

			break;
		}
	}

	html_end_box();

	bottom_footer();
}

function utilities_clear_logfile() {
	load_current_session_value('refresh', 'sess_logfile_refresh', read_config_option('log_refresh_interval'));

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page'] = 'utilities.php?action=view_logfile&header=false';

	top_header();

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	html_start_box('Clear Cacti Log File', '100%', '', '3', 'center', '');
	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			$timestamp = date('m/d/Y h:i:s A');
			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, $timestamp . " - WEBUI: Cacti Log Cleared from Web Management Interface\n");
			fclose($log_fh);
			print '<tr><td>Cacti Log File Cleared</td></tr>';
		}else{
			print "<tr><td class='deviceDown'><b>Error: Unable to clear log, no write permissions.<b></td></tr>";
		}
	}else{
		print "<tr><td class='deviceDown'><b>Error: Unable to clear log, file does not exist.</b></td></tr>";
	}
	html_end_box();
}

function utilities_view_snmp_cache() {
	global $poller_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
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
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'snmp_query_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'poller_action' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_usnmp');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = '300';
	$refresh['page'] = 'utilities.php?action=view_snmp_cache&header=false';

	?>
	<script type="text/javascript">
    var refreshIsLogout=false;
    var refreshPage='<?php print $refresh['page'];?>';
    var refreshMSeconds=<?php print $refresh['seconds']*1000;?>;

	function applyFilter() {
		strURL  = '?host_id=' + $('#host_id').val();
		strURL += '&snmp_query_id=' + $('#snmp_query_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&action=view_snmp_cache';
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = '?action=view_snmp_cache&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpcache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('SNMP Cache Items', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_snmpcache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						Device
					</td>
					<td>
						<select id='host_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var('host_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							if (get_request_var('snmp_query_id') == -1) {
								$hosts = db_fetch_assoc('SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											ORDER by host.description');
							}else{
								$hosts = db_fetch_assoc_prepared('SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											AND host_snmp_cache.snmp_query_id = ?
											ORDER by host.description', array(get_request_var('snmp_query_id')));
							}
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host['id'] . "'"; if (get_request_var('host_id') == $host['id']) { print ' selected'; } print '>' . $host['description'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Query Name
					</td>
					<td>
						<select id='snmp_query_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>>Any</option>
							<?php
							if (get_request_var('host_id') == -1) {
								$snmp_queries = db_fetch_assoc('SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											ORDER by snmp_query.name');
							}else{
								$snmp_queries = db_fetch_assoc_prepared("SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache, snmp_query,host)
											WHERE host_snmp_cache.host_id = host.id
											AND host_snmp_cache.host_id = ?
											AND host_snmp_cache.snmp_query_id = snmp_query.id
											ORDER by snmp_query.name", array(get_request_var('host_id')));
							}
							if (sizeof($snmp_queries) > 0) {
							foreach ($snmp_queries as $snmp_query) {
								print "<option value='" . $snmp_query['id'] . "'"; if (get_request_var('snmp_query_id') == $snmp_query['id']) { print ' selected'; } print '>' . $snmp_query['name'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Rows
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?>
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
						<input type='button' id='clear' value='Clear' title='Clear Fitlers'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_id') == '0') {
		$sql_where .= ' AND host.id=0';
	}elseif (!isempty_request_var('host_id')) {
		$sql_where .= ' AND host.id=' . get_request_var('host_id');
	}

	/* filter by query name */
	if (get_request_var('snmp_query_id') == '-1') {
		/* Show all items */
	}elseif (!isempty_request_var('snmp_query_id')) {
		$sql_where .= ' AND host_snmp_cache.snmp_query_id=' . get_request_var('snmp_query_id');
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (host.description LIKE '%%" . get_request_var('filter') . "%%'
			OR snmp_query.name LIKE '%%" . get_request_var('filter') . "%%'
			OR host_snmp_cache.field_name LIKE '%%" . get_request_var('filter') . "%%'
			OR host_snmp_cache.field_value LIKE '%%" . get_request_var('filter') . "%%'
			OR host_snmp_cache.oid LIKE '%%" . get_request_var('filter') . "%%')";
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM (host_snmp_cache, snmp_query,host)
		WHERE host_snmp_cache.host_id = host.id
		AND host_snmp_cache.snmp_query_id = snmp_query.id
		$sql_where");

	$snmp_cache_sql = "SELECT
		host_snmp_cache.*,
		host.description,
		snmp_query.name
		FROM (host_snmp_cache, snmp_query,host)
		WHERE host_snmp_cache.host_id = host.id
		AND host_snmp_cache.snmp_query_id = snmp_query.id
		$sql_where
		LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	$nav = html_nav_bar('utilities.php?action=view_snmp_cache&host_id=' . get_request_var('host_id') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 6, 'Entries', 'page', 'main');

	print $nav;

	html_header(array('Device', 'SNMP Query', 'Index', 'Field Name', 'Field Value', 'OID'));

	$i = 0;
	if (sizeof($snmp_cache)) {
	foreach ($snmp_cache as $item) {
		form_alternate_row();
		?>
		<td>
			<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['description']))) : htmlspecialchars($item['description']));?>
		</td>
		<td>
			<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['name']))) : htmlspecialchars($item['name']));?>
		</td>
		<td>
			<?php print $item['snmp_index'];?>
		</td>
		<td>
			<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['field_name']))) : htmlspecialchars($item['field_name']));?>
		</td>
		<td>
			<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['field_value']))) : htmlspecialchars($item['field_value']));?>
		</td>
		<td>
			<?php print (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['oid']))) : htmlspecialchars($item['oid']));?>
		</td>
		</tr>
		<?php
		}

		print $nav;
	}

	html_end_box();
}

function utilities_view_poller_cache() {
	global $poller_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
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
			'default' => 'data_template_data.name_cache', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'poller_action' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_poller');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$refresh['seconds'] = '300';
	$refresh['page'] = 'utilities.php?action=view_poller_cache&header=false';

	?>
	<script type="text/javascript">
    var refreshIsLogout=false;
    var refreshPage='<?php print $refresh['page'];?>';
    var refreshMSeconds=<?php print $refresh['seconds']*1000;?>;

	function applyFilter() {
		strURL  = '?poller_action=' + $('#poller_action').val();
		strURL += '&action=view_poller_cache';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = '?action=view_poller_cache&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_pollercache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('Poller Cache Items', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_pollercache' action='utilities.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						Device
					</td>
					<td>
						<select id='host_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var('host_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc('SELECT id, description, hostname FROM host ORDER BY description');

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host['id'] . "'"; if (get_request_var('host_id') == $host['id']) { print ' selected'; } print '>' . $host['description'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Action
					</td>
					<td>
						<select id='poller_action' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('poller_action') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var('poller_action') == '0') {?> selected<?php }?>>SNMP</option>
							<option value='1'<?php if (get_request_var('poller_action') == '1') {?> selected<?php }?>>Script</option>
							<option value='2'<?php if (get_request_var('poller_action') == '2') {?> selected<?php }?>>Script Server</option>
						</select>
					</td>
					<td>
						Entries
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?>
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
			<input type='hidden' name='action' value='view_poller_cache'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = 'WHERE poller_item.local_data_id = data_template_data.local_data_id';

	if (get_request_var('poller_action') != '-1') {
		$sql_where .= " AND poller_item.action='" . get_request_var('poller_action') . "'";
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_id') == '0') {
		$sql_where .= ' AND poller_item.host_id = 0';
	}elseif (!isempty_request_var('host_id')) {
		$sql_where .= ' AND poller_item.host_id = ' . get_request_var('host_id');
	}

	if (strlen(get_request_var('filter'))) {
		$sql_where .= " AND (data_template_data.name_cache LIKE '%%" . get_request_var('filter') . "%%'
			OR host.description LIKE '%%" . get_request_var('filter') . "%%'
			OR poller_item.arg1 LIKE '%%" . get_request_var('filter') . "%%'
			OR poller_item.hostname LIKE '%%" . get_request_var('filter') . "%%'
			OR poller_item.rrd_path  LIKE '%%" . get_request_var('filter') . "%%')";
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM data_template_data
		RIGHT JOIN (poller_item
		LEFT JOIN host
		ON poller_item.host_id = host.id)
		ON data_template_data.local_data_id = poller_item.local_data_id
		$sql_where");

	$poller_sql = "SELECT
		poller_item.*,
		data_template_data.name_cache,
		host.description
		FROM data_template_data
		RIGHT JOIN (poller_item
		LEFT JOIN host
		ON poller_item.host_id = host.id)
		ON data_template_data.local_data_id = poller_item.local_data_id
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . ', action ASC
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$poller_cache = db_fetch_assoc($poller_sql);

	$nav = html_nav_bar('utilities.php?action=view_poller_cache&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 3, 'Entries', 'page', 'main');

	print $nav;

	$display_text = array(
		'data_template_data.name_cache' => array('Data Source Name', 'ASC'),
		'nosort' => array('Details', 'ASC'));

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'utilities.php?action=view_poller_cache');

	$i = 0;
	if (sizeof($poller_cache) > 0) {
	foreach ($poller_cache as $item) {
		if ($i % 2 == 0) {
			$class = 'odd';
		}else{
			$class = 'even';
		}
		print "<tr class='$class'>\n";
			?>
			<td style='width:375px;'>
				<a class="linkEditMain" href="<?php print htmlspecialchars('data_sources.php?action=ds_edit&id=' . $item['local_data_id']);?>"><?php print (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['name_cache'])):htmlspecialchars($item['name_cache']));?></a>
			</td>

			<td>
			<?php
			if ($item['action'] == 0) {
				if ($item['snmp_version'] != 3) {
					$details =
						'SNMP Version: ' . $item['snmp_version'] . ', ' .
						'Community: ' . $item['snmp_community'] . ', ' .
						'OID: ' . (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['arg1']))) : htmlspecialchars($item['arg1']));
				}else{
					$details =
						'SNMP Version: ' . $item['snmp_version'] . ', ' .
						'User: ' . $item['snmp_username'] . ', OID: ' . $item['arg1'];
				}
			}elseif ($item['action'] == 1) {
					$details = 'Script: ' . (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['arg1']))) : htmlspecialchars($item['arg1']));
			}else{
					$details = 'Script Server: ' . (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['arg1']))) : htmlspecialchars($item['arg1']));
			}

			print $details;
			?>
			</td>
		</tr>
		<?php
		print "<tr class='$class'>\n";
		?>
			<td>
			</td>
			<td>
				RRD: <?php print $item['rrd_path'];?>
			</td>
		</tr>
		<?php
		$i++;
	}
	}

	print $nav;

	html_end_box();
}

function utilities() {
	html_start_box('Cacti System Utilities', '100%', '', '3', 'center', '');

	?>
	<colgroup span='3'>
		<col class='nowrap' style='vertical-align:top;width:20%;'></col>
		<col style='vertical-align:top;width:80%;'></col>
	</colgroup>

	<?php html_header(array('Technical Support'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_tech');?>'>Technical Support</a>
		</td>
		<td class='textArea'>
			Cacti technical support page.  Used by developers and technical support persons to assist with issues in Cacti.  Includes checks for common configuration issues.
		</td>
	</tr>

	<?php html_header(array('Log Administration'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_logfile');?>'>View Cacti Log File</a>
		</td>
		<td class='textArea'>
			The Cacti Log File stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_user_log');?>'>View User Log</a>
		</td>
		<td class='textArea'>
			Allows Administrators to browse the user log.  Administrators can filter and export the log as well.
		</td>
	</tr>

	<?php html_header(array('Poller Cache Administration'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_poller_cache');?>'>View Poller Cache</a>
		</td>
		<td class='textArea'>
			This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the rrd files for graphing or the database for display.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_snmp_cache');?>'>View SNMP Cache</a>
		</td>
		<td class='textArea'>
			The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine the OID to use when gathering information from an SNMP-enabled host.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=clear_poller_cache');?>'>Rebuild Poller Cache</a>
		</td>
		<td class='textArea'>
			The poller cache will be cleared and re-generated if you select this option. Sometimes host/data source data can get out of sync with the cache in which case it makes sense to clear the cache and start over.
		</td>
	</tr>
	<?php html_header(array('Boost Utilities'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_boost_status');?>'>View Boost Status</a>
		</td>
		<td class='textArea'>
			This menu pick allows you to view various boost settings and statistics associated with the current running Boost configuration.
		</td>
	</tr>
	<?php html_header(array('RRD Utilities'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('rrdcleaner.php');?>'>RRDfile Cleaner</a>
		</td>
		<td class='textArea'>
			When you delete Data Sources from Cacti, the corresponding RRDfiles are not removed automatically.  Use this utility to facilitate the removal of these old files.
		</td>
	</tr>
	<?php html_header(array('SNMPAgent Utilities'), 2); form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_snmpagent_cache');?>'>View SNMPAgent Cache</a>
		</td>
		<td class='textArea'>
			This shows all objects being handled by the SNMPAgent.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=rebuild_snmpagent_cache');?>'>Rebuild SNMPAgent Cache</a>
		</td>
		<td class='textArea'>
			The snmp cache will be cleared and re-generated if you select this option. Note that it takes another poller run to restore the SNMP cache completely.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('utilities.php?action=view_snmpagent_events');?>'>View SNMPAgent Notification Log</a>
		</td>
		<td class='textArea'>
			This menu pick allows you to view the latest events SNMPAgent has handled in relation to the registered notification receivers.
		</td>
	</tr>
	<?php form_alternate_row(); ?>
		<td class='textArea'>
			<a class='hyperLink' href='<?php print htmlspecialchars('managers.php');?>'>SNMP Notification Receivers</a>
		</td>
		<td class='textArea'>
			Allows Administrators to maintain SNMP notification receivers.
		</td>
	</tr>
	<?php

	api_plugin_hook('utilities_list');

	html_end_box();
}

function boost_display_run_status() {
	global $refresh, $config, $refresh_interval, $boost_utilities_interval, $boost_refresh_interval, $boost_max_runtime;

	/* ================= input validation ================= */
	get_filter_request_var('refresh');
	/* ==================================================== */

	load_current_session_value('refresh', 'sess_boost_utilities_refresh', '30');

	$last_run_time   = read_config_option('boost_last_run_time', TRUE);
	$next_run_time   = read_config_option('boost_next_run_time', TRUE);

	$rrd_updates     = read_config_option('boost_rrd_update_enable', TRUE);
	$boost_cache     = read_config_option('boost_png_cache_enable', TRUE);

	$max_records     = read_config_option('boost_rrd_update_max_records', TRUE);
	$max_runtime     = read_config_option('boost_rrd_update_max_runtime', TRUE);
	$update_interval = read_config_option('boost_rrd_update_interval', TRUE);
	$peak_memory     = read_config_option('boost_peak_memory', TRUE);
	$detail_stats    = read_config_option('stats_detail_boost', TRUE);

	$refresh['page'] = 'utilities.php?action=view_boost_status&header=false';
	$refresh['seconds'] = get_request_var('refresh');

	html_start_box('Boost Status', '100%', '', '3', 'center', '');

	?>
	<script type="text/javascript">
    var refreshIsLogout=false;
    var refreshPage='<?php print $refresh['page'];?>';
    var refreshMSeconds=<?php print $refresh['seconds']*1000;?>;

	function applyFilter() {
		strURL = '?action=view_boost_status&header=false&refresh=' + $('#refresh').val();
		loadPageNoHeader(strURL);
	}
	</script>
	<tr class='even'>
		<form id='form_boost_utilities_stats' method='post'>
		<td>
			<table>
				<tr>
					<td class='nowrap'>
						Refresh Interval
					</td>
					<td>
						<select id='refresh' name='refresh' onChange='applyFilter()'>
						<?php
						foreach ($boost_utilities_interval as $key => $interval) {
							print '<option value="' . $key . '"'; if (get_request_var('refresh') == $key) { print ' selected'; } print '>' . $interval . '</option>';
						}
						?>
					</td>
					<td>
						<input type='button' value='Refresh' onClick='applyFilter()'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_end_box(TRUE);
	html_start_box('', '100%', '', '3', 'center', '');

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
						AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");
	$pending_records = 0;
	$arch_records = 0;
	$data_length = 0;
	$engine = '';
	$max_data_length = 0;
	foreach($boost_table_status as $table) {
		if ($table['TABLE_NAME'] == 'poller_output_boost') {
			$pending_records += $table['TABLE_ROWS'];
		} else {
			$arch_records += $table['TABLE_ROWS'];
		}
		$data_length += $table['DATA_LENGTH'];
		$data_length -= $table['DATA_FREE'];
		$engine = $table['ENGINE'];
		$max_data_length = $table['MAX_DATA_LENGTH'];
	}
	$total_records = $pending_records + $arch_records;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	$total_data_sources = db_fetch_cell('SELECT COUNT(*) FROM poller_item');

	$boost_status = read_config_option('boost_poller_status', TRUE);
	if (strlen($boost_status)) {
		$boost_status_array = explode(':', $boost_status);

		$boost_status_date = $boost_status_array[1];

		if (substr_count($boost_status_array[0], 'complete')) $boost_status_text = 'Idle';
		elseif (substr_count($boost_status_array[0], 'running'))  $boost_status_text = 'Running';
		elseif (substr_count($boost_status_array[0], 'overrun'))    $boost_status_text = 'Overrun Warning';
		elseif (substr_count($boost_status_array[0], 'timeout'))  $boost_status_text = 'Timed Out';
		else   $boost_status_text = 'Other';
	}else{
		$boost_status_text = 'Never Run';
		$boost_status_date = '';
	}

	$stats_boost = read_config_option('stats_boost', TRUE);
	if (strlen($stats_boost)) {
		$stats_boost_array = explode(' ', $stats_boost);

		$stats_duration = explode(':', $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds = explode(':', $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	}else{
		$boost_last_run_duration = '';
		$boost_rrds_updated = '';
	}


	/* get cache directory size/contents */
	$cache_directory = read_config_option('boost_png_cache_directory', TRUE);
	$directory_contents = array();

	if (is_dir($cache_directory)) {
		if ($handle = @opendir($cache_directory)) {
			/* This is the correct way to loop over the directory. */
			while (FALSE !== ($file = readdir($handle))) {
				$directory_contents[] = $file;
			}

			closedir($handle);

			/* get size of directory */
			$directory_size = 0;
			$cache_files = 0;
			if (sizeof($directory_contents)) {
				/* goto the cache directory */
				chdir($cache_directory);

				/* check and fry as applicable */
				foreach($directory_contents as $file) {
					/* only remove jpeg's and png's */
					if ((substr_count(strtolower($file), '.png')) ||
						(substr_count(strtolower($file), '.jpg'))) {
						$cache_files++;
						$directory_size += filesize($file);
					}
				}
			}

			$directory_size = boost_file_size_display($directory_size);
			$cache_files = $cache_files . ' Files';
		}else{
			$directory_size = '<strong>WARNING:</strong> Can not open directory';
			$cache_files = '<strong>WARNING:</strong> Unknown';
		}
	}else{
		$directory_size = '<strong>WARNING:</strong> Directory Does NOT Exist!!';
		$cache_files = '<strong>WARNING:</strong> N/A';
	}

	$i = 0;

	/* boost status display */
	html_header(array('Current Boost Status'), 2);

	form_alternate_row();
	print '<td><strong>Boost On Demand Updating:</strong></td><td><strong>' . ($rrd_updates == '' ? 'Disabled' : $boost_status_text) . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Total Data Sources:</strong></td><td>' . $total_data_sources . '</td>';

	if ($total_records > 0) {
		form_alternate_row();
		print '<td><strong>Pending Boost Records:</strong></td><td>' . $pending_records . '</td>';

		form_alternate_row();
		print '<td><strong>Archived Boost Records:</strong></td><td>' . $arch_records . '</td>';

		form_alternate_row();
		print '<td><strong>Total Boost Records:</strong></td><td>' . $total_records . '</td>';
	}

	/* boost status display */
	html_header(array('Boost Storage Statistics'), 2);

	/* describe the table format */
	form_alternate_row();
	print '<td><strong>Database Engine:</strong></td><td>' . $engine . '</td>';

	/* tell the user how big the table is */
	form_alternate_row();
	print '<td><strong>Current Boost Tables Size:</strong></td><td>' . boost_file_size_display($data_length, 2) . '</td>';

	/* tell the user about the average size/record */
	form_alternate_row();
	print '<td><strong>Avg Bytes/Record:</strong></td><td>' . boost_file_size_display($avg_row_length) . '</td>';

	/* tell the user about the average size/record */
	$output_length = read_config_option('boost_max_output_length');
	if (strlen($output_length)) {
		$parts = explode(':', $output_length);
		if ((time()-1200) > $parts[0]) {
			$refresh = TRUE;
		}else{
			$refresh = FALSE;
		}
	}else{
		$refresh = TRUE;
	}

	if ($refresh) {
		if (strcmp($engine, 'MEMORY') == 0) {
			$max_length = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		}else{
			$max_length = '0';
		}
		db_execute("REPLACE INTO settings (name, value) VALUES ('boost_max_output_length', '" . time() . ':' . $max_length . "')");
	}else{
		$max_length = $parts[1];
	}

	if ($max_length != 0) {
		form_alternate_row();
		print '<td><strong>Max Record Length:</strong></td><td>' . $max_length . ' Bytes</td>';
	}

	/* tell the user about the "Maximum Size" this table can be */
	form_alternate_row();
	if (strcmp($engine, 'MEMORY')) {
		$max_table_allowed = 'Unlimited';
		$max_table_records = 'Unlimited';
	}else{
		$max_table_allowed = boost_file_size_display($max_data_length, 2);
		$max_table_records = ($avg_row_length ? round($max_data_length/$avg_row_length, 0) : 0);
	}
	print '<td><strong>Max Allowed Boost Table Size:</strong></td><td>' . $max_table_allowed . '</td>';

	/* tell the user about the estimated records that "could" be held in memory */
	form_alternate_row();
	print '<td><strong>Estimated Maximum Records:</strong></td><td>' . $max_table_records  . ' Records</td>';

	/* boost last runtime display */
	html_header(array('Runtime Statistics'), 2);

	form_alternate_row();
	print '<td class="utilityPick"><strong>Last Start Time:</strong></td><td>' . $last_run_time . '</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Last Run Duration:</strong></td><td>';
	print (($boost_last_run_duration > 60) ? (int)($boost_last_run_duration/60) . ' minutes ' : '' ) . $boost_last_run_duration%60 . ' seconds';
	if ($rrd_updates != ''){ print ' (' . round(100*$boost_last_run_duration/$update_interval/60) . '% of update frequency)';}
	print '</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>RRD Updates:</strong></td><td>' . $boost_rrds_updated . '</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Peak Poller Memory:</strong></td><td>' . ((read_config_option('boost_peak_memory') != '') ? (round(read_config_option('boost_peak_memory')/1024/1024,2)) . ' MBytes' : 'N/A') . '</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Detailed Runtime Timers:</strong></td><td>' . (($detail_stats != '') ? $detail_stats:'N/A') . '</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Max Poller Memory Allowed:</strong></td><td>' . ((read_config_option('boost_poller_mem_limit') != '') ? (read_config_option('boost_poller_mem_limit')) . ' MBytes' : 'N/A') . '</td>';

	/* boost runtime display */
	html_header(array('Run Time Configuration'), 2);

	form_alternate_row();
	print '<td class="utilityPick"><strong>Update Frequency:</strong></td><td><strong>' . ($rrd_updates == '' ? 'N/A' : $boost_refresh_interval[$update_interval]) . '</strong></td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Next Start Time:</strong></td><td>' . $next_run_time . '</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Maximum Records:</strong></td><td>' . $max_records . ' Records</td>';

	form_alternate_row();
	print '<td class="utilityPick"><strong>Maximum Allowed Runtime:</strong></td><td>' . $boost_max_runtime[$max_runtime] . '</td>';

	/* boost caching */
	html_header(array('Image Caching'), 2);

	form_alternate_row();
	print '<td><strong>Image Caching Status:</strong></td><td><strong>' . ($boost_cache == '' ? 'Disabled' : 'Enabled') . '</strong></td>';

	form_alternate_row();
	print '<td><strong>Cache Directory:</strong></td><td>' . $cache_directory . '</td>';

	form_alternate_row();
	print '<td><strong>Cached Files:</strong></td><td>' . $cache_files . '</td>';

	form_alternate_row();
	print '<td><strong>Cached Files Size:</strong></td><td>' . $directory_size . '</td>';

	html_end_box(TRUE);
}

/**
 *
 *
 * snmpagent_utilities_run_cache()
 *
 * @param mixed
 * @return
 */
function snmpagent_utilities_run_cache() {
	global $item_rows;

	get_filter_request_var('mib', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

	$mibs = db_fetch_assoc('SELECT DISTINCT mib FROM snmpagent_cache');
	$registered_mibs = array();
	if($mibs && $mibs >0) {
		foreach($mibs as $mib) { $registered_mibs[] = $mib['mib']; }
	}

	/* ================= input validation ================= */
	if(!in_array(get_request_var('mib'), $registered_mibs) && get_request_var('mib') != '-1' && get_request_var('mib') != '') {
		die_html_input_error();
	}
	/* ==================================================== */

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
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
		'mib' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => '-1', 
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_snmpac');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'utilities.php?action=view_snmpagent_cache';
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_cache&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_cache').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('SNMPAgent Cache', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_cache' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
						</td>
						<td>
							MIB
						</td>
						<td>
							<select id='mib' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('mib') == '-1') {?> selected<?php }?>>Any</option>
								<?php
								if (sizeof($mibs) > 0) {
									foreach ($mibs as $mib) {
										print "<option value='" . $mib['mib'] . "'"; if (get_request_var('mib') == $mib['mib']) { print ' selected'; } print '>' . $mib['mib'] . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							OIDs
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
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
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* filter by host */
	if (get_request_var('mib') == '-1') {
		/* Show all items */
	}elseif (!isempty_request_var('mib')) {
		$sql_where .= " AND snmpagent_cache.mib='" . get_request_var('mib') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (`oid` LIKE '%%" . get_request_var('filter') . "%%'
			OR `name` LIKE '%%" . get_request_var('filter') . "%%'
			OR `mib` LIKE '%%" . get_request_var('filter') . "%%'
			OR `max-access` LIKE '%%" . get_request_var('filter') . "%%')";
	}
	$sql_where .= ' ORDER by `oid`';

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_cache WHERE 1 $sql_where");

	$snmp_cache_sql = "SELECT * FROM snmpagent_cache WHERE 1 $sql_where LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	/* generate page list */
	$nav = html_nav_bar('utilities.php?action=view_snmpagent_cache&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, '', 'page', 'main');

	print $nav;

	html_header(array( 'OID', 'Name', 'MIB', 'Kind', 'Max-Access', 'Value'));

	if (sizeof($snmp_cache) > 0) {
		foreach ($snmp_cache as $item) {

			$oid = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['oid']))) : htmlspecialchars($item['oid']));
			$name = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['name']))): htmlspecialchars($item['name']));
			$mib = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['mib']))): htmlspecialchars($item['mib']));

			$max_access = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['max-access']))) : htmlspecialchars($item['max-access']));

			form_alternate_row('line' . $item['oid'], false);
			form_selectable_cell( $oid, $item['oid']);
			if($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . $item['description'] . '</div>" class="tooltip">' . $name . '</a></td>';
			}else {
				print "<td>$name</td>";
			}
			form_selectable_cell( $mib, $item['oid']);
			form_selectable_cell( $item['kind'], $item['oid']);
			form_selectable_cell( $max_access, $item['oid']);
			form_selectable_cell( (in_array($item['kind'], array('Scalar', 'Column Data')) ? $item['value'] : 'n/a'), $item['oid']);
			form_end_row();
		}
	}

	print $nav;

	html_end_box();

	?>
	<script language="javascript" type="text/javascript" >
		$('.tooltip').tooltip({
			track: true,
			show: 250,
			hide: 250,
			position: { collision: "flipfit" },
			content: function() { return $(this).attr('title'); }
		});
	</script>
	<?php
}

function snmpagent_utilities_run_eventlog(){
	global $item_rows;

	$severity_levels = array(
		SNMPAGENT_EVENT_SEVERITY_LOW => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	);

	$severity_colors = array(
		SNMPAGENT_EVENT_SEVERITY_LOW => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	$receivers = db_fetch_assoc('SELECT DISTINCT manager_id, hostname 
		FROM snmpagent_notifications_log 
		INNER JOIN snmpagent_managers 
		ON snmpagent_managers.id = snmpagent_notifications_log.manager_id');

	/* ================= input validation ================= */
	get_filter_request_var('receiver');

	if(!in_array(get_request_var('severity'), array_keys($severity_levels)) && get_request_var('severity') != '-1' && get_request_var('severity') != '') {
		die_html_input_error();
	}
	/* ==================================================== */

	if (isset_request_var('purge')) {
		db_execute('TRUNCATE table snmpagent_notifications_log');

		/* reset filters */
		set_request_var('clear', true);
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
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
		'severity' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			),
		'receiver' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_snmpl');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'utilities.php?action=view_snmpagent_events';
		strURL += '&severity=' + $('#severity').val();
		strURL += '&receiver=' + $('#receiver').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function purgeFilter() {
		strURL = 'utilities.php?action=view_snmpagent_events&purge=1&header=false';
		loadPageNoHeader(strURL);
	}
	$(function(data) {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeFilter();
		});

		$('#form_snmpagent_notifications').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>

	<?php
	html_start_box('SNMPAgent Notification Log', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_notifications' action='utilities.php'>
				<table class='filterTable'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
						</td>
						<td>
							Severity
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>>Any</option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var('severity') == $level) { print ' selected'; } print '>' . $name . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							Receiver
						</td>
						<td>
							<select id='receiver' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('receiver') == '-1') {?> selected<?php }?>>Any</option>
								<?php
								foreach ($receivers as $receiver) {
									print "<option value='" . $receiver['manager_id'] . "'"; if (get_request_var('receiver') == $receiver['manager_id']) { print ' selected'; } print '>' . $receiver['hostname'] . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							Entries
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
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
							<input type='button' id='clear' value='Clear' title='Clear Filters'>
							<input type='button' id='purge' value='Purge' title='Purge Notification Log'>
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = ' 1';

	/* filter by severity */
	if(get_request_var('receiver') != '-1') {
		$sql_where .= " AND snmpagent_notifications_log.manager_id='" . get_request_var('receiver') . "'";
	}

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
	/* Show all items */
	}elseif (!isempty_request_var('severity')) {
		$sql_where .= " AND snmpagent_notifications_log.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (`varbinds` LIKE '%%" . get_request_var('filter') . "%%')";
	}
	$sql_where .= ' ORDER by `time` DESC';
	$sql_query = "SELECT snmpagent_notifications_log.*, snmpagent_managers.hostname, snmpagent_cache.description FROM snmpagent_notifications_log
		INNER JOIN snmpagent_managers ON snmpagent_managers.id = snmpagent_notifications_log.manager_id
		LEFT JOIN snmpagent_cache ON snmpagent_cache.name = snmpagent_notifications_log.notification
		WHERE $sql_where LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	form_start('managers.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_notifications_log WHERE $sql_where");
	$logs = db_fetch_assoc($sql_query);

	/* generate page list */
	$nav = html_nav_bar('utilities.php?action=view_snmpagent_events&severity='. get_request_var('severity').'&receiver='. get_request_var('receiver').'&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, '', 'page', 'main');

	print $nav;

	html_header(array(' ', 'Time', 'Receiver', 'Notification', 'Varbinds' ));

	if (sizeof($logs) > 0) {
		foreach ($logs as $item) {
			$varbinds = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['varbinds']))): htmlspecialchars($item['varbinds']));
			form_alternate_row('line' . $item['id'], false);
			print "<td title='Severity Level: " . $severity_levels[ $item['severity'] ] . "' style='width:10px;background-color: " . $severity_colors[ $item['severity'] ] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td class='nowrap'>" . date( 'Y/m/d H:i:s', $item['time']) . '</td>';
			print '<td>' . $item['hostname'] . '</td>';
			if($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . htmlspecialchars($item['notification'], ENT_QUOTES) . '</div><div class=\'content preformatted\'>' . htmlspecialchars($item['description'], ENT_QUOTES) . '</div>" class="tooltip">' . htmlspecialchars($item['notification']) . '</a></td>';
			}else {
				print "<td>" . htmlspecialchars($item['notification']) . "</td>";
			}
			print "<td>$varbinds</td>";
			form_end_row();
		}
		print $nav;
	}else{
		print '<tr><td><em>No SNMP Notification Log Entries</em></td></tr>';
	}

	html_end_box();
	?>

	<script language='javascript' type='text/javascript' >
	$('.tooltip').tooltip({
		track: true,
		position: { collision: 'flipfit' },
		content: function() { return $(this).attr('title'); }
	});
	</script>
	<?php
}
