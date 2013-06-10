<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Main class file
*
* $Id: Ebot.php 32 2011-01-29 22:01:30Z tmichelbrink $
*
*/

class Ebot {

	/*
	 * Time bot was started
	 */
	private $startTime;

	/*
	 * Connection to the database
	 */
	private $db;

	/*
	 * ebot configuration as retrieved from db
	 */
	private $config;

	/*
	 * Server connection
	 */
	private $server;

	/*
	 * Array of channel info
	 */
	private $channels;

	/*
	 * EbotMessage class instance
	 */
	private $message;

	/*
	 * Extra message (created by other modules)
	 */
	private $extraMessages = array();

	/*
	 * Array to be used by modules to store persistent data
	 */
	private $static = array();

	/*
	 * Array of cached modules (if cacheModules is true)
	 */
	private $modules = array();


	public static function getInstance() {
		if(null == self::$_instance) {
		    self::$_instance = new self();
		}
	  	return self::$_instance;
	}

	public function __construct($dbConfig) {
		$this->startTime = time();
		$this->db = new EbotPDO($dbConfig);
		$this->config = new EbotConfig();
		$this->loadConfig();

	}

	private function loadConfig() {
		$this->config->clear();
		$rs = $this->db->getRecordSet("SELECT * FROM `config`");
		foreach($rs as $rec) {
			$this->config->$rec['name'] = $rec['value'];
		}
		if(!isset($this->config->port)) {
			$this->config->port = '6667';
		}
	}

	public function startUp() {
		$this->server = new EbotServer($this->config->server, $this->config->port, $this->config->usesockets);
		$this->message = new EbotMessage();
		if($this->server->connect()) {
			$this->console("Connection successfully made to server '".$this->config->server."' on port ".$this->config->port.".");
			$this->initializeBot();
		} else {
			die('Unable to connect to server');
		}
	}

	private function initializeBot() {
		$this->send("nick ".$this->config->botnick, EbotServer::MSGTYPE_CMD);
		$this->send("user ".$this->config->botnick . " " . $this->config->server . " " . $this->config->server . " " . $this->config->version, EbotServer::MSGTYPE_CMD);
		if($this->config->ident) {
			$this->send("IDENTIFY ".$this->config->ident, ebotServer::MSGTYPE_PRIVMSG, 'nickserv');
		}
		$this->channels = array();
		$this->joinChannels();
		$this->mainLoop();
	}

	private function recoverNick() {
		$this->send("nick _".$this->config->botnick."_", EbotServer::MSGTYPE_CMD);
		$this->send("user _".$this->config->botnick."_ ". $this->config->server . " " . $this->config->server . " " . $this->config->version, EbotServer::MSGTYPE_CMD);
		if($this->config->ident) {
			$this->send("GHOST ".$this->config->botnick." ".$this->config->ident, ebotServer::MSGTYPE_PRIVMSG, 'nickserv');
		}
	}

	private function shutdown() {
		$this->server->disconnect();
		$this->db->disconnect();
		sleep(3);
		exit;
	}

