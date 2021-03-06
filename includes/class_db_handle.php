<?php
/***************************************************************************
 *   copyright				: (C) 2008 - 2013 WeBid
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

class db_handle 
{
	// database
	private     $pdo;
	private		$DBPrefix;
	private		$lastquery;
	private		$fetchquery;
	private		$error;

    public function connect($DbHost, $DbUser, $DbPassword, $DbDatabase, $DBPrefix)
    {
        $this->DBPrefix = $DBPrefix;
		try {
			// MySQL with PDO_MYSQL
			$this->pdo = new PDO("mysql:host=$DbHost;dbname=$DbDatabase", $DbUser, $DbPassword);
			// set error reporting up
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// actually use prepared statements
			$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		}
		catch(PDOException $e) {
			$this->error_handler($e->getMessage());
		}
    }

	// to run a direct query
	public function direct_query($query)
	{
		try {
			$this->lastquery = $this->pdo->query($query);
		}
		catch(PDOException $e) {
			$this->error_handler($e->getMessage());
		}
	}

	// put together the quert ready for running
	/*
	$query must be given like SELECT * FROM table WHERE this = :that AND where = :here
	then $params would holds the values for :that and :here, $table would hold the vlue for :table
	$params = array(
		array(':that', 'that value', PDO::PARAM_STR),
		array(':here', 'here value', PDO::PARAM_INT),
	);
	last value can be left blank more info http://php.net/manual/en/pdostatement.bindparam.php
	*/
	public function query($query, $params = array())
	{
		try {
			//$query = $this->build_query($query, $table);
			$params = $this->clean_params($query, $params);
			$this->lastquery = $this->pdo->prepare($query);
			//$this->lastquery->bindParam(':table', $this->DBPrefix . $table, PDO::PARAM_STR); // must always be set
			foreach ($params as $key)
			{
			$this->lastquery->bindParam($key[0], $key[1], $key[2]);
			}
			$this->lastquery->execute();
			//$this->lastquery->debugDumpParams();
		}
		catch(PDOException $e) {
			//$this->lastquery->debugDumpParams();
			$this->error_handler($e->getMessage());
		}

		//$this->lastquery->rowCount(); // rows affected
	}

	// put together the quert ready for running
	public function fetch($method = 'FETCH_ASSOC')
	{
		try {
			// set fetchquery
			if ($this->fetchquery == NULL)
			{
				$this->fetchquery = $this->lastquery;
			}
			if ($method == 'FETCH_ASSOC')
			{
			$result = $this->fetchquery->fetch(PDO::FETCH_ASSOC);
			}
			if ($method == 'FETCH_BOTH')
			{
			$result = $this->fetchquery->fetch(PDO::FETCH_BOTH);
			}
			if ($method == 'FETCH_NUM')
			{
			$result = $this->fetchquery->fetch(PDO::FETCH_NUM);
			}
			// clear fetch query
			if ($result == false)
			{
				$this->fetchquery = NULL;
			}
			return $result;
		}
		catch(PDOException $e) {
			$this->error_handler($e->getMessage());
		}
	}

	public function result($column = NULL)
	{
		$data = $this->lastquery->fetch(PDO::FETCH_BOTH);
		if (empty($column) || $column == NULL)
		{
			return $data;
		}
		else
		{
			return $data[$column];
		}
	}

	public function lastInsertId()
	{
		try {
			return $this->pdo->lastInsertId();
		}
		catch(PDOException $e) {
			$this->error_handler($e->getMessage());
		}
	}

	private function clean_params($query, $params)
	{
		// find the vars set in the query
		preg_match_all("(:[a-zA-Z_]+)", $query, $set_params);
		//print_r("params" . $query);
		//print_r($params);
		//print_r("set_params");
		//print_r($set_params);
		$new_params = array();
		foreach ($set_params[0] as $val)
		{
			$key = $this->find_key($params, $val);
			$new_params[] = $params[$key];
		}
		//print_r("new_params");
		//print_r($new_params);
		return $new_params;
	}

	private function find_key($params, $val)
	{
		foreach ($params as $k => $v)
		{
			if ($v[0] == $val)
				return $k;
		}
	}

	private function build_query($query, $table)
	{
		// needs some security
		return str_replace(':table', $this->DBPrefix . $table, $query);
	}

	private function error_handler($error)
	{
		// DO SOMETHING
		//$this->error = $error;
		$this->error = debug_backtrace();
		//print_r($this->error);
	}

	// close everything down
	public function __destruct()
	{
		// close database connection
		$this->pdo = null;
	}
}
?>