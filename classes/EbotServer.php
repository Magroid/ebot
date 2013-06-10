<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* IRC Server class file
*
* $Id: EbotServer.php 5 2010-11-24 14:47:46Z tmichelbrink $
*
*/

class EbotServer {

	private $server='';
	private $port='6667';
	private $useSockets=true;

	private $socket;
	private $status;
	private $lastError;

	const MSGTYPE_NORMAL = 0;
	const MSGTYPE_PRIVMSG = 1;
	const MSGTYPE_NOTICE = 2;
	const MSGTYPE_CMD = 3;
	const MSGTYPE_ACTION = 4;


	public function __construct($server='', $port='', $useSockets=true) {
		$this->setServer($server);
		$this->setPort($port);
		$this->setSockets($useSockets);
	}

	public function setServer($server='') {
		if(!empty($server)) { $this->server = $server; }
	}

	public function setPort($port='') {
		if(!empty($port)) { $this->port = $port; }
	}

	public function setSockets($useSockets='') {
		if(!empty($useSockets)) {
			$this->useSockets = true;
		} else {
			$this->useSockets = false;
		}
	}

	public function getLastError() {
		return $this->lastError;
	}

	public function connect() {
		if ($this->useSockets == true) {
			$this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			$result = socket_connect($this->socket, $this->server, $this->port);
		} else {
			$this->socket = @fsockopen($this->server, $this->port, $errno, $errstr);
			socket_set_blocking($this->socket, false);
		}

		if ($this->socket === false) {
			if ($this->useSockets == true) {
				$error = socket_strerror(socket_last_error($this->socket));
			} else {
				$error = $errstr." (".$errno.")";
			}
			throw new Exception("Unable to connect to '".$this->server."' on port ".$this->port." - error reported as '$error'.", E_USER_ERROR);
			return false;
		}
		return true;
	}

	public function read() {
		if ($this->useSockets == true) {
			$sread = array($this->socket);
			$result = @socket_select($sread, $w = null, $e = null, 0, null);
				if($result == 1) {
				$rawdata = @socket_read($this->socket, 10240, PHP_NORMAL_READ);
			} else if ($result === false) {
				$this->lastError = "socket_select() returned false. Error returned as '".socket_strerror(socket_last_error())."'. Script halted";
				return false;
			} else {
				$rawdata = '';
			}
		} else {
			usleep(100000);
			$rawdata = @fgets($this->socket, 10240);
		}
		return $rawdata;
	}


	public function write($string, $type=self::MSGTYPE_NORMAL, $target='' ) {
		if(!$this->socket) {
			throw new Exception('No socket resource. Script halted.');
			exit;
		}
		$prefix = '';
		$postfix = '';
		switch($type) {
			case self::MSGTYPE_PRIVMSG:
				$prefix = 'PRIVMSG '.$target.' :';
				break;

			case self::MSGTYPE_NOTICE:
				$prefix = 'NOTICE '.$target.' :';
				break;

			case self::MSGTYPE_CMD:
				$prefix = '';
				break;

			case self::MSGTYPE_ACTION:
				$prefix = "PRIVMSG {$target} :".chr(1).'ACTION ';
				$postfix = chr(1);
				break;

			default:
				$prefix = $target.' :';
				break;

		}
		$message = $prefix.trim($string).$postfix."\r\n";

		if ($this->useSockets == true) {
			$result = @socket_write($this->socket, $message);
		} else {
			$result = @fwrite($this->socket, $message);
		}

		if ($result === false) {
			trigger_error("Send failed. String: ".$string, E_USER_WARNING);
			return false;
		}
		return $message;
	}

	public function getState() {
		$type = get_resource_type($this->socket);
		if ((is_resource($this->socket)) && ($this->socket !== false) && ($type == "socket" || $type == "Socket" || $type == "stream")) {
			$this->status = true;
		} else {
			$this->status = false;
		}
		return $this->status;
	}


	public function reconnect() {
		$this->disconnect();
		return $this->connect();
	}

	public function disconnect() {
		if ($this->useSockets == true) {
			@socket_shutdown($this->socket);
			@socket_close($this->socket);
		} else {
			fclose($this->socket);
		}
	}



}