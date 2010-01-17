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

include 'includes/common.inc.php';
include $include_path . 'countries.inc.php';
include $include_path . 'dates.inc.php';
include $main_path . 'language/' . $language . '/categories.inc.php';

// set default variables
$catscontrol = new MPTTcategories();
$NOW = time();
$userjoin = '';
$ora = '';
$wher = '';
$payment = (isset($_POST['payment'])) ? $_POST['payment'] : '';

// so paginations work
if (!empty($_POST))
{
	$_SESSION['advs'] = $_POST;
}
elseif (!isset($_GET['PAGE']) && empty($_POST))
{
	unset($_SESSION['advs']);
}

if (isset($_GET['PAGE']))
{
	$page = $_GET['PAGE'];
}
else
{
	$page = 1;
}

if (isset($_SESSION['advs']) && is_array($_SESSION['advs']))
{
	if (!empty($_SESSION['advs']['title']))
	{
		$wher .= '(';
		if (isset($_SESSION['advs']['desc']))
		{
			$wher .= "(au.description like '%" . $system->cleanvars($_SESSION['advs']['title']) . "%') OR ";
		}
		$wher .= "(au.title like '%" . $system->cleanvars($_SESSION['advs']['title']) . "%' OR au.id = " . intval($_SESSION['advs']['title']) . ")) AND ";
	}
	
	if (!empty($_SESSION['advs']['seller']))
	{
		$query = "SELECT id FROM " . $DBPrefix . "users WHERE nick = '" . $system->cleanvars($_SESSION['advs']['seller']) . "'";
		$res = mysql_query($query);
		$system->check_mysql($res, $query, __LINE__, __FILE__);
	
		if (mysql_num_rows($res) > 0)
		{
			$SELLER_ID = mysql_result($res, 0, 'id');
			$wher .= "(au.user = '" . $SELLER_ID . "') AND ";
		}
		else
		{
			$wher .= "(au.user LIKE '%-------------%') AND ";
		}
	}
	
	if (isset($_SESSION['advs']['buyitnow']))
	{
		$wher .= "(au.buy_now > 0 AND (au.bn_only = 'y' OR au.bn_only = 'n' && (au.num_bids = 0 OR (au.reserve_price > 0 AND au.current_bid < au.reserve_price)))) AND ";
	}
	
	if (isset($_SESSION['advs']['buyitnowonly']))
	{
		$wher .= "(au.bn_only = 'y') AND ";
	}
	
	if (!empty($_SESSION['advs']['zipcode']))
	{
		$userjoin = "LEFT JOIN " . $DBPrefix . "users u ON (u.id = au.user)";
		$wher .= "(u.zip LIKE '%" . addslashes($_SESSION['advs']['zipcode']) . "%') AND ";
	}
	
	if (!isset($_SESSION['advs']['closed']))
	{
		$wher .= "(au.closed = '0') AND ";
	}
	
	if (!empty($_SESSION['advs']['category']))
	{
		$query = "SELECT right_id, left_id FROM " . $DBPrefix . "categories WHERE cat_id = " . $_SESSION['advs']['category'];
		$res = mysql_query($query);
		$system->check_mysql($res, $query, __LINE__, __FILE__);
		$parent_node = mysql_fetch_assoc($res);
		$children = $catscontrol->get_children_list($parent_node['left_id'], $parent_node['right_id']);
		$childarray = array($_SESSION['advs']['category']);
		foreach ($children as $k => $v)
		{
			$childarray[] = $v['cat_id'];
		}
		$catalist = '(';
		$catalist .= implode(',', $childarray);
		$catalist .= ')';
		$wher .= "(au.category IN " . $catalist . ") AND ";
	}
	
	if (!empty($_SESSION['advs']['maxprice'])) $wher .= "(au.minimum_bid <= " . floatval($_SESSION['advs']['maxprice']) . ") AND ";
	if (!empty($_SESSION['advs']['minprice'])) $wher .= "(au.minimum_bid >= " . floatval($_SESSION['advs']['minprice']) . ") AND ";
	
	if (!empty($_SESSION['advs']['ending']) && ($_SESSION['advs']['ending'] == '1' || $_SESSION['advs']['ending'] == '2' || $_SESSION['advs']['ending'] == '4' || $_SESSION['advs']['ending'] == '6'))
	{
		$data = time() + ($ending * 86400);
		$wher .= "(au.ends <= $data) AND ";
	}
	
	if (!empty($_SESSION['advs']['country']))
	{
		$userjoin = "LEFT JOIN " . $DBPrefix . "users u ON (u.id = au.user)";
		$wher .= "(u.country = '" . $system->cleanvars($_SESSION['advs']['country']) . "') AND ";
	}
	
	if (isset($_SESSION['advs']['payment']))
	{
		if (is_array($_SESSION['advs']['payment']) && count($_SESSION['advs']['payment']) > 1)
		{
			$pri = false;
			foreach ($payment as $key => $val)
			{
				if (!$pri)
				{
					$ora = "((au.payment LIKE '%" . addslashes($val) . "%')";
				}
				else
				{
					$ora .= " OR (au.payment LIKE '%" . addslashes($val) . "%') AND ";
				}
				$pri = true;
			}
			$ora .= ") ";
		}
		else
		{
			$ora = "(au.payment LIKE '%" . addslashes($_SESSION['advs']['payment'][0]) . "%') AND ";
		}
	}
	
	if (isset($_SESSION['advs']['SortProperty']) && $_SESSION['advs']['SortProperty'] == 'starts')
	{
		$by = 'au.starts DESC';
	}
	elseif (isset($_SESSION['advs']['SortProperty']) && $_SESSION['advs']['SortProperty'] == 'min_bid')
	{
		$by = 'au.minimum_bid';
	}
	elseif (isset($_SESSION['advs']['SortProperty']) && $_SESSION['advs']['SortProperty'] == 'max_bid')
	{
		$by = 'au.minimum_bid DESC';
	}
	else
	{
		$by = 'au.ends ASC';
	}
}

