<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* IRC Message class file
*
* $Id: EbotMessage.php 14 2010-11-26 16:39:52Z tmichelbrink $
*
*/

class EbotMessage {

	private $data;

	public function __construct() {
		$this->clear();
	}

	public function clear() {
		$this->data = array();
	}

	public function __get($name) {
		if(array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		return null;
	}

	public function __set($name, $value) {
		return $this->data[$name] = $value;
	}

	public function getData() {
		return $this->data;
	}

	public function parseMessage($message, $trigger="!", $botnick="") {
		$this->clear();
		$this->data['trigger'] = '';
		$this->data['raw'] = $message;
		//Check to see if message came in that we know what do with
		if(preg_match("/^:(.*?)!(.*?)\s(.*?)\s(.*?)\s:(.*?)$/", $message, $results)) {
			$this->data['nick'] = $results[1];
			$this->data['hostmask'] = $results[2];
			$this->data['command'] = $results[3];
			$this->data['channel'] = $results[4];
			$this->data['contents'] = trim(chop($results[5]));
			if($botnick == $this->channel) {
				$this->data['returnPath'] = $this->nick;
			} else {
				$this->data['returnPath'] = $this->channel;
			}
		} elseif(preg_match("/^:(.*?)!(.*?)\s(.*?)\s(.*?)$/", $message, $results)) {
			$this->data['nick'] = $results[1];
			$this->data['hostmask'] = $results[2];
			$this->data['command'] = $results[3];
			$this->data['channel'] = $results[4];
		} elseif(preg_match("/^:(.*?)\s(\d+)\s(.*?)\s(.*?):(.*?)$/", $message, $results)) {
			$this->data['server'] = $results[1];
			$this->data['cmdnum'] = $results[2];
			$this->data['channel'] = $results[3];
			$this->data['info'] = $results[4];
			$this->data['contents'] = $results[5];
		} else {
			preg_match("/^:(.*?)\s(.*?)\s".$botnick."\s:(.*?)$/", $message, $results);
			if(isset($results[2])) {
				$this->data['command'] = $results[2];
			}
			if(isset($results[3])) {
				$this->data['contents'] = trim(chop($results[3]));
			}
		}

		if(preg_match("#^".$botnick."[:,] #", $this->contents)) {
			//Message is directed towards bot with <botnick>:
			$this->data['contents'] = substr($this->contents, strlen($botnick)+2);
			$this->data['trigger'] = 'botnick';
		} elseif(substr($this->contents, 0, 1) == $trigger) {
			// A command has been entered with trigger as first character
			if(strstr($this->contents, ' ')) {
				// Command has been entered with some parms
				$tmp = explode(' ', $this->contents);
				$this->data['trigger'] = trim(substr($tmp[0], 1));
				unset($tmp[0]);
				$this->data['params'] = implode(' ', $tmp);
				$this->parseParms($this->data['params']);
			} else {
				// Parmless trigger
				$this->data['trigger'] = substr($this->contents, 1);
				$this->data['params'] = '';
			}
		}

	}

	public function parseParms($params) {
		$this->data['parm'] = array();
		$tmp = explode(' ', $params);
		foreach($tmp as $parm) {
			$parm = trim($parm);
			$this->data['parm'][] = $parm;
		}
	}

}