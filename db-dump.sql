-- Adminer 3.3.1 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `repo`;
CREATE TABLE `repo` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `subdir` varchar(255) NOT NULL,
  `dir` varchar(255) NOT NULL,
  `added` datetime NOT NULL,
  `lastPull` datetime default NULL,
  `lastGenerated` datetime default NULL,
  `error` tinyint(4) default NULL,
  `branch` varchar(255) default NULL,
  `apigenResultId` int(11) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `dir` (`dir`),
  UNIQUE KEY `url` (`url`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `result`;
CREATE TABLE `result` (
  `id` int(11) NOT NULL auto_increment,
  `repo_id` int(11) NOT NULL,
  `cmd` varchar(255) NOT NULL,
  `ok` tinyint(1) NOT NULL,
  `output` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL auto_increment,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1,	'juzna',	'ed97b376f2a0804df9d3d99de3bacb0b',	'admin');