if ((!empty($wher) || !isset($ora)) && isset($_SESSION['advs']) && is_array($_SESSION['advs']))
{
	// retrieve records corresponding to passed page number
	$PAGE = intval($page);
	if ($PAGE == 0) $PAGE = 1;
	$lines = 50;

	// determine limits for SQL query
	$left_limit = ($PAGE - 1) * $lines;

	// get total number of records
	$query = "SELECT count(*) AS total FROM " . $DBPrefix . "auctions au
			" . $userjoin . "
			WHERE au.suspended = 0
			AND " . $wher . $ora . "
			au.starts <= " . $NOW . "
			ORDER BY " . $by;
	$res = mysql_query($query);
	$system->check_mysql($res, $query, __LINE__, __FILE__);

	$hash = mysql_fetch_assoc($res);
	$total = (int)$hash['total'];

	// get number of pages
	$PAGES = intval($total / $lines);
	if (($total % $lines) > 0)
		++$PAGES;

	// get records corresponding to this page
	$query = "SELECT au.* FROM " . $DBPrefix . "auctions au
			" . $userjoin . "
			WHERE au.suspended = 0
			AND " . $wher . $ora . "
			au.starts <= " . $NOW . "
			ORDER BY " . $by . " LIMIT " . intval($left_limit) . ", " . intval($lines);
	$res = mysql_query($query);
	$system->check_mysql($res, $query, __LINE__, __FILE__);
	
	// get featured items
	$query = "SELECT au.* FROM " . $DBPrefix . "auctions au
			" . $userjoin . "
			WHERE au.suspended = 0
			AND " . $wher . $ora . "
			featured = 'y'
			AND	au.starts <= " . $NOW . "
			ORDER BY " . $by . " LIMIT " . intval(($PAGE - 1) * 5) . ", 5";
	$feat_res = mysql_query($query);
	$system->check_mysql($feat_res, $query, __LINE__, __FILE__);

	if (mysql_num_rows($res) > 0)
	{
		include $include_path . 'browseitems.inc.php';
		browseItems($res, $feat_res, $total, 'adsearch.php');

		include 'header.php';
		$template->set_filenames(array(
				'body' => 'asearch_result.tpl'
				));
		$template->display('body');
		include 'footer.php';
		exit;
	}
	else
	{
		$ERR = $ERR_122;
	}
}

// payment
$qurey = "SELECT * FROM " . $DBPrefix . "payments";
$res = mysql_query($qurey);
$system->check_mysql($res, $qurey, __LINE__, __FILE__);
$TPL_payments_list = '';
while ($row = mysql_fetch_assoc($res))
{
	$TPL_payments_list .= '<input type="checkbox" name="payment[]" value="' . $row['description'] . '"';
	if (isset($payment[$i]) && $row['description'] == $payment[$i])
	{
		$TPL_payments_list .= ' checked=true';
	}
	$TPL_payments_list .= ' /> ' . $row['description'] . '<br>';
	$i++;
}
// category
$TPL_categories_list = '<select name="category" onChange="javascript:document.adsearch.submit()">' . "\n";
if (isset($category_plain) && count($category_plain) > 0)
{
	$category = (isset($_SESSION['advs']['category'])) ? $_SESSION['advs']['category'] : '';
	foreach ($category_plain as $k => $v)
	{
		$TPL_categories_list .= "\t\t" . '<option value="' . $k . '" ' . (($k == $category) ? ' selected="true"' : '') . '>' . $v . '</option>' . "\n";
	}
}
$TPL_categories_list .= '</select>' . "\n";
// Variant fields construction
$cattree = array();
// country
$TPL_countries_list = '<select name="country">' . "\n";
reset($countries);
$country = (isset($_SESSION['advs']['country'])) ? $_SESSION['advs']['country'] : '';
foreach ($countries as $key => $val)
{
	$TPL_countries_list .= "\t" . '<option value="' . $val . '"' . (($val == $country) ? ' selected="true"' : '') . '>' . $val . '</option>' . "\n";
}
$TPL_countries_list .= '</select>' . "\n";

$template->assign_vars(array(
		'ERROR' => (isset($ERR)) ? $ERR : '',
		'CATEGORY_LIST' => $TPL_categories_list,
		'CURRENCY' => $system->SETTINGS['currency'],
		'PAYMENTS_LIST' => $TPL_payments_list,
		'COUNTRY_LIST' => $TPL_countries_list
		));

include 'header.php';
$template->set_filenames(array(
		'body' => 'advanced_search.tpl'
		));
$template->display('body');
include 'footer.php';
?>
