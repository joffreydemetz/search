CREATE TABLE `#__search` (
  `component` VARCHAR(50) NOT NULL DEFAULT '',
  `term` VARCHAR(255) NOT NULL DEFAULT '',
  `hits` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`component`, `term`),
  KEY `component` (`component`)
);
