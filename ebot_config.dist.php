<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Configuration file
*
* $Id: ebot_config.dist.php 5 2010-11-24 14:47:46Z tmichelbrink $
*
*/

class dbConfig {
	public $mySQLserver;
	public $mySQLuser;
	public $mySQLpassword;
	public $mySQLdb;


	public function __construct() {
		$this->mySQLserver = "localhost";
		$this->mySQLuser = "my_username";
		$this->mySQLpassword = "my_password";
		$this->mySQLdb = "ebot";
	}
}