	private function mainLoop() {
		$lastCheck = 0;
		$moduleCheckLast = 0;
		$moduleCheckInterval = 30;  //Run module trigger every 30 seconds
		$lastMessageTime = time();
		$keepAlive = (int)$this->config->keepalive;
		$nextKeepalive = 0;

		while(1) {
			usleep(2000);
			//If keepalive is configured, and time for next command...send it
			if($keepAlive > 0 && time() > $nextKeepalive) {
				$this->send('TIME');
				$nextKeepalive = time() + $keepAlive;
			}

			//Check for possible socket timeouts every 10 secs
			if(time() >= $lastCheck + 10) {
				if($lastMessageTime + 3000 < time()) {
					$this->restartConnection('Possible socket timeout, restarting connection');
				}
				if(!$this->server->getState()) {
					/* reconnect to server and rejoin channels if disconnected */
					$this->restartConnection('Lost Socket');
				}
//				$this->update_record_uptime();
				$lastCheck = time();
			}

			//Run any 'module' triggers every 10 $moduleCheckInterval
			if($moduleCheckInterval && time() > $moduleCheckLast + $moduleCheckInterval) {
				$this->message->clear();
				$this->message->trigger = '_timed_';
				$this->executeTrigger();
				$moduleCheckLast = time();
			}

			$rawdata = $this->server->read();
			if($rawdata === false) {
				$this->restartConnection('Socket Read Error Detected!');
			}

			if(trim($rawdata)) {

				$lastMessageTime = time();
				if($this->config->msgDebug && trim($rawdata)) {
					$this->console("<-- ".trim($rawdata), 'socket');
				}
				if($this->config->log) {
//					$this->db->_Query("INSERT INTO log VALUES ('0', '".time()."', '".mysql_escape_string($rawdata)."')");
				}

				$this->message->parseMessage($rawdata, $this->config->trigger, $this->config->botnick);

				if($this->message->command == '433 *') {
					if($this->message->contents == 'Nickname is already in use.') {
						$this->recoverNick();
						$this->initializeBot();
					}
				}

				if($this->message->trigger) {
					$this->executeTrigger();
				}

				if($this->message->command == 'PRIVMSG') {
					// Special command has been sent, let's handle that separately
					if(!$this->processSpecial()) {
						$this->message->trigger = 'alllines';
						$this->executeTrigger();
					}
				} else {
					// If not a special command, let's fire the 'alllines' trigger
					$this->message->trigger = 'alllines';
					$this->executeTrigger();
				}

				if(preg_match('/PING (.*)/', $rawdata, $results)) {
					$this->send('PONG '.$results[1], EbotServer::MSGTYPE_CMD);
				}
			}
			while(count($this->extraMessages) > 0 ) {
				$this->message = array_pop($this->extraMessages);
				$this->executeTrigger(true);
			}
			unset($rawdata);
		}
	}

	function processSpecial() {
		switch($this->message->contents) {
			case chr(1).'VERSION'.chr(1):
				$this->send($this->config->version, EbotServer::MSGTYPE_NOTICE, $this->message->nick);
				return true;
				break;
		}
		return false;
	}

	function joinChannels() {
		$tmp = explode(' ', $this->config->channel);
		if($this->server->getState()) {
			foreach($tmp as $channel) {
				if(substr($channel, 0, 1) != '#') {
					$channel = '#'.$channel;
				}
				$this->console("Attempting to join channel: $channel.");
				$this->send('JOIN '.$channel, EbotServer::MSGTYPE_CMD);
				$this->channels[$channel] = array();
			}
		}
		unset($tmp);
	}

	private function executeTrigger($internalTrigger = false) {
		// Get the trigger word
		$trigger = preg_replace('#\W#', '', $this->message->trigger);

		//If user entered a trigger beginning with _ - return.
		if(substr($trigger, 0, 1) == '_' && !$internalTrigger) { return false; }


		// If trigger is empty, why are we here? - return
		if($trigger == '') { return false; }

		// If trigger != sleep and command came from a channel
		if('sleep' != $trigger && '#' == substr($this->message->returnPath, 0, 1) ) {
			if($this->isSleeping($this->message->returnPath)) {
				return false;
			}
		}

		//Set isPM variable, to show if command came from /msg
		$this->isPM = ("#" == substr($this->message->returnPath, 0, 1) ? false : true);

		//If command was directed to specific user (with > user_name) command, set new return target
		if(preg_match('#^(> *(.+?))\b#', $this->message->params, $matches)) {
			$this->message->returnPath = $matches[2];
			$this->message->params = str_replace($matches[1], '', $this->message->params);
		}

		//Find all modules that are set to trigger on this trigger phrase
		$regex = "(^| )(".$trigger.")( |$)";
		$qry = "SELECT name, priv, code FROM modules WHERE (triggers REGEXP '{$regex}' OR name = '{$this->message->trigger}') AND active = 1";

		if(($moduleList = $this->db->getRecordSet($qry))) {
			foreach($moduleList as $module) {
				$allowed = true;
				// If entire module has a class restriction, check it.
				if($module['priv'] != '') {
					$allowed = $this->checkPriv($module['priv']);
				}

				// If specific trigger has a class restriction, check it.
				$var = 'triggerclass_'.$trigger;
				if($this->config->$var && $allowed = true) {
					$allowed = $this->checkPriv($this->config->$var);
				}

				//If user is allowed to execute trigger, do so.
				if($allowed) {
					$xml = $this->loadModule($module['name']);
					if(isset($xml->triggers->{$trigger})) {
						eval($xml->triggers->{$trigger});
					}
				}
			}
		} else {
			if($trigger != 'alllines' && $trigger != 'module') {
				$this->reply("Unknown command: {$trigger}");
			}
		}
	}


