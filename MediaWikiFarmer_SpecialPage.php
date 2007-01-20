<?php
/**
 * Created on Jul 20, 2006
 * 
 * @author Gregory Szorc <gregory.szorc@gmail.com>
 */

/**
 * 
 * @todo Move presentation text into MW messages
 */
class SpecialFarmer extends SpecialPage
{
    /**
     * Class constructor
     */
    public function __construct()
    {
        SpecialPage::SpecialPage('Farmer');
    }
    
    /**
     * Executes special page
     */
    public function execute($par)
    {
        $wgFarmer = MediaWikiFarmer::getInstance();
        $wgRequest = $wgFarmer->getMWVariable('wgRequest');
        
        $request = isset($par) ? $par : $wgRequest->getText('request');
        
        $arr = explode('/', $request);
        
        if (count($arr) && $arr[0]) {
            if ($arr[0] == 'create') {
                $this->_executeCreate($wgFarmer, isset($arr[1]) ? $arr[1] : null);
            } elseif ($arr[0] == 'manageExtensions') {
                $this->_executeManageExtensions($wgFarmer);
            } elseif ($arr[0] == 'updateList') {
                $this->_executeUpdateList($wgFarmer);
            } elseif ($arr[0] == 'list') {
                $this->_executeList($wgFarmer);
            } elseif ($arr[0] == 'admin') {
                $this->_executeAdminister($wgFarmer);
            } elseif ($arr[0] == 'delete') {
            	$this->_executeDelete($wgFarmer);
            }
        } else {
            //no parameters were given
            //display the main page
            
            $this->_executeMainPage($wgFarmer);
        }
        
        $this->setHeaders();
    }
    
    /**
     * Displays the main page
     */
    protected function _executeMainPage($wgFarmer)
    {
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        $wgUser = $wgFarmer->getMWVariable('wgUser');
        
        $wgOut->addWikiText('==About==');
        $wgOut->addWikiText('MediaWiki Farmer always you to manage a farm of MediaWiki wikis.');
        
        $wgOut->addWikiText('==List of Wikis==');
        $wgOut->addWikiText('*[[Special:Farmer/list|List]] all wikis on this site');
        
        if ($wgFarmer->getActiveWiki()->isDefaultWiki()) {
        
            if (MediaWikiFarmer::userCanCreateWiki($wgUser)) {
                $wgOut->addWikiText('==Create a Wiki==');
                $wgOut->addWikiText('*[[Special:Farmer/create|Create]] a new wiki now!');
            }
        
            //if the user is a farmer admin, give them a menu of cool admin tools
            if (MediaWikiFarmer::userIsFarmerAdmin($wgUser)) {
                $wgOut->addWikiText('==Farm Administration==');
                $wgOut->addWikiText('===Manage Extensions===');
                $wgOut->addWikiText('*[[Special:Farmer/manageExtensions|Manage]] installed extensions');
                
                $wgOut->addWikiText('===Farm List Update===');
                $wgOut->addWikiText('*[[Special:Farmer/updateList|Update]] list of wikis on this site');
                
                $wgOut->addWikiText('===Delete a Wiki===');
                $wgOut->addWikiText('*[[Special:Farmer/delete|Delete]] a wiki from the farm');
                
            }
        }
        
        $wiki = MediaWikiFarmer_Wiki::factory($wgFarmer->getActiveWiki());
        
        if (MediaWikiFarmer::userIsFarmerAdmin($wgUser) || $wiki->userIsAdmin($wgUser)) {
            $wgOut->addWikiText('==Administer this Wiki==');
            $wgOut->addWikiText('*[[Special:Farmer/admin|Administer]] changes to this wiki');
        }
        

        
    }

