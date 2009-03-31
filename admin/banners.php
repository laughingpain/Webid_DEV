<?php
/***************************************************************************
 *   copyright				: (C) 2008 WeBid
 *   site					: http://www.webidsupport.com/
 ***************************************************************************/

/***************************************************************************
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version. Although none of the code may be
 *   sold. If you have been sold this script, get a refund.
 ***************************************************************************/

require('../includes/common.inc.php');
include $include_path . 'functions_admin.php';
include $include_path . 'functions_admin.php';
include 'loggedin.inc.php';

unset($ERR);

if (isset($_POST['action']) && $_POST['action'] == "update") {
	$query = "update ".$DBPrefix."settings set banners = '" . $system->cleanvars($_POST['banners']) . "'";
	$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
	$ERR = $MSG['600'];
	$system->SETTINGS['banners'] = $_POST['banners'];
}

loadblock($MSG['597'], $MSG['_0009'], 'batch', 'banners', $system->SETTINGS['banners'], $MSG['030'], $MSG['029']);

$template->assign_vars(array(
		'ERROR' => (isset($ERR)) ? $ERR : '',
		'SITEURL' => $system->SETTINGS['siteurl'],
		'TYPE' => 'ban',
		'TYPENAME' => $MSG['25_0011'],
		'PAGENAME' => $MSG['5205']
		));

$template->set_filenames(array(
		'body' => 'adminpages.html'
		));
$template->display('body');
?>