	public function reply($message, $target='', $type='') {
		$lines = explode("\n", $message);
		$target = ($target == '' ? $this->message->returnPath : $target);
		$type = ($type == '' ? EbotServer::MSGTYPE_PRIVMSG : $type);

		if(is_array($lines)) {
			foreach($lines as $line) {
				$ret = $this->server->write($line, $type, $target);
				$this->console('--> '.$ret, 'socket');
				usleep(500000);
			}
		} else {
			$ret = $this->server->write($message, $type, $target);
			$this->console('--> '.$ret, 'socket');
		}
	}

	public function send($message, $type=ebotServer::MSGTYPE_NORMAL, $target='') {
		$ret = $this->server->write($message, $type, $target);
		if($this->config->msgDebug) {
			$this->console('--> '.$ret, 'socket');
		}
		return $ret;
	}

	public function console($message='', $type='') {
		$var = 'log_'.$type;
		if($this->config->$var || $this->config->log_all) {
			$message = trim($message);
			if($message) { echo "$message\n"; }
			flush();
		}
	}

	private function restartConnection($reason='Unknown') {
		$this->console('Restarting server connect: '.$reason);
		if($this->server->reconnect()) {
			$this->initializeBot();
		} else {
			die('Reconnection failed');
		}
	}

	public function setConfig($name, $value) {
		if($this->db->getRecord("SELECT * FROM `config` WHERE `name` = '$name'")) {
			echo "Updating config\n";
			$this->db->updateConfig($name, $value);
		} else {
			$this->db->addConfig($name, $value);
			echo "Inserting config\n";
		}
		$this->loadConfig();
	}

	private function isSleeping($channel) {
		$sleeping = clean($this->channels[$channel], 'sleeping');
		if(true === $sleeping) {
			return true;
		}

		//If sleeping for specific amount of time
		if(is_numeric($sleeping)) {
			//If sleep time expired, set sleeping to false
			if(time() >  $sleeping) {
				$this->console('Sleep timer expired - setting to false', 'ebot');
				$this->channels[$channel]['sleeping'] = false;
				return false;
			}
			return (int)$sleeping;
		}
	}

	public function setSleeping($channel, $length) {
		if(is_numeric($length)) {
			$length = time() + ($length * 60);
		}
		$this->channels[$channel]['sleeping'] = $length;
	}


	/*
	 * Check to see of a nick is in a specific class
	 *
	 * @return boolean
	 */
	private function inClass($class, $nick='') {
		if($nick == '') { $nick = $this->message->nick; }
		$var = strtolower('class_'.$class);
		if($this->config->$var) {
			$tmp = explode(',', $this->config->$var);
			foreach($tmp as $var) {
				if(strtolower($nick) == strtolower(trim($var))) {
					unset($var);
					unset($tmp);
					return true;
				}
			}
		}
		unset($var);
		return false;
	}

	/*
	 * Check to see if user entering command is in a specific class
	 *
	 * @return boolean
	 */
	private function checkPriv($class) {
		print_r($this->message);
		if($this->inClass($class)) {
			$var = 'mask_'.strtolower($this->message->nick);
			if($this->message->hostmask == $this->config->$var) {
				unset($var);
				return true;
			}
			unset($var);
		}

		$this->reply('You are not authorized to use this command');
		return false;
	}

