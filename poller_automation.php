<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2015 The Cacti Group                                 |
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

ini_set('output_buffering', 'Off');

/** sig_handler - provides a generic means to catch exceptions to the Cacti log.
 * @arg $signo  - (int) the signal that was thrown by the interface.
 * @return      - null */
function sig_handler($signo) {
	global $network_id, $thread, $master, $poller_id;

    switch ($signo) {
        case SIGTERM:
        case SIGINT:
			if ($thread > 0) {
				clearTask($network_id, getmypid());
				exit;
			}elseif($thread == 0 && !$master) {
				$pids = array_rekey(db_fetch_assoc_prepared("SELECT pid 
					FROM automation_processes 
					WHERE network_id = ?
					AND task!='tmaster'", array($network_id)), 'pid', 'pid');

				if (sizeof($pids)) {
				foreach($pids as $pid) {
					posix_kill($pid, SIGTERM);
				}
				}

				clearTask($network_id, getmypid());
			}else{
				$pids = array_rekey(db_fetch_assoc_prepared("SELECT pid 
					FROM automation_processes 
					WHERE poller_id = ?
					AND task='tmaster'", array($poller_id)), 'pid', 'pid');

				if (sizeof($pids)) {
				foreach($pids as $pid) {
					posix_kill($pid);
				}
				}

				clearTask($network_id, getmypid());
			}

            exit;

            break;
        default:
            /* ignore all other signals */
    }
}

/* let PHP run just as long as it has to */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* take time and log performance data */
list($micro,$seconds) = explode(" ", microtime());
$start = $seconds + $micro;

// Unix Timestamp for Database
$startTime = time();

/* let PHP run just as long as it has to */
ini_set("max_execution_time", "0");

$dir = dirname(__FILE__);
chdir($dir);

include("./include/global.php");
include_once($config["base_path"] . '/lib/snmp.php');
include_once($config["base_path"] . '/lib/ping.php');
include_once($config["base_path"] . '/lib/poller.php');
include_once($config["base_path"] . '/lib/utility.php');
include_once($config["base_path"] . '/lib/api_data_source.php');
include_once($config["base_path"] . '/lib/api_graph.php');
include_once($config["base_path"] . '/lib/snmp.php');
include_once($config["base_path"] . '/lib/data_query.php');
include_once($config["base_path"] . '/lib/api_device.php');

include_once($config["base_path"] . '/lib/sort.php');
include_once($config["base_path"] . '/lib/html_form_template.php');
include_once($config["base_path"] . '/lib/template.php');

include_once($config["base_path"] . '/lib/api_tree.php');
include_once($config["base_path"] . '/lib/api_automation.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug      = false;
$force      = false;
$network_id = 0;
$poller_id  = 0;
$thread     = 0;
$master     = false;

global $debug, $poller_id, $network_id, $thread, $master;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
	case "-d":
	case "--debug":
		$debug = true;
		break;
	case "-M":
	case "--master":
		$master = true;
		break;
	case "--poller":
		$poller_id = $value;
		break;
	case "-f":
	case "--force":
		$force = true;
		break;
	case "--network":
		$network_id = $value;
		break;
	case "--thread":
		$thread = $value;
		break;
	case "-h":
	case "-v":
	case "--version":
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

/* install signal handlers for UNIX only */
if (function_exists("pcntl_signal")) {
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
}

// Let's insure that we were called correctly
if (!$master && !$network_id) {
	print "FATAL: You must specify -M to Start the Master Control Process, or the Network ID using --network\n";
	exit;
}

// Simple check for a disabled network
if (!$master && $thread == 0) {
	$status = db_fetch_cell("SELECT enabled FROM automation_networks WHERE id=$network_id AND poller_id=$poller_id");

	if ($status != 'on' && !$force) {
		print "ERROR: This Subnet Range is disabled.  You must use the 'force' option to force it's execution.\n";
		exit;
	}
}

if ($master) {
	$networks = db_fetch_assoc_prepared('SELECT * FROM automation_networks WHERE poller_id = ?', array($poller_id));
	$launched = 0;
	if (sizeof($networks)) {
	foreach($networks as $network) {
		if (api_automation_is_time_to_start($network['id'])) {
			automation_debug("Launching Network Master for '" . $network['name'] . "'\n");
			exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . "/poller_automation.php --poller=" . $poller_id . " --network=" . $network['id'] . ($force ? ' --force':'') . ($debug ? ' --debug':''));
			$launched++;
		}else{
			automation_debug("Not time to Run Discovery for '" . $network['name'] . "'\n");
		}
	}
	}

	exit;
}

// Check for Network Master
if (!$master && $thread == 0) {
	automation_debug("Thread master about to launch collector threads\n");

	// Remove any stale entries
	$pids = array_rekey(db_fetch_assoc("SELECT pid 
		FROM automation_processes 
		WHERE network_id=$network_id"), 'pid', 'pid');

	automation_debug("Killing any prior running threads\n");
	if (sizeof($pids)) {
		foreach($pids as $pid) {
			if (isProcessRunning($pid)) {
				killProcess($pid);
				print "NOTE: Killing Process $pid\n";
			}else{
				print "NOTE: Process $pid claims to be running but not found\n";
			}
		}
	}

	automation_debug("Removing any orphan entries\n");
	db_execute("DELETE FROM automation_ips WHERE network_id=$network_id");
	db_execute("DELETE FROM automation_processes WHERE network_id=$network_id");

	registerTask($network_id, getmypid(), $poller_id, 'tmaster');

	cacti_log("Network Discover is now running for Subnet Range '$network_id'", true, 'AUTOMATION');

	automation_primeIPAddressTable($network_id);

	$threads = db_fetch_cell("SELECT threads FROM automation_networks WHERE id=$network_id");
	automation_debug("Automation will use $threads Threads\n");

	$curthread = 1;
	while($curthread <= $threads) {
		automation_debug("Launching Thread $curthread\n");
		$old_debug = $debug;
		$debug = false;
		exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . "/poller_automation.php --poller=" . $poller_id . " --thread=$curthread --network=$network_id" . ($force ? ' --force':'') . ($debug ? ' --debug':''));
		$debug = $old_debug;
		$curthread++;
	}

	sleep(5);
	automation_debug("Checking for Running Threads\n");

	while (true) {
		$running = db_fetch_cell("SELECT count(*) FROM automation_processes WHERE network_id=$network_id AND task!='tmaster' AND status='running'");
		automation_debug("Found $running Threads\n");

		if ($running == 0) {
			/* determine data queries to rerun */
			$graph_search = db_fetch_cell("SELECT rerun_data_queries FROM automation_networks WHERE id=$network_id");
			if ($graph_search == 'on') {
				automation_debug("Rerunning Data Queries on Existing Hosts\n");
				$devices = db_fetch_assoc("SELECT id, description FROM host 
					INNER JOIN automation_ips
					ON host.hostname=automation_ips.hostname
					WHERE host.disabled!='on'
					AND automation_ips.network_id=$network_id");

				foreach ($devices as $device) {
//					automation_debug('Device : ' . $device['description'] . "\n");
//					automation_create_graphs($device['id']);
//					automation_remove_graphs($device['id']);
				}
			}

			db_execute_prepared('DELETE FROM automation_ips WHERE network_id = ?', array($network_id));

			$totals = db_fetch_row_prepared('SELECT SUM(up_hosts) AS up, SUM(snmp_hosts) AS snmp FROM automation_processes WHERE network_id=?', array($network_id));

			/* take time and log performance data */
			list($micro,$seconds) = explode(" ", microtime());
			$end = $seconds + $micro;

			db_execute_prepared('UPDATE automation_networks 
				SET up_hosts = ?, 
					snmp_hosts = ?, 
					last_started = ?, 
					last_runtime = ? WHERE id = ?', 
				array($totals['up'], $totals['snmp'], date('Y-m-d H:i:s', $startTime), ($end - $start), $network_id));

			clearAllTasks($network_id);

			exit;
		}

		sleep(5);
	}
}else{
	registerTask($network_id, getmypid(), $poller_id);
	discoverDevices($network_id, $thread);
	endTask($network_id, getmypid());
}

exit;

function discoverDevices($network_id, $thread) {
	$network = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', array($network_id));

	$temp = db_fetch_assoc('SELECT automation_templates.*, host_template.name 
		FROM automation_templates
		LEFT JOIN host_template 
		ON (automation_templates.host_template=host_template.id)');

	$dns = trim($network['dns_servers']);

	/* Let's do some stats! */
	$stats = array();
	$stats['scanned'] = 0;
	$stats['ping']    = 0;
	$stats['snmp']    = 0;
	$stats['added']   = 0;
	$count_graph      = 0;

	while(true) {
		// set and ip to be scanned
		db_execute_prepared('UPDATE automation_ips SET pid = ?, thread = ? WHERE network_id = ? AND status=0 AND pid=0 LIMIT 1', array(getmypid(), $thread, $network_id));

		$device = db_fetch_row_prepared('SELECT * FROM automation_ips WHERE pid = ? AND thread = ? AND status=0', array(getmypid(), $thread));

		if (sizeof($device)) {
			$count++;
			if ($dns != '') {
				$dnsname = automation_get_dns_from_ip($device['ip_address'], $dns, 300);
				if ($dnsname != $device['ip_address'] && $dnsname != 'timed_out') {
					db_execute_prepared('UPDATE automation_ips SET hostname = ? WHERE ip_address = ?', array($dnsname, $device['ip_address']));

					$device['hostname']      = $dnsname;
					$device['dnsname']       = $dnsname;
					$device['dnsname_short'] = preg_split('/[\.]+/', strtolower($dnsname), -1, PREG_SPLIT_NO_EMPTY);
				}else{
					$device['hostname'] = ping_netbios_name($device['ip_address']);
					if ($device['hostname'] === false) {
						$device['hostname']      = $device['ip_address'];
						$device['dnsname']       = '';
						$device['dnsname_short'] = '';
					}else{
						db_execute_prepared('UPDATE automation_ips SET hostname = ? WHERE ip_address = ?', array($device['hostname'], $device['ip_address']));
						$device['dnsname']       = $device['hostname'];
						$device['dnsname_short'] = $device['hostname'];
					}
				}
			}else{
				$dnsname = gethostbyaddr($device['ip_address']);
				$device['hostname'] = $dnsname;
				if ($dnsname != $device['ip_address']) {
					db_execute_prepared('UPDATE automation_ips SET hostname = ? WHERE ip_address = ?', array($dnsname, $device['ip_address']));

					$device['dnsname']       = $dnsname;
					$device['dnsname_short'] = preg_split('/[\.]+/', strtolower($dnsname), -1, PREG_SPLIT_NO_EMPTY);
				}else{
					$device['hostname'] = ping_netbios_name($device['ip_address']);
					if ($device['hostname'] === false) {
						$device['hostname']      = $device['ip_address'];
						$device['dnsname']       = '';
						$device['dnsname_short'] = '';
					}else{
						db_execute_prepared('UPDATE automation_ips SET hostname = ? WHERE ip_address = ?', array($device['hostname'], $device['ip_address']));
						$device['dnsname']       = $device['hostname'];
						$device['dnsname_short'] = $device['hostname'];
					}
				}
			}

			$exists = db_fetch_row_prepared('SELECT snmp_version, status FROM host WHERE hostname IN(?,?)', array($device['ip_address'], $device['hostname']));

			if (!sizeof($exists)) {
				if (substr($device['ip_address'], -3) < 255) {
					automation_debug('Scanning Host: ' . $device['ip_address']);

					// Set status to running
					markIPRunning($device['ip_address'], $network_id);

					$stats['scanned']++;

					$device['snmp_status']          = 0;
					$device['ping_status']          = 0;
					$device['snmp_id']              = $network['snmp_id'];
					$device['snmp_version']         = '';
					$device['snmp_readstring']      = '';
					$device['snmp_username']        = '';
					$device['snmp_password']        = '';
					$device['snmp_auth_protocol']   = '';
					$device['snmp_auth_passphrase'] = '';
					$device['snmp_auth_protocol']   = '';
					$device['snmp_context']         = '';
					$device['snmp_port']            = '';
					$device['snmp_timeout']         = '';
					$device['snmp_sysDescr']        = '';
					$device['snmp_sysObjectID']     = '';
					$device['snmp_sysUptime']       = 0;
					$device['snmp_sysName']         = '';
					$device['snmp_sysName_short']   = '';
					$device['snmp_sysLocation']     = '';
					$device['snmp_sysContact']      = '';
					$device['os']                   = '';

					/* create new ping socket for host pinging */
					$ping = new Net_Ping;
					$ping->host["hostname"] = $device['ip_address'];
					$ping->retries = $network['ping_retries'];
					$ping->port    = $network['ping_port'];;
	
					/* perform the appropriate ping check of the host */
					$result = $ping->ping(AVAIL_PING, $network['ping_method'], $network['ping_timeout'], 1);
	
					if (!$result) {
						automation_debug(" - Does not respond to ping!");
						updateDownDevice($network_id, $device['ip_address']);
					}else{
						automation_debug(" - Responded to ping!");
						$stats['ping']++;
						addUpDevice($network_id, getmypid());
					}
	
					if ($result && automation_valid_snmp_device($device)) {
						$snmp_sysName = preg_split('/[\s.]+/', $device['snmp_sysName'], -1, PREG_SPLIT_NO_EMPTY);
						if(!isset($snmp_sysName[0])) {
							$snmp_sysName[0] = '';
						}
						$snmp_sysName_short = preg_split('/[\.]+/', strtolower($snmp_sysName[0]), -1, PREG_SPLIT_NO_EMPTY);
	
						$exists = db_fetch_row_prepared('SELECT status, snmp_version FROM host WHERE hostname IN(?,?)', array($snmp_sysName_short, $snmp_sysname[0]));

						if (sizeof($exists)) {
							if ($exists['status'] == 3 || $exists['status'] == 2) {
								addUpDevice($network_id, getmypid());

								if ($exists['snmp_version'] > 0) {
									addSNMPDevice($network_id, getmypid());
								}
							}

							automation_debug(' - Host DNS is already in hosts table!');
							automation_debug(' DNS: ' . $device['dnsname'] . ' - ' . $device['dnsname_short'][0] . ' SNMP: ' . $snmp_sysName[0] . ' - ' . $snmp_sysName_short[0]);
							markIPDone($device['ip_address'], $network_id);
						} else {
							$isDuplicateSysName = db_fetch_cell_prepared('SELECT COUNT(*) 
								FROM automation_devices 
								WHERE network_id = ? 
								AND sysName = ?', array($network_id, $snmp_sysName[0]));
	
							if ($isDuplicateSysName) {
								automation_debug(" - Ignoring Address Already Discovered as Another IP!\n");
								markIPDone($device['ip_address'], $network_id);
								continue;
							}
	
							$stats['snmp']++;
							addSNMPDevice($network_id, getmypid());

							$host_id = 0;
							automation_debug(' - Is a valid device! DNS: ' . $device['dnsname'] . ' SNMP: ' . $snmp_sysName[0]);

							$fos = automation_find_os($device['snmp_sysDescr'], $device['snmp_sysObjectID'], $device['snmp_sysName']);

							if ($fos != false) {
								automation_debug("\n     Host Template: " . $fos['name']);
								$device['os']                   = $fos['name'];
								$device['host_template']        = $fos['host_template'];
								$device['availability_method']  = $fox['availability_method'];
								$device['snmp_readstring']      = db_qstr($device['snmp_readstring']);
								$device['snmp_version']         = db_qstr($device['snmp_version']);
								$device['snmp_username']        = db_qstr($device['snmp_username']);
								$device['snmp_password']        = db_qstr($device['snmp_password']);
								$device['snmp_auth_protocol']   = db_qstr($device['snmp_auth_protocol']);
								$device['snmp_priv_passphrase'] = db_qstr($device['snmp_priv_passphrase']);
								$device['snmp_priv_protocol']   = db_qstr($device['snmp_priv_protocol']);
								$device['snmp_context']         = db_qstr($device['snmp_context']);
								$device['snmp_sysName']         = db_qstr($device['snmp_sysName']);
								$device['snmp_sysLocation']     = db_qstr($device['snmp_sysLocation']);
								$device['snmp_sysContact']      = db_qstr($device['snmp_sysContact']);
								$device['snmp_sysDescr']        = db_qstr($device['snmp_sysDescr']);
								$device['snmp_sysUptime']       = db_qstr($device['snmp_sysUptime']);
								$host_id = automation_add_device($device);

								$stats['added']++;
							}

							// if the devices template is not discovered, add to found table
							if (!$host_id) {
								db_execute('REPLACE INTO automation_devices 
									(network_id, hostname, ip, community, snmp_version, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, sysName, sysLocation, sysContact, sysDescr, sysUptime, os, snmp, up, time) VALUES ('
									. $network_id                              . ', '
									. db_qstr($device['dnsname'])              . ', '
									. db_qstr($device['ip_address'])           . ', '
									. db_qstr($device['snmp_readstring'])      . ', '
									. db_qstr($device['snmp_version'])         . ', '
									. db_qstr($device['snmp_username'])        . ', '
									. db_qstr($device['snmp_password'])        . ', '
									. db_qstr($device['snmp_auth_protocol'])   . ', '
									. db_qstr($device['snmp_priv_passphrase']) . ', '
									. db_qstr($device['snmp_priv_protocol'])   . ', '
									. db_qstr($device['snmp_context'])         . ', '
									. db_qstr($device['snmp_sysName'])         . ', '
									. db_qstr($device['snmp_sysLocation'])     . ', '
									. db_qstr($device['snmp_sysContact'])      . ', '
									. db_qstr($device['snmp_sysDescr'])        . ', '
									. db_qstr($device['snmp_sysUptime'])       . ', '
									. db_qstr($device['os'])                   . ', '
									. '1, 1,' . time() . ')');
							}

							markIPDone($device['ip_address'], $network_id);
						}
					}else if ($result) {
						db_execute('REPLACE INTO automation_devices 
							(network_id, hostname, ip, community, snmp_version, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, sysName, sysLocation, sysContact, sysDescr, sysUptime, os, snmp, up, time) VALUES ('
							. $network_id                              . ', '
							. db_qstr($device['dnsname'])              . ', '
							. db_qstr($device['ip_address'])           . ', '
							. db_qstr($device['snmp_readstring'])      . ', '
							. db_qstr($device['snmp_version'])         . ', '
							. db_qstr($device['snmp_username'])        . ', '
							. db_qstr($device['snmp_password'])        . ', '
							. db_qstr($device['snmp_auth_protocol'])   . ', '
							. db_qstr($device['snmp_priv_passphrase']) . ', '
							. db_qstr($device['snmp_priv_protocol'])   . ', '
							. db_qstr($device['snmp_context'])         . ', '
							. db_qstr($device['snmp_sysName'])         . ', '
							. db_qstr($device['snmp_sysLocation'])     . ', '
							. db_qstr($device['snmp_sysContact'])      . ', '
							. db_qstr($device['snmp_sysDescr'])        . ', '
							. db_qstr($device['snmp_sysUptime'])       . ', '
							. '"", 0, 1,' . time() . ')');

						automation_debug(" - Host $dnsname is alive but no SNMP!");

						markIPDone($device['ip_address'], $network_id);
					}else{
						markIPDone($device['ip_address'], $network_id);
					}
				} else {
					automation_debug(" - Ignoring Address (PHP Bug does not allow us to ping .255 as it thinks its a broadcast IP)!");
					markIPDone($device['ip_address'], $network_id);
				}
			} else {
				if ($exists['status'] == 3 || $exists['status'] == 2) {
					addUpDevice($network_id, getmypid());

					if ($exists['snmp_version'] > 0) {
						addSNMPDevice($network_id, getmypid());
					}
				}

				automation_debug(' - Host is already in hosts table!');
				markIPDone($device['ip_address'], $network_id);
			}
	
			automation_debug("\n");
		}else{
			// no more ips to scan
			break;
		}
	}

	cacti_log('Network ' . $network['name'] . " Thread $thread Finished, " . $stats['scanned'] . ' IPs Scanned, ' . $stats['ping'] . ' IPs Responded to Ping, ' . $stats['snmp'] . ' Responded to SNMP, ' . $stats['added'] . ' Device Added, ' . $count_graph .  ' Graphs Added to Cacti', true, 'AUTOMATION');

	return true;
}

/*	display_help - displays the usage of the function */
function display_help () {
    $version = db_fetch_cell('SELECT cacti FROM version');
    print "Network Discovery, Version $version, " . COPYRIGHT_YEARS . "\n\n";
	print "Cacti Network Discovery Scanner based on original works of Autom8 and Discovery\n\n";
	print "usage: poller_automation.php -M [--poller=N ] | --network=network_id [-T=thread_id]\n";
	print "    [-d | --debug] [-f | --force] [-h | --help | -v | --version]\n\n";
	print "Master Process:\n";
	print "    -M | --master - Master poller for all Automation\n";
	print "    --poller=n    - Master Poller ID, Defaults to 0 or WebServer\n\n";
	print "Network Masters and Workers:\n";
	print "    --network=n   - Network ID to discover\n";
	print "    --thread=n    - Thread ID, Defaults to 0 or Network Master\n\n";
	print "General Options:\n";
	print "    -f | --force  - Force the execution of a discovery process\n";
	print "    -d | --debug  - Display verbose output during execution\n";
	print "    -v --version  - Display this help message\n";
	print "    -h --help     - Display this help message\n";
}

function isProcessRunning($pid) {
    return posix_kill($pid, 0);
}

function killProcess($pid) {
	return posix_kill($pid);
}

function registerTask($network_id, $pid, $poller_id, $task = 'collector') {
	db_execute_prepared("REPLACE INTO automation_processes 
		(pid, poller_id, network_id, task, status, heartbeat) 
		VALUES (?, ?, ?, ?, 'running', NOW())", array($pid, $poller_id, $network_id, $task));
}

function endTask($network_id, $pid) {
	db_execute_prepared("UPDATE automation_processes 
		SET status='done', heartbeat=NOW() WHERE pid = ? AND network_id = ?", array($pid, $network_id));
}

function addUpDevice($network_id, $pid) {
	db_execute_prepared('UPDATE automation_processes 
		SET up_hosts=up_hosts+1, heartbeat=NOW() WHERE pid = ? AND network_id = ?', array($pid, $network_id));
}

function addSNMPDevice($network_id, $pid) {
	db_execute_prepared('UPDATE automation_processes 
		SET snmp_hosts=snmp_hosts+1, heartbeat=NOW() WHERE pid = ? AND network_id = ?', array($pid, $network_id));
}

function clearTask($network_id, $pid) {
	db_execute_prepared('DELETE FROM automation_processes WHERE pid = ?  AND network_id = ?', array($pid, $network_id));
}

function clearAllTasks($network_id) {
	db_execute_prepared('DELETE FROM automation_processes WHERE network_id = ?', array($network_id));
}

function markIPRunning($ip_address, $network_id) {
	db_execute_prepared('UPDATE automation_ips SET status=1 WHERE ip_address = ? AND network_id = ?', array($ip_address, $network_id));
}

function markIPDone($ip_address, $network_id) {
	db_execute_prepared('UPDATE automation_ips SET status=2 WHERE ip_address = ? AND network_id = ?', array($ip_address, $network_id));
}

function updateDownDevice($network_id, $ip) {
	$exists = db_fetch_cell_prepared('SELECT COUNT(*) FROM automation_devices WHERE ip = ? AND network_id = ?', array($ip, $network_id));
	if ($exists) {
		db_execute_prepared('UPDATE automation_devices SET status="Down" time = ? WHERE ip = ? AND network_id = ?', array(time(), $ip, $network_id));
	}
}


