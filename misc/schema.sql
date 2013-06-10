--
-- Table structure for table `config`
--

CREATE TABLE IF NOT EXISTS `config` (
  `name` varchar(60) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `data`
--

CREATE TABLE IF NOT EXISTS `data` (
  `data_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data_type` varchar(20) NOT NULL DEFAULT '',
  `data_nick` varchar(20) NOT NULL DEFAULT '',
  `data_hostmask` varchar(100) NOT NULL DEFAULT '',
  `data_channel` varchar(30) NOT NULL DEFAULT '',
  `data_datestamp` int(10) unsigned NOT NULL DEFAULT '0',
  `data_text` text,
  `data_int` int(11) DEFAULT NULL,
  `data_text2` text,
  PRIMARY KEY (`data_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE IF NOT EXISTS `modules` (
  `name` varchar(100) NOT NULL,
  `triggers` varchar(250) NOT NULL,
  `author` varchar(100) NOT NULL,
  `info` varchar(200) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `priv` varchar(200) DEFAULT NULL,
  `source` varchar(200) NOT NULL,
  `datestamp` int(10) unsigned NOT NULL DEFAULT '0',
  `size` int(10) unsigned NOT NULL DEFAULT '0',
  `code` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

TRUNCATE TABLE `config`;
INSERT INTO `config` VALUES('usesockets', '1');
INSERT INTO `config` VALUES('version', 'ebot 2.0a');

