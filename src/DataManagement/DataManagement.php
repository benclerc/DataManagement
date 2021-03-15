<?php

/**
* 	PHP Class providing simple and compact database requests. Useful for small projects without ORM.
*	@author Benjamin Clerc <contact@benjamin-clerc.com>
*	@copyright Copyright (c) 2017, Benjamin Clerc.
*	@license MIT
*	@link https://github.com/benclerc/DataManagement
*/

namespace DataManagement;

use PDO;
use PDOException;
use Exception;

/**
* 	Database content management.
*	@property PDO $connector PDO object connected to the target database.
*/
class DataManagement {

	private $connector;

	/**
	*	The constructor gathers database information and initiate connection.
	*	@param string $type Database type, must be 'mysql' OR 'pgsql'.
	*	@param string $server Database server's FQDN or IP address.
	*	@param string $port Database server's port.
	*	@param string $name Database's name.
	*	@param string $username Database authorized user username.
	*	@param string $password Database authorized user password.
	*/
	public function __construct(string $type, string $server, int $port, string $name, string $username, string $password) {
		if ($type != 'mysql' && $type != 'pgsql') {
		    throw new Exception('__construct() called by '.debug_backtrace()[1]['function'].'() : Wrong database type provided.');
		} else {
			try {
				$this->connector = new PDO("$type:host=$server;port=$port;dbname=$name", $username, $password);
			} catch (PDOException $e) {
				die('__construct() called by '.debug_backtrace()[1]['function'].'() : Database connexion failed. Error : '.$e->getMessage());
			}
		}
	}


	/**
	*	Function returning the PDO object connected to the target database.
	*	@return PDO PDO object connected to the target database.
	*/
	public function connector() : PDO {
		return $this->connector;
	}


	/**
	*	Function starting a transaction on the current connector.
	*	@return bool Status of the started transaction.
	*/
	public function beginTransaction() : bool {
		return $this->connector->beginTransaction();
	}


	/**
	*	Function ending a transaction on the current connector.
	*	@return bool Status of the transaction's commit.
	*/
	public function commit() : bool {
		return $this->connector->commit();
	}


	/**
	*	Function ending a transaction with a rollback on the current connector.
	*	@return bool Status of the transaction's rollback.
	*/
	public function rollback() : bool {
		return $this->connector->rollback();
	}


	/**
	*	Function used to data from one table (or several table with join) :
	*	@param string $table Table name.
	*	@param array $order Array of column name and wanted order e.g. ['column' => 'ASC/DESC'].
	*	@param array $join Array with wanted join table name as key and array of needed values as values e.g. ['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]].
	*	@param array $filter Array with table name as key and array of array as value with column name and filter value e.g. ['table'=>[['columnname'=>'data']]]. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'. 'data' can also be an array of values.
	*	@param int $limit Number of max rows e.g. 50.
	*	@param int $offset Offset for returned rows e.g. 100.
	*	@param array $columns Array of column name.
	*	@return array 3 PHP arrays : 'fetch' => first result (in an array), 'fetchAll' => array of all the results, 'rowCount' => number of results.
	*/
	public function select(string $table, array $order = NULL, array $join = NULL, array $filter = NULL, int $limit = NULL, int $offset = NULL, array $columns = ['*']) : array {
		// Start SQL request creation
		$sql = "SELECT";
		// Adding the wanted columns
		foreach ($columns as $key => $value) {
			$sql .= ($key == 0) ? " $value" : ", $value";
		}
		// Adding the table name
		$sql .= " FROM ".$table;
		// Adding joined tables if wanted
		if (!empty($join)) {
			$sql .= $this->join($table, $join);
		}
		// Adding filter if wanted
		$data = NULL;
		if (!empty($filter)) {
			$sql .= " WHERE";
			$i = 0;
			foreach ($filter as $key => $value) {
				foreach ($value as $key2 => $value2) {
					foreach ($value2 as $key3 => $value3) {
						switch ($value3) {
							case 'NULL':
								$sql .= ($i == 0) ? " $key.$key3 IS NULL" : " AND $key.$key3 IS NULL";
								break;
							case '!NULL':
								$sql .= ($i == 0) ? " $key.$key3 IS NOT NULL" : " AND $key.$key3 IS NOT NULL";
								break;
							case 'TRUE':
								$sql .= ($i == 0) ? " $key.$key3 IS TRUE" : " AND $key.$key3 IS TRUE";
								break;
							case 'FALSE':
								$sql .= ($i == 0) ? " $key.$key3 IS FALSE" : " AND $key.$key3 IS FALSE";
								break;
							default:
								if (is_array($value3)) {
									$in = '';
									for ($j = 0; $j < count($value3); $j++) {
										$in .= ($j == 0) ? ":filter$key$key3$j" : ", :filter$key$key3$j";
										$data["filter$key$key3$j"] = $value3[$j];
									}
									$sql .= ($i == 0) ? " $key.$key3 IN ($in)" : " AND $key.$key3 IN ($in)";
								} else {
									$sql .= ($i == 0) ? " $key.$key3 = :filter$key$key3" : " AND $key.$key3 = :filter$key$key3";
									$data["filter$key$key3"] = $value3;
								}
								break;
						}
						$i++;
					}
				}
			}
		}
		// Adding order if wanted
		if (!empty($order)) {
			$sql .= " ORDER BY";
			$i = 0;
			foreach ($order as $key => $value) {
				$sql .= ($i == 0) ? " $key $value" : ", $key $value";
				$i++;
			}
		}
		// Adding limit if wanted
		if (!empty($limit)) {
			$sql .= " LIMIT $limit";
		}
		// Adding offset if wanted
		if (!empty($offset)) {
			$sql .= " OFFSET $offset";
		}
		// Closing the query
		$sql .= ";";
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		$query->execute($data);
		// Format return array
		$return['rowCount'] = $query->rowCount();
		$return['fetchAll'] = $query->fetchAll();
		$return['fetch'] = (count($return['fetchAll']) > 0) ? $return['fetchAll'][0] : NULL;
		return $return;
	}


