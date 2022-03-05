<?php

require_once('globals.inc');
require_once("util.inc");

$input_errors = array();

// Array init and ordering functions, copied from pfSense source code
if (!function_exists('init_config_arr')) {
	function init_config_arr($keys) {
		global $config;
		$c = &$config;
		if (!is_array($keys)) {
			return null;
		}
		foreach ($keys as $k) {
			if (!is_array($c[$k])) {
				$c[$k] = array();
			}
			$c = &$c[$k];
		}
	}
}
if (!function_exists('staticmapcmp')) {
	function staticmapcmp($a, $b) {
		return ipcmp($a['ipaddr'], $b['ipaddr']);
	}
}
if (!function_exists('staticmaps_sort')) {
	function staticmaps_sort($ifgui) {
		global $g, $config;
	
		usort($config['dhcpd'][$ifgui]['staticmap'], "staticmapcmp");
	}
}

// Check if MAC address is in valid form (using pfSense functions in util.inc)
if (!function_exists('checkMac')) {
	function checkMac($mac) {
		global $input_errors;
	
		if (($mac && !is_macaddr($mac))) {
			$input_errors[] = gettext("A valid MAC address must be specified.");
			return false;
		}
		return true;
	}
}

// Check if IP address is in valid form and if it's inside a valid Reservation range (using pfSense functions in util.inc)
if (!function_exists('checkIp')) {
	function checkIp($if, $ip) {
		global $config, $input_errors;
		
		$a_pools = $config['dhcpd'][$if]['pool'];
		$ifcfgip = get_interface_ip($if);
		$ifcfgsn = get_interface_subnet($if);
	
		if (($ip && !is_ipaddrv4($ip))) {
			$input_errors[] = gettext("A valid IPv4 address must be specified.");
			return false;
		}
	
		if (is_inrange_v4($ip, $config['dhcpd'][$if]['range']['from'], $config['dhcpd'][$if]['range']['to'])) {
			$input_errors[] = sprintf(gettext("The IP address must not be within the DHCP range for this interface."));
			return false;
		}
	
		foreach ($a_pools as $pidx => $p) {
			if (is_inrange_v4($ip, $p['range']['from'], $p['range']['to'])) {
				$input_errors[] = gettext("The IP address must not be within the range configured on a DHCP pool for this interface.");
				return false;
			}
		}
	
		$lansubnet_start = gen_subnetv4($ifcfgip, $ifcfgsn);
		$lansubnet_end = gen_subnetv4_max($ifcfgip, $ifcfgsn);
		if (!is_inrange_v4($ip, $lansubnet_start, $lansubnet_end)) {
			$input_errors[] = sprintf(gettext("The IP address must lie in the %s subnet."), $if);
			return false;
		}
	
		if ($ip == $lansubnet_start) {
			$input_errors[] = sprintf(gettext("The IP address cannot be the %s network address."), $if);
			return false;
		}
	
		if ($ip == $lansubnet_end) {
			$input_errors[] = sprintf(gettext("The IP address cannot be the %s broadcast address."), $if);
			return false;
		}
	
		return true;
	}
}

// Check if Hostname is in valid form (using pfSense functions in util.inc)
if (!function_exists('checkHostName')) {
	function checkHostName($hostName) {
		global $input_errors;
		
		if ($hostName) {
			preg_match("/\-\$/", $hostName, $matches);
			if ($matches) {
				$input_errors[] = gettext("The hostname cannot end with a hyphen according to RFC952");
				return false;
			}
			if (!is_hostname($hostName)) {
				$input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
				return false;
			} else {
				if (!is_unqualified_hostname($hostName)) {
					$input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
					return false;
				}
			}
		}
		return true;
	}
}

// Calls "mark_subsystem_dirty" functions after config update
if (!function_exists('triggerUpdate')) {
	function triggerUpdate($if) {
		global $config;
	
		if (isset($config['dhcpd'][$if]['enable'])) {
			mark_subsystem_dirty('staticmaps');
			if (isset($config['dnsmasq']['enable']) && isset($config['dnsmasq']['regdhcpstatic'])) {
				mark_subsystem_dirty('hosts');
			}
			if (isset($config['unbound']['enable']) && isset($config['unbound']['regdhcpstatic'])) {
				mark_subsystem_dirty('unbound');
			}
		}
	}
}

// Errors and update status output to shell in a readable format
if (!function_exists('printErrors')) {
	function printErrors() {
		global $input_errors;
	
		foreach ($input_errors as $row) {
			if ($row[0] == '#') {
				print_r($row);
				print_r("\n");
			} else {
				print_r("   ".$row);
				print_r("\n");
			}
		}
	}
}

// Verify and add a single reservation to the config array (also delete ARP static entry for that IP)
if (!function_exists('addReserv')) {
	function addReserv($if, $mac, $ip, $hostName, $description) {
		global $config, $input_errors;
	
		$input_errors[] = "# Processing $description (MAC: $mac   IP: $ip   Hostname: $hostName) on IF: $if...";
		if(checkMac($mac) && checkIp($if, $ip) && checkHostName($hostName)) {
			$tempArray = ['mac' => $mac, 'cid' => '', 'ipaddr' => $ip, 'hostname' => $hostName, 'descr' => $description, 'filename' => '', 'rootpath' => '', 'defaultleasetime' => '', 'maxleasetime' => '', 'gateway' => '', 'domain' => '', 'domainsearchlist' => '', 'ddnsdomain' => '', 'ddnsdomainprimary' => '', 'ddnsdomainsecondary' => '', 'ddnsdomainkeyname' => '', 'ddnsdomainkeyalgorithm' => 'hmac-md5', 'ddnsdomainkey' => '', 'tftp' => '', 'ldap' => '', 'nextserver' => '', 'filename32' => '', 'filename64' => '', 'filename32arm' => '', 'filename64arm' => '', 'uefihttpboot' => '', 'numberoptions' => ''];
	
			$updated = false;
			foreach ($config['dhcpd'][$if]['staticmap'] as $key => $entry) {
				if ($entry['mac'] == $mac) {
					$config['dhcpd'][$if]['staticmap'][$key] = $tempArray;
					$updated = true;
					$input_errors[] = "OK (updated existing entry)";
					break;
				}
			}
			if (!$updated) {
				array_push($config['dhcpd'][$if]['staticmap'], $tempArray);
				$input_errors[] = "OK";
			}
			mwexec("/usr/sbin/arp -d $ip");
		}
	}
}

// Important: check interface name from dhcp server links (this is the internal name, not the name shown in the GUI)
$if = 'opt1';

init_config_arr(array('dhcpd', $if, 'staticmap'));


// List of DHCP reservation to add, one per line
// Format addReserv($if, '<MAC Address>', '<IP Address>', '<Hostname>', '<Description>');
addReserv($if, '11:12:11:12:11:12', '172.20.4.15', 'test-entry1', 'Test entry 1');
addReserv($if, '11:12:11:12:11:13', '172.20.4.15', 'test-entry2', 'Test entry 2');
addReserv($if, '11:12:11:12:11:12', '172.20.4.10', 'test-entry1', 'Test entry 1');


// Sorts the staticmap array for that interface, write HTML config, trigger the update and print errors/status
staticmaps_sort($if);
write_config("DHCP Server settings saved (mass insert)");
triggerUpdate($if);
printErrors();

?>