    /**
     * Displays form to create wiki
     */
    protected function _executeCreate($wgFarmer, $wiki)
    {
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        $wgUser = $wgFarmer->getMWVariable('wgUser');
        $wgTitle = $wgFarmer->getMWVariable('wgTitle');
        $wgRequest = $wgFarmer->getMWVariable('wgRequest');

        if (!$wgFarmer->getActiveWiki()->isDefaultWiki()) {
            $wgOut->addWikiText('==Not Available==');
            $wgOut->addWikiText('This feature is only available on the main wiki');
            return;
        }

        if (!MediaWikiFarmer::userCanCreateWiki($wgUser, $wiki)) {
            $wgOut->addWikiText(wfMsg('farmercantcreatewikis'));
            return;
        }

        //if something was POST'd
        if ($wgRequest->wasPosted()) {
            $name = MediaWikiFarmer_Wiki::sanitizeName($wgRequest->getVal('name'));
            $title = MediaWikiFarmer_Wiki::sanitizeTitle($wgRequest->getVal('wikititle'));
            $description = $wgRequest->getVal('description');

            //we create the wiki if the user pressed 'Confirm'
            if ($wgRequest->getVal('confirm') == 'Confirm') {
                $wgUser = $wgFarmer->getMWVariable('wgUser');	
            
                MediaWikiFarmer_Wiki::create($name, $title, $description, $wgUser->getName());

                $wgOut->addWikiText('==Wiki Created==');
                $wgOut->addWikiText('Your wiki has been created.  It is accessible at ' . wfMsg('farmerwikiurl', $name));
                $wgOut->addWikiText('By default, nobody has permissions on this wiki except you.  You can change the user privileges via [['.$title.':Special:Farmer|Special:Farmer]]');
                return;
            }

            if ($name && $title && $description) {

                $wiki = new MediaWikiFarmer_Wiki($name);
                
                if ($wiki->exists() || $wiki->databaseExists()) {
                    $wgOut->addWikiText('==Wiki Exists==');
                    $wgOut->addWikiText("The wiki you are attempting to create, '''$name''', already exists.  Please go back and try another name.");
                    return;
                }
                
                

                $wgOut->addWikiText('==Confirm Wiki Settings==');
                $wgOut->addWikiText('; Name : ' . $name);
                $wgOut->addWikiText('; Title : ' . $title);
                $wgOut->addWikiText('; Description : ' . $description);

                $wgOut->addWikiText("Your wiki, '''$title''', will be accessible via " . wfMsg('farmerwikiurl', $name) . ".  The project namespace will be '''$title'''.  Links to this namespace will be of the form '''<nowiki>[[$title:Page Name]]</nowiki>'''.  If this is what you want, press the '''confirm''' button below.");

                $wgOut->addHTML('
<form id="farmercreate2" method="post">
    <input type="hidden" name="name" value="'.htmlentities($name).'" />
<input type="hidden" name="wikititle" value="'.htmlentities($title).'" />
<input type="hidden" name="description" value="'.htmlentities($description).'" />
    <input type="submit" name="confirm" value="Confirm" />
</form>'

                );

                return;
                    

                
            }
        }

        if ($wiki && !$name) {
            $name = $wiki;
        }

        $wgOut->addWikiText('=Create a Wiki=');

        $wgOut->addWikiText('Use the form below to create a new wiki.');

        $wgOut->addWikiText('==Help==');
        $wgOut->addWikiText("; Wiki Name : The name of the wiki.  Contains only letters and numbers.  The wiki name will be used as part of the URL to identify your wiki.  For example, if you enter '''title''', then your wiki will be accessed via <nowiki>http://</nowiki>'''title'''.mydomain.");
        $wgOut->addWikiText('; Wiki Title : Title of the wiki.  Will be used in the title of every page on your wiki.  Will also be the project namespace and interwiki prefix.');
        $wgOut->addWikiText('; Description : Description of wiki.  This is a text description about the wiki.  This will be displayed in the wiki list.');
        
        
        $formURL = wfMsgHTML('farmercreateurl');
        $formSitename = wfMsgHTML('farmercreatesitename');
        $formNextStep = wfMsgHTML('farmercreatenextstep');

        $token = $wgUser->editToken();

        $wgOut->addHTML( "
<form id='farmercreate1' method='post' action=\"$action\">
    <table>
        <tr>
            <td align=\"right\">Username</td>
            <td align=\"left\"><b>{$wgUser->getName()}</b></td>
        </tr>
        <tr>
            <td align='right'>Wiki Name</td>
            <td align='left'><input tabindex='1' type='text' size='20' name='name' value=\"" . htmlentities($name) . "\" /></td>
    </tr>
    <tr>
        <td align='right'>Wiki Title</td>
        <td align='left'><input tabindex='1' type='text' size='20' name='wikititle' value=\"" . htmlentities($title) . "\"/></td>
    </tr>
    <tr> 
         <td align='right'>Description</td>
         <td align='left'><textarea tabindex='1' cols=\"40\" rows=\"5\" name='description'>" . htmlentities($description) . "</textarea></td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td align='right'><input type='submit' name='submit' value=\"Submit\" /></td>
        </tr>
    </table>
    <input type='hidden' name='token' value='$token' />
</form>");
        
    }
    
    protected function _executeUpdateList(&$wgFarmer)
    {
        $wgUser = $wgFarmer->getMWVariable('wgUser');
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        /*
        if (!MediaWikiFarmer::userIsFarmerAdmin($wgUser)) {
            $wgOut->addWikiText('==Permission Denied==');
            
            return;
        }
        */
        $wgFarmer->updateFarmList();
        
        $wgOut->addWikiText('==Updated List==');
    }
    
    protected function _executeDelete(&$wgFarmer)
    {
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        $wgUser = $wgFarmer->getMWVariable('wgUser');
        
        if (!$wgFarmer->getActiveWiki()->isDefaultWiki()) {
        	$wgOut->addWikiText('==Not Accessible==');
            $wgOut->addWikiText('This feature is only available on the parent wiki in the farm');
            return;
        }
        
        if (!MediaWikiFarmer::userIsFarmerAdmin($wgUser)) {
        	$wgOut->addWikiText('==Permission Denied==');
            $wgOut->addWikiText('You do not have permission to delete a wiki from the farm');
            return;
        }
        
        $wgRequest = $wgFarmer->getMWVariable('wgRequest');
        
        if ( ($wiki = $wgRequest->getVal('wiki')) && $wiki != '-1') {
            $wgOut->addWikiText('==Deleting ' . $wiki .'==');
            
            $deleteWiki = MediaWikiFarmer_Wiki::factory($wiki);
            
            $wgFarmer->deleteWiki($deleteWiki);
        }
        
        $list = $wgFarmer->getFarmList();
        
        $wgOut->addWikiText('==Delete Wiki==');
        $wgOut->addWikiText('Please select the wiki from the list below that you wish to delete');
        
        $wgOut->addHTML('<form method="post" name="deleteWiki"><select name="wiki"><option value="-1">Select a wiki</option>');
        
        foreach ($list as $wiki) {
        	if ($wiki['name'] != $wgFarmer->getDefaultWiki()) {
                $wgOut->addHTML('<option value="'.$wiki['name'].'">'.$wiki['name'] . ' - ' . $wiki['title'] . '</option>');
            }
        }
        
        $wgOut->addHTML('<input type="submit" name="submit" value="Delete" /></form>');
        
    }
    
    protected function _executeList(&$wgFarmer)
    {
        $list = $wgFarmer->getFarmList();
        
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        
        $wgOut->addWikiText('==List of Wikis==');
        
        foreach ($list as $wiki) {
            $wgOut->addWikiText('; [[' . $wiki['title'] .':Main Page|'.$wiki['title'].']] : ' . $wiki['description']);
        }
    }
    
    protected function _executeAdminister(&$wgFarmer)
    {
        $wgUser = $wgFarmer->getMWVariable('wgUser');
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        $wgRequest = $wgFarmer->getMWVariable('wgRequest');
        
        $currentWiki = MediaWikiFarmer_Wiki::factory($wgFarmer->getActiveWiki());
        
        $action = Title::makeTitle(NS_SPECIAL, 'Farmer/admin')->escapeLocalURL();
        
        if (!(MediaWikiFarmer::userIsFarmerAdmin($wgUser) || $currentWiki->userIsAdmin($wgUser))) {
            $wgOut->addWikiText('==Permission Denied==');
            $wgOut->addWikiText('You do not have permission to access this page');
            return;
        }
        
        $wgOut->addWikiText('==Basic Parameters==');
        
        $wiki = $wgFarmer->getActiveWiki();
        
        if ($title = $wgRequest->getVal('wikiTitle')) {
            $wiki->title = MediaWikiFarmer_Wiki::sanitizeTitle($title);
            $wiki->save();
            $wgFarmer->updateFarmList();
        }
        
        if ($description = $wgRequest->getVal('wikiDescription')) {
        	$wiki->description = $description;
            $wiki->save();
            $wgFarmer->updateFarmList();
        }
        
        if ($permissions = $wgRequest->getArray('permission')) {
        	foreach ($permissions['*'] as $k=>$v) {
        		$wiki->setPermissionForAll($k, $v);
        	}
            
            foreach ($permissions['user'] as $k=>$v) {
            	$wiki->setPermissionForUsers($k, $v);
            }
            
            $wiki->save();
        }
        
        if (!$wiki->title) {
            $wgOut->addWikiText('===Title===');
            $wgOut->addWikiText('Your wiki does not have a title.  Set one NOW');
            
            $wgOut->addHTML('<form method="post" name="wikiTitle" action="'.$action.'">' .
                    '<input name="wikiTitle" size="30" value="'. $wiki->title . '" />' .
                    '<input type="submit" name="submit" value="submit" />' .
                    '</form>'
                   );
        }
        
        $wgOut->addWikiText('===Description===');
        $wgOut->addWikiText('Set the description of your wiki below');
        
        $wgOut->addHTML('<form method="post" name="wikiDescription" action="'.$action.'">'.
            '<textarea name="wikiDescription" rows="5" cols="30">'.htmlentities($wiki->description).'</textarea>'.
            '<input type="submit" name="submit" value="submit" />'.
            '</form>'
            );
        
        $wgOut->addWikiText('==Permissions==');
        $wgOut->addWikiText('Using the form below, it is possible to alter permissions for users of this wiki.');
        
        $wgOut->addHTML('<form method="post" name="permissions" action="'.$action.'">');
        
        $wgOut->addWikiText('===Permissions for Every Visitor===');
        $wgOut->addWikiText('The following permissions will be applied to every person who visits this wiki');
        
        $doArray = array(
            array('read', 'View all articles'),
            array('edit', 'Edit all articles'),
            array('createpage', 'Create new articles'),
            array('createtalk', 'Create talk articles')
        );
        
        foreach ($doArray as $arr) {
        	$this->_doPermissionInput($wgOut, $wiki, '*', $arr[0], $arr[1]);
        }
        
        $wgOut->addWikiText('===Permissions for Logged-In Users===');
        $wgOut->addWikiText('The follow permissions will be applied to every person who is logged into this wiki');
        
        $doArray = array(
            array('read', 'View all articles'),
            array('edit', 'Edit all articles'),
            array('createpage', 'Create new articles'),
            array('createtalk', 'Create talk articles'),
            array('move', 'Move articles'),
            array('upload', 'Upload files'),
            array('reupload', 'Re-upload files (overwrite existing uploads'),
            array('minoredit', 'Allow minor edits')
        );
        
        foreach ($doArray as $arr) {
        	$this->_doPermissionInput($wgOut, $wiki, 'user', $arr[0], $arr[1]);
        }
        
        $wgOut->addHTML('<input type="submit" name="setPermissions" value="Set Permissions" />');
        
        $wgOut->addHTML("</form>\n\n\n");
        
        
        $wgOut->addWikiText("==Default Skin==");
        
        if ($newSkin = $wgRequest->getVal('defaultSkin')) {
        	$wiki->wgDefaultSkin = $newSkin;
            $wiki->save();
        }
        
        $defaultSkin = $wgFarmer->getActiveWiki()->wgDefaultSkin;
        
        if (!$defaultSkin) {
            $defaultSkin = 'MonoBook';
        }
        
        $skins = Skin::getSkinNames();
        $skipSkins = $wgFarmer->getMWVariable('wgSkipSkins');
        
        foreach ($skipSkins as $skin) {
            if (array_key_exists($skin, $skins)) {
                unset($skins[$skin]);
            }
        }
        
        $wgOut->addHTML('<form method="post" name="formDefaultSkin" action="'.$action.'">');
        
        foreach ($skins as $k=>$skin) {
        	$toAdd = '<input type="radio" name="defaultSkin" value="'.$k.'"';
            
            if ($k == $defaultSkin) {
            	$toAdd .= ' checked="checked" ';
            }
            
            $toAdd .= '/>' . $skin;
            
            $wgOut->addHTML($toAdd . "<br />\n");
        }
        
        $wgOut->addHTML('<input type="submit" name="submitDefaultSkin" value="Set Default Skin" />');
        
        $wgOut->addHTML('</form>');
        
        /**
         * Manage active extensions
         */
        $wgOut->addWikiText('==Active Extensions==');
        
        $extensions = $wgFarmer->getExtensions();
        
        //if we post a list of new extensions, wipe the old list from the wiki
        if ($wgRequest->getVal('submitExtension')) {
        	$wiki->extensions = array();
        }
        
        //go through all posted extensions and add the appropriate ones
        foreach ((array)$wgRequest->getArray('extension') as $k=>$e) {
            
            if (array_key_exists($k, $extensions)) {
        		$wiki->addExtension($extensions[$k]);
        	}
        }
        
        $wiki->save();
        
        
        $wgOut->addHTML('<form method="post" name="formActiveExtensions" action="'.$action.'">');
        
        foreach ($extensions as $extension) {
        	$toAdd = '<input type="checkbox" name="extension['.$extension->name.']" ';
            
            if ($wiki->hasExtension($extension)) {
            	$toAdd .= 'checked="checked" ';
            }
            
            $toAdd .=' /><strong>'.htmlentities($extension->name) . '</strong> - ' . htmlentities($extension->description) . "<br />\n";
            
            $wgOut->addHTML($toAdd);
        }
        
        $wgOut->addHTML('<input type="submit" name="submitExtension" value="Set Active Extensions" />');
        
        $wgOut->addHTML('</form>');

    }
    
    /**
     * Handles page to manage extensions
     */
    protected function _executeManageExtensions(&$wgFarmer)
    {
        $wgUser = $wgFarmer->getMWVariable('wgUser');
        $wgOut = $wgFarmer->getMWVariable('wgOut');
        
        //quick security check
        if (!MediaWikiFarmer::userIsFarmerAdmin($wgUser)) {
            $wgOut->addWikiText('==Permission Denied==');
            $wgOut->addWikiText('You do not have permission to use this feature.  You must be a member of the farmeradmin group');
            return;
        }
        
        //look and see if a new extension was registered
        $wgRequest = $wgFarmer->getMWVariable('wgRequest');
        
        if ($wgRequest->wasPosted()) {
            $name = $wgRequest->getVal('name');
            $description = $wgRequest->getVal('description');
            $include = $wgRequest->getVal('include');
            
            $extension = new MediaWikiFarmer_Extension($name, $description, $include);
            
            if (!$extension->isValid()) {
            	$wgOut->addWikiText('==Invalid Extension==');
                $wgOut->addWikiText('We could not add the extension because the file selected for inclusion could not be found');
            } else {
                $wgFarmer->registerExtension($extension);
            }
        }
        
        
        $wgOut->addWikiText('==Available Extensions==');
        
        $extensions = $wgFarmer->getExtensions();
        
        if (count($extensions) === 0) {
            $wgOut->addWikiText('No extensions are registered');
        } else {   
            foreach ($wgFarmer->getExtensions() as $extension) {
                $wgOut->addWikiText('; ' . htmlentities($extension->name) . ' : ' . htmlentities($extension->description));
            }
        }
        
        $wgOut->addWikiText('==Register Extension==');
        $wgOut->addWikiText('Use the form below to register a new extension with the farm.  Once an extension is registered, all wikis will be able to use it.');
        
        $wgOut->addWikiText("For the ''Include File'' parameter, enter the name of the PHP file as you would in LocalSettings.php.");
        $wgOut->addWikiText("If the filename contains '''\$root''', that variable will be replaced with the MediaWiki root directory.");
        
        $wgOut->addWikiText('The current include paths are:');
        
        foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
            $wgOut->addWikiText('*' . $path);
        }
        
        $wgOut->addHTML("
<form id=\"registerExtension\" method=\"post\">
    <table> 
        <tr> 
            <td align=\"right\">Name</td> 
            <td align=\"left\"><input type=\"text\" size=\"20\" name=\"name\" value=\"\" /> 
        </tr>
        <tr> 
            <td align=\"right\">Description</td> 
            <td align=\"left\"><input type=\"text\" size=\"50\" name=\"description\" value=\"\" /> 
        </tr> 
        <tr>. 
            <td align=\"right\">Include File</td> 
            <td align=\"left\"><input type=\"text\" size=\"50\" name=\"include\" value=\"\" /> 
        </tr> 
        <tr> 
            <td>&nbsp;</td> 
            <td align=\"right\"><input type=\"submit\" name=\"submit\" value=\"Submit\" /></td> 
        </tr>
    </table>               
</form>");
        
    }
    
    /**
     * Creates form element representing an individual permission
     */
    protected function _doPermissionInput(&$wgOut, &$wiki, $group, $permission, $description)
    {
    	$value = $wiki->getPermission($group, $permission);
        
        $wgOut->addHTML('<p>' . $description . ': ');
        
        $input = "<input type=\"radio\" name=\"permission[$group][$permission]\" value=\"1\" ";
        
        if ($wiki->getPermission($group, $permission)) {
        	$input .= 'checked="checked" ';
        }
        
        $input .= ' />Yes&nbsp;&nbsp;';
        
        $wgOut->addHTML($input);
        
        $input = "<input type=\"radio\" name=\"permission[$group][$permission]\" value=\"0\" ";
        
        if (!$wiki->getPermission($group, $permission)) {
            $input .= 'checked="checked" ';
        }
        
        $input .= ' />No';
        
        $wgOut->addHTML($input . '</p>');
       
    }
    
}