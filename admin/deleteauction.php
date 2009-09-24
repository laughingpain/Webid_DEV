<?php
/***************************************************************************
 *   copyright				: (C) 2008, 2009 WeBid
 *   site					: http://www.webidsupport.com/
 ***************************************************************************/

/***************************************************************************
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version. Although none of the code may be
 *   sold. If you have been sold this script, get a refund.
 ***************************************************************************/

define('InAdmin', 1);
include '../includes/common.inc.php';
include $include_path . 'functions_admin.php';
include 'loggedin.inc.php';
include $main_path . 'language/' . $language . '/categories.inc.php';

// Data check
if (!isset($_REQUEST['id']))
{
	$URL = $_SESSION['RETURN_LIST'];
	unset($_SESSION['RETURN_LIST']);
	header('location: ' . $URL);
	exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'update')
{
	$catscontrol = new MPTTcategories();
	$auc_id = intval($_POST['id']);

	// get auction data
	$query = "SELECT category, num_bids, suspended, closed FROM " . $DBPrefix . "auctions WHERE id = " . $auc_id;
	$res = mysql_query($query);
	$system->check_mysql($res, $query, __LINE__, __FILE__);
	$auc_data = mysql_fetch_assoc($res);

	// Delete related values
	$query = "DELETE FROM " . $DBPrefix . "auctions WHERE id = " . $auc_id;
	$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);

	// delete bids
	$query = "DELETE FROM " . $DBPrefix . "bids WHERE auction = " . $auc_id;
	$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);

	// Delete proxybids
	$query = "DELETE FROM " . $DBPrefix . "proxybid WHERE itemid = " . $auc_id;
	$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);

	// Delete file in counters
	$query = "DELETE FROM " . $DBPrefix . "auccounter WHERE auction_id = " . $auc_id;
	$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);

	if ($auc_data['suspended'] == 0 && $auc_data['closed'] == 0)
	{
		// update main counters
		$query = "UPDATE " . $DBPrefix . "counters SET auctions = (auctions - 1), bids = (bids - " . $auc_data['num_bids'] . ")";
		$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
	
		// update recursive categories
		$query = "SELECT left_id, right_id, level FROM " . $DBPrefix . "categories WHERE cat_id = " . $auc_data['category'];
		$res = mysql_query($query);
		$system->check_mysql($res, $query, __LINE__, __FILE__);
		$parent_node = mysql_fetch_assoc($res);
		$crumbs = $catscontrol->get_bread_crumbs($parent_node['left_id'], $parent_node['right_id']);
	
		for ($i = 0; $i < count($crumbs); $i++)
		{
			$query = "UPDATE " . $DBPrefix . "categories SET sub_counter = sub_counter - 1 WHERE cat_id = " . $crumbs[$i]['cat_id'];
			$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
		}
	}

	// Delete auctions images
	if (file_exists($upload_path . $auc_id))
	{
		if ($dir = @opendir($upload_path . $auc_id))
		{
			while ($file = readdir($dir))
			{
				if ($file != '.' && $file != '..')
				{
					@unlink($upload_path . $auc_id . '/' . $file);
				}
			}
			closedir($dir);
			@rmdir($upload_path . $auc_id);
		}
	}

	$URL = $_SESSION['RETURN_LIST'];
	unset($_SESSION['RETURN_LIST']);
	header('location: ' . $URL . '?offset=' . $_REQUEST['offset']);
	exit;
}

$query = "SELECT u.nick, a.title, a.starts, a.description, a.category, d.description as duration,
		a.suspended, a.current_bid, a.quantity, a.reserve_price
		FROM " . $DBPrefix . "auctions a
		LEFT JOIN " . $DBPrefix . "users u ON (u.id = a.user)
		LEFT JOIN " . $DBPrefix . "durations d ON (d.days = a.duration)
		WHERE a.id = " . $_GET['id'];
$res = mysql_query($query);
$system->check_mysql($res, $query, __LINE__, __FILE__);
$auc_data = mysql_fetch_assoc($res);

if ($system->SETTINGS['datesformat'] == 'USA')
{
	$date = gmdate('m/d/Y', $auc_data['starts']);
}
else
{
	$date = gmdate('d/m/Y', $auc_data['starts']);
}

$template->assign_vars(array(
		'SITEURL' => $system->SETTINGS['siteurl'],
		'ID' => $_GET['id'],
		'TITLE' => $auc_data['title'],
		'NICK' => $auc_data['nick'],
		'STARTS' => $date,
		'DURATION' => $auc_data['duration'],
		'CATEGORY' => $category_names[$auc_data['category']],
		'DESCRIPTION' => stripslashes($auc_data['description']),
		'CURRENT_BID' => $system->print_money($auc_data['current_bid']),
		'QTY' => $auc_data['quantity'],
		'RESERVE_PRICE' => $system->print_money($auc_data['reserve_price']),
		'SUSPENDED' => $auc_data['suspended'],
		'OFFSET' => $_REQUEST['offset']
		));

$template->set_filenames(array(
		'body' => 'deleteauction.tpl'
		));
$template->display('body');
?>