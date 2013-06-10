<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* PDO Class file
*
* $Id: EbotPDO.php 9 2010-11-24 18:08:17Z tmichelbrink $
*
*/

class EbotPDO {
	/**
	* The last time that connect, query, prepare, execute, or exec happened
	* @var int timestamp
	*/
	public $lastUseTime = 0;

	/**
	* How many seconds of inactivity before a reconnect should happen
	* (default 300 seconds / 5 minutes)
	* @var int
	*/
	private $reconnectIdleSeconds = 300;

	/**
	* The actual PDO object so we can close and open it from in here
	* @var PDO
	*/
	private $pdo = null;

	/**
	* The connection string used when constructed
	* @var string
	*/
	private $dsn = '';

	/**
	* The database username to connect as
	* @var string
	*/
	private $username = '';

	/**
	* The database user's password when connecting
	* @var string
	*/
	private $password = '';

	/**
	* The most recent SQL passed through this object
	* @var string
	*/
	public $queryString = '';

	public function __construct($dbConfig) {
		$this->dsn = $dsn = "mysql:dbname={$dbConfig->mySQLdb};host={$dbConfig->mySQLserver}";
		$this->username = $dbConfig->mySQLuser;
		$this->password = $dbConfig->mySQLpassword;

		$this->reconnect();
	} // __construct

	public function __destruct() {
		$this->disconnect();
	} // __destruct

	private function verifyPdoConnection() {
		if (is_null($this->pdo)) {
			$this->reconnect();
			//throw new PDOException('Connection is closed');
		}
	} // verifyPdoConnection

	private function checkReconnect() {
		$lastUsed = time() - $this->lastUseTime;
		// if its been too long, then reconnect
		if ($lastUsed > $this->reconnectIdleSeconds) {
			$this->reconnect();
		}
	} // checkReconnect

	public function disconnect() {
		$this->pdo = null;
	} // disconnect

	public function exec($statement) {
		$this->queryString = $statement;
		$this->verifyPdoConnection();
		$this->checkReconnect();

		$x = $this->pdo->exec($statement);
		$this->lastUseTime = time();
		return $x;
	} // exec

	public function getRecord($selectSql, $fetch_style = PDO::FETCH_ASSOC) {
		$rs = $this->query($selectSql);
		if (!$rs) {
			return false;
		}
		$row = $rs->fetch($fetch_style);
		$rs->closeCursor();
		unset($rs);
		return $row;
	} // getRecord

	public function getRecordSet($selectSql, $fetch_style = PDO::FETCH_ASSOC) {
		$rs = $this->query($selectSql);
		if (!$rs) {
			return false;
		}
		$rows = $rs->fetchAll($fetch_style);
		$rs->closeCursor();
		unset($rs);
		return $rows;
	} // getRecord

	public function lastInsertId($name = null) {
		$this->verifyPdoConnection();
		return $this->pdo->lastInsertId($name);
	} // lastInsertId

	public function query($statement, $fetch_style = PDO::FETCH_BOTH, $var1 = null, $var2 = null) {
		$this->queryString = $statement;
		$this->verifyPdoConnection();
		$this->checkReconnect();
		// select the correct prototype in php pdo object
		if (!is_null($var2)) {
			$rs = $this->pdo->query($statement, $fetch_style, $var1, $var2);
		} elseif (!is_null($var1)) {
			$rs = $this->pdo->query($statement, $fetch_style, $var1);
		} else {
			$rs = $this->pdo->query($statement, $fetch_style);
		}
		$this->lastUseTime = time();
		return $rs;
	} // query

	public function quote($string, $parameter_type = PDO::PARAM_STR) {
		$this->verifyPdoConnection();
		return $this->pdo->quote($string, $parameter_type);
	} // quote

	public function reconnect() {
		$this->disconnect();
		// make new connection
		$this->pdo = new PDO($this->dsn, $this->username, $this->password);
		$this->lastUseTime = time();
	} // reconnect

	public function setReconnectIdleSeconds($seconds) {
		if ($seconds >= 1) {
			$this->reconnectIdleSeconds = $seconds;
		}
		return $this->reconnectIdleSeconds;
	} // setReconnectIdleSeconds

	public function prepare($qry) {
		return $this->pdo->prepare($qry);
	}

	public function getRandomRecord($type, $arg=false) {
		$qry = "SELECT * FROM data WHERE data_type=:type".($arg ? " AND $arg" : '').' ORDER BY RAND() LIMIT 1';
		$stmt = $this->prepare($qry);
		$stmt->bindParam(':type', $type);
		if($stmt->execute()) {
			return $stmt->fetch(PDO::FETCH_ASSOC);
		}
		return false;
	}

	public function updateConfig($name, $value) {
		$qry = "UPDATE `config` SET `value` = :value WHERE `name`=:name";
		$stmt = $this->prepare($qry);
		$stmt->bindParam(':value', $value);
		$stmt->bindParam(':name', $name);
		$stmt->execute();
	}

	public function addConfig($name, $value) {
		$qry = "INSERT INTO `config` (`name`, `value`) VALUES (:name, :value)";
		$stmt = $this->prepare($qry);
		$stmt->bindParam(':value', $value);
		$stmt->bindParam(':name', $name);
		$stmt->execute();
	}

}