	public function format_time($s) {
		$d = intval($s/86400);
		$s -= $d*86400;
		$h = intval($s/3600);
		$s -= $h*3600;
		$m = intval($s/60);
		$s -= $m*60;
		$day = ($d == 1 ? 'day' : 'days');
		$hour = ($h == 1 ? 'hour' : 'hours');
		$min = ($m == 1 ? 'minute' : 'minutes');
		$sec = ($s == 1 ? 'second' : 'seconds');
		return ($d ? "$d {$day} " : '').($h ? "$h {$hour} " : '').($m ? "$m {$min} " : '')."$s {$sec}";
	}

	/*
	 * Read random line from file
	 */
	public function getLine($fname) {
		if($lines = file($fname)) {
			return $lines[rand(0, count($lines)-1)];
		}
		return false;
	}

	public function cache_set($module, $tag, $data) {
		$cache_file = "modules/cache/{$module}_".md5($tag).'.cache.php';
		file_put_contents($cache_file, '<?php'.$data);
		@chmod($cache_file, 0777);
		@touch($cache_file);
	}

	public function cache_get($module, $tag, $maxAge = false) {
		$cache_file = "modules/cache/{$module}_".md5($tag).'.cache.php';
		if (file_exists($cache_file)) {
			if ($maxAge !== false && (filemtime($cache_file) + ($maxAge * 60)) < time()) {
				$this->console('Cache file expired', 'cache');
				unlink($cache_file);
				return false;
			} else {
				$ret = file_get_contents($cache_file);
				$ret = substr($ret, 5);
				return $ret;
			}
		} else {
			$this->console('Cache file not found', 'cache');
			return false;
		}
	}

	/*
	 * Get source html from a url, parsing is needed
	 */
	public function getHtml($requestUrl, $start_html='', $end_html='', $mode = 'verbose') {
		$url = parse_url($requestUrl);
		if(!$remote = fsockopen ($url['host'], 80 ,$errno, $errstr, 10)) {
			return false;
		}
		fclose ($remote);
		$fullHtml = file_get_contents($requestUrl);
		if($mode == 'all') {
			return ($fullHtml);
		}
		if(is_array($start_html)) {
			reset ($start_html);
			while(list($key, $val) = each($start_html)) {
				preg_match("/{$val}(.*?){$end_html[$key]}/si", $fullHtml, $matches);
				$result[$key] = ($mode == 'verbose' ? strip_tags($matches[1]) : strip_tags(substr($matches[1], 0, strpos($matches[1], "\n"))).' ...');
				if($result[$key] == ' ...'){ $result[$key] = $matches[1]; }
			}
		} else {
			preg_match("/{$start_html}(.*?){$end_html}/si", $fullHtml, $result);
		}
		return $result;
	}

	/*
	 * Insert a record into the `data` table
	 */
	public function dataInsert($type, $data, $data2=null, $int=null) {
		$qry = '
		INSERT INTO data
			(data_id, data_type, data_nick, data_hostmask, data_channel, data_datestamp, data_text, data_int, data_text2)
		VALUES
			(0, :type, :nick, :mask, :channel, :datestamp, :text, :int, :text2)
		';
		$nick = $this->message->nick;
		$mask = $this->message->hostmask;
		$channel = $this->message->channel;
		$time = time();
		$stmt = $this->db->prepare($qry);
		$stmt->bindParam(':type', $type);
		$stmt->bindParam(':nick', $nick);
		$stmt->bindParam(':mask', $mask);
		$stmt->bindParam(':channel', $channel);
		$stmt->bindParam(':datestamp', $time);
		$stmt->bindParam(':text', $data);
		$stmt->bindParam(':int', $int);
		$stmt->bindParam(':text2', $data2);
		if($stmt->execute()) {
			return $this->db->lastInsertId();
		}
		return false;
	}