	/**
	*	Function used to retrieve data from the database using a custom SQL request when wanted result is not possible with classic select() method.
	*	@param string $sql SQL request.
	*	@param array $data Array of data e.g. ['columnname'=>'data'].
	*	@return array 3 PHP arrays : 'fetch' => first result (in an array), 'fetchAll' => array of all the results, 'rowCount' => number of results.
	*/
	public function customSelect(string $sql, array $data = NULL) : array {
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		$query->execute($data);
		// Format return array
		$return['rowCount'] = $query->rowCount();
		$return['fetchAll'] = $query->fetchAll();
		$return['fetch'] = (count($return['fetchAll']) > 0) ? $return['fetchAll'][0] : NULL;
		return $return;
	}


	/**
	*	Function used to insert a row in a table of the database.
	*	@param string $table Table name.
	*	@param array $data Array of data e.g. ['columnname'=>'data'].
	*	@return array Array with 2 rows : 'raw' => the database's raw response, 'lastInsertId' => the last insert id.
	*/
	public function insert(string $table, array $data) : array {
		// Start SQL request creation
		$sql = "INSERT INTO $table (";
		// Adding all wanted rows
		$i = 0;
		foreach ($data as $key => $value) {
			$sql .= ($i == 0) ? " $key" : ", $key";
			$i++;
		}
		// Adding values' name
		$sql .= ") VALUES (";
		$i = 0;
		foreach ($data as $key => $value) {
			$sql .= ($i == 0) ? ":$key" : ", :$key";
			$i++;
		}
		// Closing the query
		$sql .= ");";
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		// Format return array
		$return['raw'] = $query->execute($data);
		$return['lastInsertId'] = $this->connector->lastInsertId();
		return $return;
	}


	/**
	*	Function used to insert a row in a table of the database using a custom SQL request when wanted result is not possible with classic insert() method.
	*	@param string $sql SQL request.
	*	@param array $data Array of data e.g. ['columnname'=>'data'].
	*	@return array Array with 2 rows : 'raw' => the database's raw response, 'lastInsertId' => the last insert id.
	*/
	public function customInsert(string $sql, array $data = NULL) : array {
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		// Format return array
		$return['raw'] = $query->execute($data);
		$return['lastInsertId'] = $this->connector->lastInsertId();
		return $return;
	}


	/**
	*	Function used to update row(s) in a table of the database.
	*	@param string $table Table name.
	*	@param array $data Array of data e.g. ['columnname'=>'data'].
	*	@param array $where Array of data pointing the row to update e.g. ['columnname'=>'data']. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'.
	*	@return bool Request's status.
	*/
	public function update(string $table, array $data, array $where) : bool {
		// Start SQL request creation
		$sql = "UPDATE $table SET";
		// Adding all wanted rows to update
		$i = 0;
		foreach ($data as $key => $value) {
			$sql .= ($i == 0) ? " $key=:$key" : ", $key=:$key";
			$i++;
		}
		// Adding the where clause
		$sql .= " WHERE";
		$i = 0;
		$whereData = NULL;
		foreach ($where as $key => $value) {
			switch ($value) {
				case 'NULL':
					$sql .= ($i == 0) ? " $key IS NULL" : " AND $key IS NULL";
					break;
				case '!NULL':
					$sql .= ($i == 0) ? " $key IS NOT NULL" : " AND $key IS NOT NULL";
					break;
				case 'TRUE':
					$sql .= ($i == 0) ? " $key IS TRUE" : " AND $key IS TRUE";
					break;
				case 'FALSE':
					$sql .= ($i == 0) ? " $key IS FALSE" : " AND $key IS FALSE";
					break;
				default:
					$sql .= ($i == 0) ? " $key = :where$key" : " AND $key = :where$key";
					$whereData["where$key"] = $value;
					break;
			}
			$i++;
		}
		// Closing the query
		$sql .= ";";
		// Merging data's array and where clause's array (if needed)
		if (!empty($whereData)) {
			$data = array_merge($data, $whereData);
		}
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		return $query->execute($data);
	}


