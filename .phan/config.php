<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: __DIR__ . '/../../..';

$dependencies = [
		$IP . '/extensions/LDAPAuthentication2',
		$IP . '/extensions/LDAPProvider',
		$IP . '/extensions/PluggableAuth',
		$IP . '/extensions/Renameuser',
		$IP . '/extensions/UserMerge',
	];

$cfg['directory_list'] = array_merge( $cfg['directory_list'], $dependencies );

$cfg['exclude_file_list'][] = $IP . '/extensions/Renameuser/RenameuserSQL.php';

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], $dependencies
);

$cfg['target_php_version'] = "7.3";

return $cfg;
