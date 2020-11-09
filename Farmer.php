<?php
/**
 * This file contains classes and functions for MediaWiki farmer, a tool to help
 * manage a MediaWiki farm
 *
 * @author Gregory Szorc <gregory.szorc@gmail.com>
 * @license GPL-2.0-or-later
 *
 * @todo Extension management on per-wiki basis
 * @todo Upload prefix
 *
 */

$wgExtensionCredits['specialpage'][] = [
	'path' => __FILE__,
	'name' => 'Farmer',
	'author' => [ 'Gregory Szorc <gregory.szorc@case.edu>', 'Alexandre Emsenhuber' ],
	'url' => 'https://www.mediawiki.org/wiki/Extension:Farmer',
	'descriptionmsg' => 'farmer-desc',
	'version' => '0.1.2',
	'license-name' => 'GPL-2.0-or-later',
];

/**
 * Extension's configuration
 */
$wgFarmerSettings = [
	// Path to the directory that holds settings for wikis
	'configDirectory'           => __DIR__ . '/configs/',

	// Or use a database
	'databaseName'              => null,

	// Default wiki
	'defaultWiki'               => '',

	// Function used to identify the wiki to use
	'wikiIdentifierFunction'    => [ 'MediaWikiFarmer', 'matchByURLHostname' ],
	'matchRegExp'               => '',
	'matchOffset'               => null,
	'matchServerNameSuffix'     => '',

	// Function to call for unknown wiki
	'onUnknownWiki'             => [ 'MediaWikiFarmer', 'redirectTo' ],
	// If onUnknownWiki calls MediaWikiFarmer::redirectTo (default), url to redirect to
	'redirectToURL'             => '',

	// Whether to use $wgConf to get some settings
	'useWgConf'                 => true,

	// Callback function that is called when a wiki is initialized and will
	// recieve the MediaWikiFarmer_Wiki object in first parameter
	'initCallback'              => null,

	// File used to create tables for new wikis
	'newDbSourceFile'           => "$IP/maintenance/tables.sql",

	// Get DB name and table prefix for a wiki
	'dbFromWikiFunction'        => [ 'MediaWikiFarmer', 'prefixTable' ],
	'dbTablePrefixSeparator'    => '',
	'dbTablePrefix'             => '',

	// user name and password for MySQL admin user
	'dbAdminUser'               => 'root',
	'dbAdminPassword'           => '',

	// Per-wiki image storage, filesystem path
	'perWikiStorageRoot'        => '/images/',
	// Url
	'perWikiStorageUrl'         => '/images/',

	// default skin
	'defaultSkin'               => 'vector',
];

$wgMessagesDirs['MediaWikiFarmer'] = __DIR__ . '/i18n';

$wgExtensionMessagesFiles['MediaWikiFarmerAlias'] = __DIR__ . '/Farmer.alias.php';

$wgAutoloadClasses['FarmerUpdaterHooks'] = __DIR__ . '/FarmerUpdater.hooks.php';
$wgAutoloadClasses['MediaWikiFarmer'] = __DIR__ . '/MediaWikiFarmer.php';
$wgAutoloadClasses['MediaWikiFarmer_Extension'] = __DIR__ . '/MediaWikiFarmer_Extension.php';
$wgAutoloadClasses['MediaWikiFarmer_Wiki'] = __DIR__ . '/MediaWikiFarmer_Wiki.php';
$wgAutoloadClasses['SpecialFarmer'] = __DIR__ . '/SpecialFarmer.php';

$wgSpecialPages['Farmer'] = 'SpecialFarmer';

# New permissions
$wgAvailableRights[] = 'farmeradmin';
$wgAvailableRights[] = 'createwiki';

$wgGroupPermissions['*']['farmeradmin'] = false;
$wgGroupPermissions['sysop']['farmeradmin'] = true;
$wgGroupPermissions['*']['createwiki'] = false;
$wgGroupPermissions['sysop']['createwiki'] = true;

# New log
$wgLogTypes[] = 'farmer';
$wgLogNames['farmer'] = 'farmer-log-name';
$wgLogHeaders['farmer'] = 'farmer-log-header';
$wgLogActions['farmer/create'] = 'farmer-log-create';
$wgLogActions['farmer/delete'] = 'farmer-log-delete';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'FarmerUpdaterHooks::addSchemaUpdates';
