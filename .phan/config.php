<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: __DIR__ . '/../../..';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		$IP . '/extensions/LDAPAuthentication2',
		$IP . '/extensions/PluggableAuth',
		$IP . '/extensions/Renameuser',
	]
);

$cfg['exclude_file_list'][] = $IP . '/extensions/Renameuser/RenameuserSQL.php';

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		$IP . '/extensions/LDAPAuthentication2',
		$IP . '/extensions/PluggableAuth',
		$IP . '/extensions/Renameuser',
	]
);

$cfg['target_php_version'] = "7.3";

return $cfg;