	/*
	 * Insert a record into the `data` table
	 */
	public function dataUpdate($id, $type, $data, $data2=null, $int=null) {
		$qry = '
		UPDATE data SET
			data_type = :type,
			data_nick = :nick,
			data_hostmask = :mask,
			data_channel = :channel,
			data_datestamp = :datestamp,
			data_text = :text,
			data_int = :int,
			data_text2 = :data2
		WHERE
			data_id = :id
		';

		$nick = $this->message->nick;
		$mask = $this->message->hostmask;
		$channel = $this->message->channel;
		$time = time();
		$stmt = $this->db->prepare($qry);
		$stmt->bindParam(':id', $id);
		$stmt->bindParam(':type', $type);
		$stmt->bindParam(':nick', $nick);
		$stmt->bindParam(':mask', $mask);
		$stmt->bindParam(':channel', $channel);
		$stmt->bindParam(':datestamp', $time);
		$stmt->bindParam(':text', $data);
		$stmt->bindParam(':int', $int);
		$stmt->bindParam(':data2', $data2);
		if($stmt->execute()) {
			return $this->db->lastInsertId();
		}
		return false;
	}

	public function dataDelete() {

	}

	/*
	 * Add message to the extraMessage stack.
	 * Will be processed as if it came from server
	 */
	public function addMessage($trigger, $params='', $contents='') {
		$message = new EbotMessage();
		$old = $this->message->getData();
		foreach($old as $key => $val) {
			$message->$key = $val;
		}
		$message->trigger = $trigger;
		$message->contents = $contents;
		$message->params = $params;
		$message->parseParms($params);
		$this->extraMessages[] = $message;
	}

	/*
	 * Check to see if command was entered in a channel (not PM)
	 *
	 * @return boolean
	 */
	public function inChannel() {
		return substr($this->message->returnPath, 0, 1) == '#';
	}

	/*
	 * Load a module file from disk
	 *
	 * @parm string
	 * @return mixed
	 */
	private function loadModule($module, $force=false) {
		$moduleFile = "modules/{$module}_mod.xml";
		if($this->config->cacheModule != true) { $this->modules = array(); }
		if(!$force && $this->config->cacheModules == true && isset($this->modules[$module])) {
			$this->console("Using module cache: $moduleFile", 'debug');
			return $this->modules[$module];
		}
		$this->console("Loading module file: $moduleFile", 'debug');
		if(is_readable($moduleFile)) {
			 if($xml = simplexml_load_file($moduleFile, 'SimpleXMLElement', LIBXML_NOCDATA)) {
			 	if($this->config->cacheModules == true) {
			 		$this->modules[$module] = $xml;
			 	}
			 	return $xml;
			} else {
				$this->reply("Error: Trigger not found in module file");
			}
		} else {
			$this->reply("Error: Unable to read needed module file {$moduleFile}");
		}
		return false;
	}

	private function getModuleInfo($module, $remote=false) {
		$ret = array();
		if(!$remote) {
			$info = $this->loadmodule($this->message->parm[0], true);
		} else {
			$path = "http://phpebot.svn.sourceforge.net/viewvc/phpebot/modules/";
			echo "loading module info from: $path \n";
			$moduleSource = file_get_contents($path);
			if(preg_match("#<a href=.*?{$module}_mod\.xml.*?revision=(\d+).*?/a>#", $moduleSource, $matches)) {
				$ret['svn_rev'] = $matches[1];
				return $ret;
			}
		}
		if($info) {
			$info = (array)$info;
			$array = (array)$info['info'];
			foreach($array['@attributes'] as $key => $value) {
				$ret[$key] = $value;
			}
		}
		$ret['descr'] = clean($info, 'descr');
		$ret['priv'] = clean($info, 'priv');

		if(clean($info, 'revision')) {
			if(preg_match("#LastChangedRevision: (\d+) #", $info['revision'], $matches)) {
				$ret['revision'] = (int)trim($matches[1]);
			}
		}
		if(clean($info, 'svn')) {
			if(preg_match("#Id: (.*?) (\d+) (.*?) (.*?) (.*?) #", $info['svn'], $matches)) {
				$ret['file'] = trim($matches[1]);
				$ret['rev'] = trim($matches[2]);
				$ret['date'] = trim($matches[3]);
				$ret['time'] = trim($matches[4]);
				$ret['author'] = trim($matches[5]);
			}
		}

		$triggers = (array)$info['triggers'];
		$ret['triggers'] = implode(', ', array_keys($triggers));
		unset($array);
		unset($triggers);
		unset($info);
		return $ret;
	}

}

