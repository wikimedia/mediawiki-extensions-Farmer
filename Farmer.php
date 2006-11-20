<?php
/**
 * This file contains classes and functions for MediaWiki farmer, a tool to help
 * manage a MediaWiki farm
 * 
 * @author Gregory Szorc <gregory.szorc@gmail.com>
 * @package MediaWiki
 * @licence GNU General Public Licence 2.0 or later
 * 
 * @todo Extension management on per-wiki basis
 * @todo Upload prefix
 * @todo Use MediaWiki messages
 * 
 */

$root = dirname(__FILE__);

require_once $root . '/MediaWikiFarmer.php';
require_once $root . '/MediaWikiFarmer_Wiki.php';
require_once $root . '/MediaWikiFarmer_Extension.php';


$wgFarmerSettings = array( 
    'configDirectory'           =>  realpath(dirname(__FILE__)) . '/configs/',
    'defaultWiki'               =>  '',
    'wikiIdentifierFunction'    =>  array('MediaWikiFarmer', '_matchByURLHostname'),
    'matchRegExp'   =>  '',
    'matchOffset'   =>  null,
    'matchServerNameSuffix'    =>   '',
    
    'onUnknownWiki'             =>  array('MediaWikiFarmer', '_redirectTo'),
    'redirectToURL'             =>  '',
    
    'dbAdminUser'               =>  'root',
    'dbAdminPassword'           =>  '',
    
    'newDbSourceFile'           =>  realpath(dirname(__FILE__)) . '/daughterwiki.sql',
    
    'dbTablePrefixSeparator'    =>  '',
    'dbTablePrefix'             =>  '',
    
    'defaultMessagesFunction'    =>  array('MediaWikiFarmer', '_getDefaultMessages'),
    
    'perWikiStorageRoot'        => '',
    'defaultSkin'               => 'monobook',
);

$wgExtensionFunctions[] = 'MediaWikiFarmer_Initialize';

/**
 * These should really go in the initialize function, but MediaWiki initializes
 * $wgUser before the extensions are initialized.  Seems like weird behavior,
 * but OK.
 */
$wgGroupPermissions['*']['farmeradmin'] = false;
$wgGroupPermissions['sysop']['farmeradmin'] = true;
$wgGroupPermissions['*']['createwiki'] = false;
$wgGroupPermissions['sysop']['createwiki'] = true;

function MediaWikiFarmer_Initialize()
{    
    $wgFarmer = MediaWikiFarmer::getInstance();
    $wgExtensionCredits = MediaWikiFarmer::getMWVariable('wgExtensionCredits');
    $wgMessageCache = MediaWikiFarmer::getMWVariable('wgMessageCache');
    
    $wgExtensionCredits['specialpage'][] = array(
        'name'=>'Farmer',
        'author'=>'Gregory Szorc <gregory.szorc@case.edu>',
        'url'=>'http://wiki.case.edu/User:Gregory.Szorc',
        'description'=>'Manage a MediaWiki farm',
        'version'=>'0.0.2'
    );
    
    $wgMessageCache->addmessages(
        array(
            'farmer'=>'Farmer',
            'farmercantcreatewikis'  => 'You are unable to create wikis because you do not have the createwikis privilege',
            'farmercreateinstructions'  => "In the form below, you will need to fill out some information about your wiki.  Her",
            'farmercreateurl'   => 'URL',
            'farmercreatesitename'  => 'Site Name',
            'farmercreatenextstep'  => 'Next Step',
            'farmernewwikimainpage' =>  "==Welcome to Your Wiki==\nIf you are reading this, your new wiki has been installed  correctly.  To customize your wiki, please visit [[Special:Farmer]].",
            'farmerwikiurl'    =>  'http://$1.myfarm',
            'farmerinterwikiurl'    => 'http://$1.myfarm/$2',
        )
    );
    
    require_once dirname(__FILE__) . '/MediaWikiFarmer_SpecialPage.php';
    
    //I would use the new method, but it didn't work for me...
    SpecialPage::addPage( new SpecialFarmer );
}