	/**
	*	Function used to delete row(s) in a table of the database.
	*	@param string $table Table name.
	*	@param array $where Array of data pointing the row to update e.g. ['columnname'=>'data']. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'.
	*	@return bool Request's status.
	*/
	public function delete(string $table, array $where) : bool {
		// Start SQL request creation
		$sql = "DELETE FROM $table WHERE";
		// Adding where clause
		$i = 0;
		$data = NULL;
		foreach ($where as $key => $value) {
			switch ($value) {
				case 'NULL':
					$sql .= ($i == 0) ? " $key IS NULL" : " AND $key IS NULL";
					break;
				case '!NULL':
					$sql .= ($i == 0) ? " $key IS NOT NULL" : " AND $key IS NOT NULL";
					break;
				case 'TRUE':
					$sql .= ($i == 0) ? " $key IS TRUE" : " AND $key IS TRUE";
					break;
				case 'FALSE':
					$sql .= ($i == 0) ? " $key IS FALSE" : " AND $key IS FALSE";
					break;
				default:
					$sql .= ($i == 0) ? " $key = :$key" : " AND $key = :$key";
					$data["$key"] = $value;
					break;
			}
			$i++;
		}
		// Closing the query
		$sql .= ";";
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		return $query->execute($data);
	}


	/**
	*	Function used to count how many times a result exist.
	*	@param string $table Table name.
	*	@param string $column Column name.
	*	@param array $where Array of data pointing the rows to count e.g. ['columnname'=>'data']. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'.
	*	@param array $join = Array with wanted join table name as key and array of needed values as values e.g. ['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]].
	*	@return mixed Request's status on fail or int on success.
	*/
	public function count(string $table, string $column, array $where, array $join = NULL) {
		// Start SQL request creation
		$sql = "SELECT COUNT($column) FROM $table";
		// Adding joined tables if wanted
		if (!empty($join)) {
			$sql .= $this->join($table, $join);
		}
		// Adding where clause
		$sql .= " WHERE";
		$i = 0;
		$data = NULL;
		foreach ($where as $key => $value) {
			switch ($value) {
				case 'NULL':
					$sql .= ($i == 0) ? " $key IS NULL" : " AND $key IS NULL";
					break;
				case '!NULL':
					$sql .= ($i == 0) ? " $key IS NOT NULL" : " AND $key IS NOT NULL";
					break;
				case 'TRUE':
					$sql .= ($i == 0) ? " $key IS TRUE" : " AND $key IS TRUE";
					break;
				case 'FALSE':
					$sql .= ($i == 0) ? " $key IS FALSE" : " AND $key IS FALSE";
					break;
				default:
					$sql .= ($i == 0) ? " $key = :$key" : " AND $key = :$key";
					$data["$key"] = $value;
					break;
			}
			$i++;
		}
		// Closing the query
		$sql .= ";";
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		$query->execute($data);
		return $query->fetch()[0];
	}


	/**
	*	Function used to sum all the values of a particular column.
	*	@param string $table Table name.
	*	@param string $column Column name.
	*	@param array $where Array of data pointing the rows to sum e.g. ['columnname'=>'data']. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'.
	*	@param array $join = Array with wanted join table name as key and array of needed values as values e.g. ['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]].
	*	@return mixed Request's status on fail or int on success.
	*/
	public function sum(string $table, string $column, array $where, array $join = NULL) {
		// Start SQL request creation
		$sql = "SELECT SUM($column) FROM $table";
		// Adding joined tables if wanted
		if (!empty($join)) {
			$sql .= $this->join($table, $join);
		}
		// Adding where clause
		$sql .= " WHERE";
		$i = 0;
		foreach ($where as $key => $value) {
			$sql .= ($i == 0) ? " $key = :$key" : " AND $key = :$key";
			$i++;
		}
		// Closing the query
		$sql .= ";";
		// Preparing the execution
		$query = $this->connector->prepare($sql);
		$query->execute($where);
		return $query->fetch()[0];
	}


	/**
	*	Function used to send a custom request SQL of any type.
	*	@param string $sql SQL request.
	*	@param array $data Array of data e.g. ['columnname'=>'data'].
	*	@return bool Request's status.
	*/
	public function customSQL(string $sql, array $data = NULL) : bool {
		return $this->connector->prepare($sql)->execute($data);
	}


	/**
	*	Function creating join clauses in the request.
	*	@param string $table Default table name needed because from table is optionnal.
	*	@param array $join Array with wanted join table name as key and array of needed values as values e.g. ['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]].
	*	@return string SQL request with all wanted join clauses.
	*/
	private function join(string $table, array $join) : string {
		$sql = '';
		foreach ($join as $key => $value) {
			if (empty($value[3])) {
				$value[3] = $table;
			}
			$sql .= " $value[0] JOIN $key ON $value[3].$value[1] = $key.$value[2]";
		}
		return $sql;
	}

}
