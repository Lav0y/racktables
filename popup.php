<?php
	require 'inc/interface.php';
	require 'inc/init.php';

// Return a list of rack IDs, which are P or less positions
// far from the given rack in its row.
function getProximateRacks ($rack_id, $proximity = 0)
{
	$rack = spotEntity ('rack', $rack_id);
	$rackList = listCells ('rack', $rack['row_id']);
	doubleLink ($rackList);
	$ret = array ($rack_id);
	$todo = $proximity;
	$cur_item = $rackList[$rack_id];
	while ($todo and array_key_exists ('prev_key', $cur_item))
	{
		$cur_item = $rackList[$cur_item['prev_key']];
		$ret[] = $cur_item['id'];
		$todo--;
	}
	$todo = $proximity;
	$cur_item = $rackList[$rack_id];
	while ($todo and array_key_exists ('next_key', $cur_item))
	{
		$cur_item = $rackList[$cur_item['next_key']];
		$ret[] = $cur_item['id'];
		$todo--;
	}
	return $ret;
}

function findSparePorts ($port_id, $only_racks = array())
{
	$query = "SELECT id, object_id, name FROM Port WHERE " .
		"id <> ${port_id} " .
		"AND type IN (SELECT type2 FROM PortCompat WHERE type1 = (SELECT type FROM Port WHERE id = ${port_id})) " .
		"AND reservation_comment IS NULL " .
		"AND id NOT IN (SELECT porta FROM Link) " .
		"AND id NOT IN (SELECT portb FROM Link) ";
	if (count ($only_racks))
		$query .= 'AND object_id IN (SELECT DISTINCT object_id FROM RackSpace WHERE rack_id IN (' .
			implode (', ', $only_racks) . '))';
	$query .= ' ORDER BY object_id, name';
	$result = useSelectBlade ($query, __FUNCTION__);
	// avoid nested queries
	$rows = $result->fetchAll (PDO::FETCH_ASSOC);
	unset ($result);
	$ret = array();
	foreach (array_keys ($rows) as $rkey)
	{
		$object = spotEntity ('object', $rows[$rkey]['object_id']);
		$ret[$rows[$rkey]['id']] = $object['dname'] . ' ' . $rows[$rkey]['name'];
		unset ($rows[$rkey]);
	}
	return $ret;
}

header ('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" style="height: 100%;">
<head>
<?php
	echo "<title>RackTables pop-up</title>\n";
	printPageHeaders();
	echo '</head><body style="height: 100%;">';
	assertStringArg ('helper', __FILE__);
	switch ($_REQUEST['helper'])
	{
		case 'portlist':
			$pageno = 'depot';
			$tabno = 'default';
			fixContext();
			if (!permitted())
				renderAccessDenied();
			assertUIntArg ('port', __FILE__);
			assertStringArg ('in_rack', __FILE__);
			$localchoice = $_REQUEST['in_rack'] == 'y';
			$port_id = $_REQUEST['port'];
			echo '<div style="background-color: #f0f0f0; border: 1px solid #3c78b5; padding: 10px; height: 100%; text-align: center; margin: 5px;"><h2>';
			echo $localchoice ?
				('Nearest spare ports (<a href="popup.php?helper=portlist&port=' . $port_id . '&in_rack=n">show all</a>)') :
				('All spare ports (<a href="popup.php?helper=portlist&port=' . $port_id . '&in_rack=y">show nearest</a>)');
			echo '</h2><form action="javascript:;">';
			$only_racks = array();
			$port_info = getPortInfo ($port_id);
			if ($_REQUEST['in_rack'] == 'y' and $port_info['object_id'])
			{
				$object = spotEntity ('object', $port_info['object_id']);
				if ($object['rack_id'])
					$only_racks = getProximateRacks ($object['rack_id'], getConfigVar ('PROXIMITY_RANGE'));
			}
			$spare_ports = findSparePorts ($port_id, $only_racks);
			printSelect ($spare_ports, array ('name' => 'ports', 'id' => 'ports', 'size' => getConfigVar ('MAXSELSIZE')));
			echo '<br><br>';
			echo "<input type='submit' value='Link' onclick='".
			"if (getElementById(\"ports\").value != \"\") {".
			"	opener.location=\"process.php?page=object&tab=ports&op=linkPort&object_id=${port_info['object_id']}&port_id=$port_id&remote_port_id=\"+getElementById(\"ports\").value; ".
			"	window.close();}'>";
			echo '</form></div>';
			break;
		case 'inet4list':
			$pageno = 'ipv4space';
			$tabno = 'default';
			fixContext();
			if (!permitted())
				renderAccessDenied();
			echo '<div style="background-color: #f0f0f0; border: 1px solid #3c78b5; padding: 10px; height: 100%; text-align: center; margin: 5px;">';
			echo '<h2>Choose a port:</h2><br><br>';
			echo '<form action="javascript:;">';
			echo '<input type=hidden id=ip>';
			echo '<select size=' . getConfigVar ('MAXSELSIZE') . ' id=addresses>';
			$addresses = getAllIPv4Allocations();
			usort ($addresses, 'sortObjectAddressesAndNames');
			foreach ($addresses as $address)
				echo "<option value='${address['ip']}' onclick='getElementById(\"ip\").value=\"${address['ip']}\";'>" .
					"${address['object_name']} ${address['name']} ${address['ip']}</option>\n";
			echo '</select><br><br>';
			echo "<input type=submit value='Proceed' onclick='".
			"if (getElementById(\"ip\")!=\"\") {".
			" opener.document.getElementById(\"remoteip\").value=getElementById(\"ip\").value;".
			" window.close();}'>";
			echo '</form></div>';
			break;
		default:
			showError ('Invalid parameter or internal error', __FILE__);
			break;
	}
?>
</body>
</html>
