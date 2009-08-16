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

if (!defined('InWeBid')) exit('Access denied');

class fees
{
	var $ASCII_RANGE;

	function fees()
	{
		$this->ASCII_RANGE = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	}

	function paypal_validate()
	{
		global $system, $_POST;

		$confirmed = false;	// used to check if the payment is confirmed
		$errstr = $error_output = '';
		$errno = 0;

		// we ensure that the txn_id (transaction ID) contains only ASCII chars...
		$pos = strspn($_POST['txn_id'], $this->ASCII_RANGE);
		$len = strlen($_POST['txn_id']);

		if ($pos != $len)
		{
			return;
		}

		//validate payment
		$req = 'cmd=_notify-validate';

		foreach ($this->data as $key => $value)
		{
			$value = urlencode(stripslashes($value));
			$req .= '&' . $key . '=' . $value;
		}

		$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

		$fp = fsockopen ('www.paypal.com', 80, $errno, $errstr, 30);

		$payment_status = $_POST['payment_status'];
		$payment_gross = $_POST['mc_gross'];
		$payment_currency = $_POST['mc_currency'];
		$txn_id = $_POST['txn_id'];

		list($custom, $fee_type) = explode('WEBID', $_POST['custom']);

		if (!$fp)
		{
			$error_output = $errstr . ' (' . $errno . ')';
		}
		else
		{
			fputs ($fp, $header . $req);

			while (!feof($fp))
			{
				$res = fgets ($fp, 1024);

				if (strcmp ($res, 'VERIFIED') == 0)
				{
					$this->callback_process($custom_id, $fee_type, $payment_gateway, $payment_amount);
				}
			}
			fclose ($fp);
		}
	}

	function callback_process($custom_id, $fee_type, $payment_gateway, $payment_amount, $currency = NULL)
	{
		global $system, $DBPrefix;

		switch ($fee_type)
		{
			case 1:
				$addquery = '';
				if ($system->SETTINGS['fee_disable_acc'] == 'y')
				{
					$query = "SELECT suspended, balance FROM " . $DBPrefix . "users WHERE id = " . $custom_id;
					$res = mysql_query($query);
					$system->check_mysql($res, $query, __LINE__, __FILE__);
					$data = mysql_fetch_assoc($res);
					// reable user account if it was disabled
					if ($data['suspended'] == 7 && ($data['balance'] + $payment_amount) >= 0)
					{
						$addquery = ', suspended = 0 ';
					}
				}
				$query = "UPDATE " . $DBPrefix . "users SET balance = balance + " . $payment_amount . $addquery . " WHERE id = " . $custom_id;
				$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
			break;
			case 2:
				$query = "UPDATE " . $DBPrefix . "winners SET paid = 1 WHERE id = " . $custom_id;
				$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
			break;
			case 3:
				$query = "UPDATE " . $DBPrefix . "users SET suspended = 0 WHERE id = " . $custom_id;
				$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
			break;
			case 4:
				$query = "UPDATE " . $DBPrefix . "auctions SET suspended = 0 WHERE id = " . $custom_id;
				$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
				$query = "DELETE FROM " . $DBPrefix . "userfees WHERE auc_id = " . $custom_id;
				$system->check_mysql(mysql_query($query), $query, __LINE__, __FILE__);
			break;
		}
	}
}
?>