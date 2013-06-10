<?php
class EbotInstall {

	private $configFile;
	private $config = array();
	private $dbh;

	public function install($configFile) {
		$this->configFile = $configFile;
		$input = $this->getInput("\n\nConfiguration file '{$configFile}' was not found, would you like to create a new configuration", 'Y');
		if(!$this->isTrue($input)) {
			die();
		}

		if(!file_put_contents($configFile, 'php_ebot config')) {
			die("Unable to create file: {$configFile}\n");
		}
		unlink($configFile);
		$this->installDb();
		$this->installConfig();
		if($this->writeConfig()) {
			echo "ebot configuration successfully created\n";
		} else {
			echo "ERROR: There was an error creating the ebot configuration file\n";
		}
	}


	private function installDb() {
		$items = array(
			array('Enter mysql Host', 'dbHost', 'localhost'),
			array('Enter mysql username', 'dbUser', ''),
			array('Enter mysql password', 'dbPass', ''),
			array('Enter mysql database', 'dbDb', 'ebot'),
			);
		foreach($items as $item) {
			$this->getConfig($item[0], $item[1], $item[2]);
		}
		try {
//    		$dbh = new PDO("mysql:dbname={$this->config['dbDb']}", $this->config['dbUser'], $this->config['dbPass']);
    		$this->dbh = new PDO("mysql:host={$this->config['dbHost']};dbname={$this->config['dbDb']};", $this->config['dbUser'], $this->config['dbPass']);
		} catch (PDOException $e) {
			if(preg_match('# \[(\d+)\] #', $e->getMessage(), $matches)) {
				$error = $matches[1];
				if($error == 1049) {
					$input = $this->getInput("\nDatabase does not exist, create it?", 'Y');
					if($this->isTrue($input)) {
    					$this->dbh = new PDO("mysql:host={$this->config['dbHost']};", $this->config['dbUser'], $this->config['dbPass']);
						$result = $this->dbh->query("CREATE DATABASE {$this->config['dbDb']}");
						if(!$result) {
							die("Unable to create database: {$this->config['dbDb']}\n");
						} else {
							unset($this->dbh);
    						$this->dbh = new PDO("mysql:host={$this->config['dbHost']};dbname={$this->config['dbDb']};", $this->config['dbUser'], $this->config['dbPass']);
						}
					} else {
						die();
					}
				} else {
					echo "The following db error occured:\n";
					echo $e->getMessage()."\n";
					$this->installDb();
				}
			} else {
				echo "An unknown error occured..exiting install\n";
				echo $e->getMessage();
				die();
			}
		}
		try {
			$schema = file_get_contents('misc/schema.sql');
			$result = $this->dbh->query($schema);
		} catch (PDOExeption $e) {
			echo $e->getMessage();
		}
		echo "Database successfully created\n";
	}

	private function installConfig() {
		$items = array(
			array('Enter IRC server', 'ebotServer', ''),
			array('Enter any channels you would like to auto join (separated by spaces)', 'ebotChan', ''),
			array('Enter bot nickname', 'ebotNick', 'php_ebot'),
			array('Enter bot identify password', 'ebotPass', 'N/A'),
			array('Enter owner (your) IRC nick', 'ebotOwner', ''),
			array('Enter owner password', 'ebotOwnerPass', ''),
			array('Enter bot trigger character', 'ebotTrigger', '~'),
			);
		foreach($items as $item) {
			$this->getConfig($item[0], $item[1], $item[2]);
		}
		$values = array();
		$values[] = "('owner', '{$this->config['ebotOwner']}')";
		$values[] = "('owner_pw', '{$this->config['ebotOwnerPass']}')";
		$values[] = "('botnick', '{$this->config['ebotNick']}')";
		if($this->config['ebotPass'] != 'N/A') {
			$values[] = "('ident', '{$this->config['ebotPass']}')";
		}
		$values[] = "('trigger', '{$this->config['ebotTrigger']}')";
		$values[] = "('server', '{$this->config['ebotServer']}')";
		$values[] = "('channel', '{$this->config['ebotChan']}')";
		$values[] = "('usesockets', '1')";

		$qry = "
			INSERT INTO `config` (`name`, `value`)
			VALUES
			".implode(',', $values);
		try {
			$this->dbh->query($qry);
		} catch (PDOExeption $e) {
			echo $e->getMessage();
		}
	}

	private function getConfig($prompt, $key, $default='') {
		$input = '';
		while($input == '') {
			$input = $this->getInput($prompt, $default);
		}
		$this->config[$key] = $input;
	}

	private function getInput($prompt, $default='') {
		echo "$prompt [{$default}]: ";
		$h = fopen ('php://stdin','r');
		$line = fgets($h);
		$line = trim($line);
		if($line == '' && $default) {
			return $default;
		}
		return $line;
	}

	private function isTrue($input) {
		$input = strtoupper($input);
		if($input == 'Y' || $input == 'YES') {
			return true;
		} else {
			return false;
		}
	}

	private function writeConfig() {
$contents = "
<?php
class dbConfig {
        public \$mySQLserver;
        public \$mySQLuser;
        public \$mySQLpassword;
        public \$mySQLdb;


        public function __construct() {
                \$this->mySQLserver = \"{$this->config['dbHost']}\";
                \$this->mySQLuser = \"{$this->config['dbUser']}\";
                \$this->mySQLpassword = \"{$this->config['dbPass']}\";
                \$this->mySQLdb = \"{$this->config['dbDb']}\";
        }
}
";
		return file_put_contents($this->configFile, $contents);
	}

}