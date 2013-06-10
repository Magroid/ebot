<?php
/*
* This file is part of the ebot PHP bot
*
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Configuration class file
*
* $Id: EbotConfig.php 5 2010-11-24 14:47:46Z tmichelbrink $
*
*/

class EbotConfig {

	private $data;

	public function __construct() {
		$this->clear();
	}

	public function clear() {
		$this->data = array();
	}

	public function __set($name, $value) {
		$this->data[$name] = $value;
	}

	public function __get($name) {
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		return null;
	}
}