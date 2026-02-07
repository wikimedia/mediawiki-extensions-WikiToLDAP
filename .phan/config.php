<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$dependencies = [
	'../../extensions/LDAPAuthentication2',
	'../../extensions/LDAPProvider',
	'../../extensions/PluggableAuth',
	'../../extensions/UserMerge',
];

$cfg['directory_list'] = array_merge( $cfg['directory_list'], $dependencies );

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], $dependencies
);

$cfg['target_php_version'] = "7.3";

return $cfg;
