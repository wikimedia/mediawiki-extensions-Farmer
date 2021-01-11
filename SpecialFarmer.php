<?php
/**
 * Created on Jul 20, 2006
 *
 * @file
 * @ingroup Extensions
 * @author Gregory Szorc <gregory.szorc@gmail.com>
 */

/**
 *
 * @todo Move presentation text into MW messages
 */
class SpecialFarmer extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Farmer' );
	}

	/**
	 * Executes special page
	 * @param string|null $par
	 */
	public function execute( $par ) {
		global $wgRequest;
		$farmer = MediaWikiFarmer::getInstance();

		$this->setHeaders();

		$request = $par !== null ? $par : $wgRequest->getText( 'request' );

		$arr = explode( '/', $request );

		if ( count( $arr ) && $arr[0] ) {
			if ( $arr[0] == 'create' ) {
				$this->executeCreate( $farmer, isset( $arr[1] ) ? $arr[1] : null );
			} elseif ( $arr[0] == 'manageExtensions' ) {
				$this->executeManageExtensions( $farmer );
			} elseif ( $arr[0] == 'updateList' ) {
				$this->executeUpdateList( $farmer );
			} elseif ( $arr[0] == 'list' ) {
				$this->executeList( $farmer );
			} elseif ( $arr[0] == 'admin' ) {
				$this->executeAdminister( $farmer );
			} elseif ( $arr[0] == 'delete' ) {
				$this->executeDelete( $farmer );
			}
		} else {
			// no parameters were given
			// display the main page

			$this->executeMainPage( $farmer );
		}
	}

	/**
	 * Displays the main page
	 * @param MediaWikiFarmer $farmer
	 */
	private function executeMainPage( $farmer ) {
		global $wgOut, $wgUser;

		$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-about' );
		$wgOut->addWikiMsg( 'farmer-about-text' );

		$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-list-wiki' );
		$wgOut->wrapWikiMsg( '* $1', [ 'farmer-list-wiki-text', 'Special:Farmer/list' ] );

		if ( $farmer->getActiveWiki()->isDefaultWiki() ) {
			if ( MediaWikiFarmer::userCanCreateWiki( $wgUser ) ) {
				$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-createwiki' );
				$wgOut->wrapWikiMsg( '* $1', [ 'farmer-createwiki-text', 'Special:Farmer/create' ] );
			}

			// if the user is a farmer admin, give them a menu of cool admin tools
			if ( MediaWikiFarmer::userIsFarmerAdmin( $wgUser ) ) {
				$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-administration' );
				$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-administration-extension' );
				$wgOut->wrapWikiMsg( '* $1', [
						'farmer-administration-extension-text', 'Special:Farmer/manageExtensions'
					]
				);

				$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-admimistration-listupdate' );
				$wgOut->wrapWikiMsg( '* $1', [
						'farmer-admimistration-listupdate-text', 'Special:Farmer/updateList'
					]
				);

				$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-administration-delete' );
				$wgOut->wrapWikiMsg( '* $1', [
						'farmer-administration-delete-text', 'Special:Farmer/delete'
					]
				);
			}
		}

		$wiki = $farmer->getActiveWiki();

		if ( MediaWikiFarmer::userIsFarmerAdmin( $wgUser ) || $wiki->userIsAdmin( $wgUser ) ) {
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-administer-thiswiki' );
			$wgOut->wrapWikiMsg( '* $1', [
					'farmer-administer-thiswiki-text', 'Special:Farmer/admin'
				]
			);
		}
	}

	/**
	 * Displays form to create wiki
	 * @param MediaWikiFarmer $farmer
	 * @param string $wiki
	 */
	private function executeCreate( $farmer, $wiki ) {
		global $wgOut, $wgUser, $wgRequest;

		if ( !$farmer->getActiveWiki()->isDefaultWiki() ) {
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-notavailable' );
			$wgOut->addWikiMsg( 'farmer-notavailable-text' );
			return;
		}

		if ( !MediaWikiFarmer::userCanCreateWiki( $wgUser, $wiki ) ) {
			$wgOut->addWikiMsg( 'farmercantcreatewikis' );
			return;
		}

		$name = MediaWikiFarmer_Wiki::sanitizeName( $wgRequest->getVal( 'wpName', $wiki ) );
		$title = MediaWikiFarmer_Wiki::sanitizeTitle( $wgRequest->getVal( 'wpTitle' ) );
		$description = $wgRequest->getVal( 'wpDescription', '' );
		$reason = $wgRequest->getVal( 'wpReason' );
		$action = $this->getPageTitle( 'create' )->getLocalURL();

		// if something was POST'd
		if ( $wgRequest->wasPosted() ) {
			// we create the wiki if the user pressed 'Confirm'
			if ( $wgRequest->getCheck( 'wpConfirm' ) ) {
				$wikiObj = MediaWikiFarmer_Wiki::newFromParams(
					$name, $title, $description, $wgUser->getName()
				);
				$wikiObj->create();

				$log = new LogPage( 'farmer' );
				$log->addEntry( 'create', $this->getPageTitle(), $reason, [ $name ], $wgUser );

				$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-wikicreated' );
				$wgOut->addWikiMsg( 'farmer-wikicreated-text', $wikiObj->getUrl( wfUrlencode(
							wfMessage( 'mainpage' )->inContentLanguage()->useDatabase( false )->plain()
						)
					)
				);
				$wgOut->addWikiMsg( 'farmer-default', '[[' . $title . ':Special:Farmer|Special:Farmer]]' );
				return;
			}

			if ( $name && $title && $description ) {
				$wiki = new MediaWikiFarmer_Wiki( $name );

				if ( $wiki->exists() || $wiki->databaseExists() ) {
					$wgOut->wrapWikiMsg( "== $1 ==\n\n$2", 'farmer-wikiexists', [
							'farmer-wikiexists-text', $name
						]
					);
					return;
				}

				$url = $wiki->getUrl( '' );
				$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-confirmsetting' );

				$wgOut->addHtml( Xml::openElement( 'table', [ 'class' => 'wikitable' ] ) . "\n" .
					Xml::tags( 'tr', [], Xml::tags( 'th', [],
						wfMessage(
							'farmer-confirmsetting-name'
						)->parse() ) . Xml::element( 'td', [], $name ) ) . "\n" .
					Xml::tags( 'tr', [], Xml::tags( 'th', [],
						wfMessage(
							'farmer-confirmsetting-title'
						)->parse() ) . Xml::element( 'td', [], $title ) ) . "\n" .
					Xml::tags( 'tr', [], Xml::tags( 'th', [],
						wfMessage(
							'farmer-confirmsetting-description'
						)->parse() ) . Xml::element( 'td', [], $description ) ) . "\n" .
					Xml::tags( 'tr', [], Xml::tags( 'th', [],
						wfMessage(
							'farmer-confirmsetting-reason'
						)->parse() ) . Xml::element( 'td', [], $reason ) ) . "\n" .
					Xml::closeElement( 'table' )
				);
				$wgOut->addWikiMsg( 'farmer-confirmsetting-text', $name, $title, $url );

				$nameaccount = htmlspecialchars( $name );
				$nametitle = htmlspecialchars( $title );
				$namedescript = htmlspecialchars( $description );
				$confirmaccount = wfMessage( 'farmer-button-confirm' )->escaped();
				$wgOut->addHTML( "

<form id=\"farmercreate2\" method=\"post\" action=\"$action\">
<input type=\"hidden\" name=\"wpName\" value=\"{$nameaccount}\" />
<input type=\"hidden\" name=\"wpTitle\" value=\"{$nametitle}\" />
<input type=\"hidden\" name=\"wpDescription\" value=\"{$namedescript}\" />
<input type=\"hidden\" name=\"wpReason\" value=\"{$reason}\" />
<input type=\"submit\" name=\"wpConfirm\" value=\"{$confirmaccount}\" />
</form>"
				);

				return;
			}
		}

		if ( $wiki && !$name ) {
			$name = $wiki;
		}

		$wgOut->wrapWikiMsg(
			"== $1 ==\n$2\n== $3 ==\n$4\n$5\n$6",
			'farmer-createwiki-form-title',
			'farmer-createwiki-form-text1',
			'farmer-createwiki-form-help',
			'farmer-createwiki-form-text2',
			'farmer-createwiki-form-text3',
			'farmer-createwiki-form-text4'
		);

		$formURL = wfMessage( 'farmercreateurl' )->escaped();
		$formSitename = wfMessage( 'farmercreatesitename' )->escaped();
		$formNextStep = wfMessage( 'farmercreatenextstep' )->escaped();

		$token = htmlspecialchars( $wgUser->getEditToken() );

		$wgOut->addHTML(
			Xml::openElement( 'form', [ 'method' => 'post', 'action' => $action ] ) . "\n" .
			Xml::buildForm(
				[
					'farmer-createwiki-user' => Xml::element( 'b', [], $wgUser->getName() ),
					'farmer-createwiki-name' => Xml::input( 'wpName', 20, $name ),
					'farmer-createwiki-title' => Xml::input( 'wpTitle', 20, $title ),
					'farmer-createwiki-description' => Xml::textarea( 'wpDescription', $description ),
					'farmer-createwiki-reason' => Xml::input( 'wpReason', 20, $reason ),
				], 'farmer-button-submit'
			) . "\n" .
			Html::Hidden( 'token', $token ) . "\n" .
			Xml::closeElement( 'form' )
		);
	}

	/**
	 * @param MediaWikiFarmer $farmer
	 */
	private function executeUpdateList( $farmer ) {
		global $wgUser, $wgOut;

		if ( !MediaWikiFarmer::userIsFarmerAdmin( $wgUser ) ) {
			throw new PermissionsError( 'farmeradmin' );
		}

		$farmer->updateFarmList();
		$farmer->updateInterwikiTable();
		$wgOut->wrapWikiMsg( '<div class="successbox">$1</div><br clear="all" />', 'farmer-updatedlist' );
		$wgOut->returnToMain( null, $this->getPageTitle() );
	}

	/**
	 * @param MediaWikiFarmer $farmer
	 */
	private function executeDelete( $farmer ) {
		global $wgOut, $wgUser, $wgRequest;

		if ( !$farmer->getActiveWiki()->isDefaultWiki() ) {
			$wgOut->wrapWikiMsg( "== $1 ==\n$2", 'farmer-notaccessible', 'farmer-notaccessible-test' );
			return;
		}

		if ( !MediaWikiFarmer::userIsFarmerAdmin( $wgUser ) ) {
			$wgOut->wrapWikiMsg( "== $1 ==\n$2", 'farmer-permissiondenied', 'farmer-permissiondenied-text' );
			return;
		}

		if ( $wgRequest->wasPosted() ) {
			$wiki = $wgRequest->getVal( 'wpWiki' );
			if ( $wiki && $wiki != '-1' ) {
				if ( $wgRequest->getCheck( 'wpConfirm' ) ) {
					$wgOut->wrapWikiMsg( '<div class="successbox">$1</div>', [ 'farmer-deleting', $wiki ] );

					$log = new LogPage( 'farmer' );
					$log->addEntry(
						'delete',
						$this->getPageTitle(),
						$wgRequest->getVal( 'wpReason' ),
						[ $wiki ],
						$wgUser
					);

					$deleteWiki = MediaWikiFarmer_Wiki::factory( $wiki );
					$deleteWiki->deleteWiki();
				} else {
					$wgOut->addWikiMsg( 'farmer-delete-confirm-wiki', $wiki );
					$wgOut->addHTML(
						Xml::openElement( 'form', [ 'method' => 'post', 'name' => 'deleteWiki' ] ) . "\n" .
						Xml::buildForm( [
							'farmer-delete-reason' => Xml::input( 'wpReason', false, $wgRequest->getVal( 'wpReason' ) ),
							'farmer-delete-confirm' => Xml::check( 'wpConfirm' )
						], 'farmer-delete-form-submit' ) . "\n" .
						Html::Hidden( 'wpWiki', $wiki ) . "\n" .
						Xml::closeElement( 'form' )
					);
				}
				return;
			}
		}

		$list = $farmer->getFarmList();

		$wgOut->wrapWikiMsg( "== $1 ==\n$2", 'farmer-delete-title', 'farmer-delete-text' );

		$select = new XmlSelect( 'wpWiki', false, $wgRequest->getVal( 'wpWiki' ) );
		$select->addOption( wfMessage( 'farmer-delete-form' )->text(), '-1' );
		foreach ( $list as $wiki ) {
			if ( $wiki['name'] != $farmer->getDefaultWiki() ) {
				$name = $wiki['name'];
				$title = $wiki['title'];
				$select->addOption( "$name - $title", $name );
			}
		}

		$wgOut->addHTML(
			Xml::openElement( 'form', [ 'method' => 'post', 'name' => 'deleteWiki' ] ) . "\n" .
			$select->getHTML() . "\n" .
			Xml::submitButton( wfMessage( 'farmer-delete-form-submit' )->text() ) . "\n" .
			Xml::closeElement( 'form' )
		);
	}

	/**
	 * @param MediaWikiFarmer $farmer
	 */
	private function executeList( $farmer ) {
		global $wgOut;

		$list = $farmer->getFarmList();

		$wgOut->wrapWikiMsg( "== $1 ==", 'farmer-listofwikis' );
		$current = $farmer->getActiveWiki()->name;

		foreach ( $list as $wiki ) {
			$link = ( $current == $wiki['name'] ? wfMessage(
				'mainpage'
			)->inContentLanguage()->text() : $wiki['name'] . ':' );
			$this->outputWikiText(
				$wgOut, '; [[' . $link . '|' . $wiki['title'] . ']] : ' . $wiki['description']
			);
		}
	}

	/**
	 * @param MediaWikiFarmer $farmer
	 */
	private function executeAdminister( $farmer ) {
		global $wgOut, $wgUser, $wgRequest;

		$currentWiki = MediaWikiFarmer_Wiki::factory( $farmer->getActiveWiki() );

		$action = $this->getPageTitle( 'admin' )->getLocalURL();

		if ( !(
				MediaWikiFarmer::userIsFarmerAdmin( $wgUser ) || $currentWiki->userIsAdmin( $wgUser )
			)
		) {
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-permissiondenied' );
			$wgOut->addWikiMsg( 'farmer-permissiondenied-text1' );
			return;
		}

		$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-basic-title' );

		$wiki = $farmer->getActiveWiki();

		$title = $wgRequest->getVal( 'wikiTitle' );
		if ( $title ) {
			$wiki->title = MediaWikiFarmer_Wiki::sanitizeTitle( $title );
			$wiki->save();
			$farmer->updateFarmList();
		}

		$description = $wgRequest->getVal( 'wikiDescription' );
		if ( $description ) {
			$wiki->description = $description;
			$wiki->save();
			$farmer->updateFarmList();
		}

		if ( !$wiki->title ) {
			$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-basic-title1' );
			$wgOut->addWikiMsg( 'farmer-basic-title1-text' );

			$wgOut->addHTML(
				'<form method="post" name="wikiTitle" action="' . $action . '">' .
				'<input name="wikiTitle" size="30" value="' . $wiki->title . '" />' .
				'<input type="submit" name="submit" value="' . wfMessage(
					'farmer-button-submit'
				)->escaped() . '" />' .
				'</form>'
			);
		}

		$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-basic-description' );
		$wgOut->addWikiMsg( 'farmer-basic-description-text' );

		$wgOut->addHTML(
			'<form method="post" name="wikiDescription" action="' . $action . '">' .
			'<textarea name="wikiDescription" rows="5" cols="30">' . htmlspecialchars(
				$wiki->description
			) . '</textarea>' .
			'<input type="submit" name="submit" value="' . wfMessage(
				'farmer-button-submit'
			)->escaped() . '" />' .
			'</form>'
		);

		# Permissions stuff
		if ( Hooks::run( 'FarmerAdminPermissions', [ $farmer ] ) ) {
			# Import
			if ( $wgRequest->wasPosted() ) {
				$permissions = $wgRequest->getArray( 'permission' );
				if ( $permissions ) {
					foreach ( $permissions['*'] as $k => $v ) {
						$wiki->setPermissionForAll( $k, $v );
					}

					foreach ( $permissions['user'] as $k => $v ) {
						$wiki->setPermissionForUsers( $k, $v );
					}

					$wiki->save();
				}
			}

			# Form
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-basic-permission' );
			$wgOut->addWikiMsg( 'farmer-basic-permission-text' );

			$wgOut->addHTML( '<form method="post" name="permissions" action="' . $action . '">' );

			$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-basic-permission-visitor' );
			$wgOut->addWikiMsg( 'farmer-basic-permission-visitor-text' );

			$doArray = [
				[ 'read', wfMessage( 'right-read' )->text() ],
				[ 'edit', wfMessage( 'right-edit' )->text() ],
				[ 'createpage', wfMessage( 'right-createpage' )->text() ],
				[ 'createtalk', wfMessage( 'right-createtalk' )->text() ]
			];

			foreach ( $doArray as $arr ) {
				$this->doPermissionInput( $wgOut, $wiki, '*', $arr[0], $arr[1] );
			}

			$wgOut->wrapWikiMsg( '=== $1 ===', 'farmer-basic-permission-user' );
			$wgOut->addWikiMsg( 'farmer-basic-permission-user-text' );

			$doArray = [
				[ 'read', wfMessage( 'right-read' )->text() ],
				[ 'edit', wfMessage( 'right-edit' )->text() ],
				[ 'createpage', wfMessage( 'right-createpage' )->text() ],
				[ 'createtalk', wfMessage( 'right-createtalk' )->text() ],
				[ 'move', wfMessage( 'right-move' )->text() ],
				[ 'upload', wfMessage( 'right-upload' )->text() ],
				[ 'reupload', wfMessage( 'right-reupload' )->text() ],
				[ 'minoredit', wfMessage( 'right-minoredit' )->text() ]
			];

			foreach ( $doArray as $arr ) {
				$this->doPermissionInput( $wgOut, $wiki, 'user', $arr[0], $arr[1] );
			}

			$wgOut->addHTML( '<input type="submit" name="setPermissions" value="' .
				wfMessage( 'farmer-setpermission' )->text() . '" />' );
			$wgOut->addHTML( "</form>\n\n\n" );
		}

		# Default skin
		if ( Hooks::run( 'FarmerAdminSkin', [ $farmer ] ) ) {
			# Import
			if ( $wgRequest->wasPosted() ) {
				$newSkin = $wgRequest->getVal( 'defaultSkin' );
				if ( $newSkin ) {
					$wiki->wgDefaultSkin = $newSkin;
					$wiki->save();
				}
			}

			# Form
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-defaultskin' );

			$defaultSkin = $farmer->getActiveWiki()->wgDefaultSkin;

			if ( !$defaultSkin ) {
				$defaultSkin = 'MonoBook';
			}

			$skins = Skin::getSkinNames();

			foreach ( $this->getConfig()->get( 'SkipSkins' ) as $skin ) {
				if ( array_key_exists( $skin, $skins ) ) {
					unset( $skins[$skin] );
				}
			}

			$wgOut->addHTML( '<form method="post" name="formDefaultSkin" action="' . $action . '">' );

			foreach ( $skins as $k => $skin ) {
				$toAdd = '<input type="radio" name="defaultSkin" value="' . $k . '"';
				if ( $k == $defaultSkin ) {
					$toAdd .= ' checked="checked" ';
				}
				$toAdd .= '/>' . $skin;
				$wgOut->addHTML( $toAdd . "<br />\n" );
			}

			$wgOut->addHTML( '<input type="submit" name="submitDefaultSkin" value="' . wfMessage(
				'farmer-defaultskin-button'
			)->escaped() . '" />' );
			$wgOut->addHTML( '</form>' );
		}

		# Manage active extensions
		if ( Hooks::run( 'FarmerAdminExtensions', [ $farmer ] ) ) {
			$extensions = $farmer->getExtensions();

			// if we post a list of new extensions, wipe the old list from the wiki
			if ( $wgRequest->wasPosted() && $wgRequest->getCheck( 'submitExtension' ) ) {
				$wiki->extensions = [];

				// go through all posted extensions and add the appropriate ones
				foreach ( (array)$wgRequest->getArray( 'extension' ) as $k => $e ) {
					if ( array_key_exists( $k, $extensions ) ) {
						$wiki->addExtension( $extensions[$k] );
					}
				}

				$wiki->save();
			}

			# Form
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-extensions' );
			$wgOut->addHTML( '<form method="post" name="formActiveExtensions" action="' . $action . '">' );

			foreach ( $extensions as $extension ) {
				$toAdd = '<input type="checkbox" name="extension[' . $extension->name . ']" ';
				if ( $wiki->hasExtension( $extension ) ) {
					$toAdd .= 'checked="checked" ';
				}
				$toAdd .= ' /><strong>' . htmlspecialchars(
					$extension->name
				) . '</strong> - ' . htmlspecialchars( $extension->description ) . "<br />\n";
				$wgOut->addHTML( $toAdd );
			}

			$wgOut->addHTML( '<input type="submit" name="submitExtension" value="' . wfMessage(
				'farmer-extensions-button'
			)->escaped() . '" />' );
			$wgOut->addHTML( '</form>' );
		}
	}

	/**
	 * Handles page to manage extensions
	 * @param MediaWikiFarmer $farmer
	 */
	private function executeManageExtensions( $farmer ) {
		global $wgOut, $wgUser, $wgRequest;

		if ( !Hooks::run( 'FarmerManageExtensions', [ $farmer ] ) ) {
			return;
		}

		// quick security check
		if ( !MediaWikiFarmer::userIsFarmerAdmin( $wgUser ) ) {
			$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-permissiondenied' );
			$wgOut->addWikiMsg( 'farmer-extensions-extension-denied' );
			return;
		}

		// look and see if a new extension was registered

		if ( $wgRequest->wasPosted() ) {
			$name = $wgRequest->getVal( 'name' );
			$description = $wgRequest->getVal( 'description' );
			$include = $wgRequest->getVal( 'include' );

			$extension = new MediaWikiFarmer_Extension( $name, $description, $include );

			if ( !$extension->isValid() ) {
				$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-extensions-invalid' );
				$wgOut->addWikiMsg( 'farmer-extensions-invalid-text' );
			} else {
				$farmer->registerExtension( $extension );
			}
		}

		$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-extensions-available' );

		$extensions = $farmer->getExtensions();

		if ( count( $extensions ) === 0 ) {
			$wgOut->addWikiMsg( 'farmer-extensions-noavailable' );
		} else {
			foreach ( $farmer->getExtensions() as $extension ) {
				$this->outputWikiText( $wgOut, '; ' . htmlspecialchars(
					$extension->name
				) . ' : ' . htmlspecialchars( $extension->description ) );
			}
		}

		$wgOut->wrapWikiMsg( '== $1 ==', 'farmer-extensions-register' );
		$wgOut->addWikiMsg( 'farmer-extensions-register-text1' );
		$wgOut->addWikiMsg( 'farmer-extensions-register-text2' );
		$wgOut->addWikiMsg( 'farmer-extensions-register-text3' );
		$wgOut->addWikiMsg( 'farmer-extensions-register-text4' );

		foreach ( explode( PATH_SEPARATOR, get_include_path() ) as $path ) {
			$this->outputWikiText( $wgOut, '*' . $path );
		}

		$wgOut->addHTML( "
<form id=\"registerExtension\" method=\"post\">
	<table>
		<tr>
			<td align=\"right\">" . wfMessage( 'farmer-extensions-register-name' )->escaped() . "</td>
			<td align=\"left\"><input type=\"text\" size=\"20\" name=\"name\" value=\"\" /></td>
		</tr>
		<tr>
			<td align=\"right\">" . wfMessage( 'farmer-description' )->escaped() . "</td>
			<td align=\"left\"><input type=\"text\" size=\"50\" name=\"description\" value=\"\" /></td>
		</tr>
		<tr>
			<td align=\"right\">" . wfMessage( 'farmer-extensions-register-includefile' )->escaped() . "</td>
			<td align=\"left\"><input type=\"text\" size=\"50\" name=\"include\" value=\"\" /></td>
		</tr>
		<tr>
			<td>&#160;</td>
			<td align=\"right\"><input type=\"submit\" name=\"submit\" value=\"" . wfMessage(
				'farmer-button-submit'
			)->escaped() . "\" /></td>
		</tr>
	</table>
</form>" );
	}

	/**
	 * Creates form element representing an individual permission
	 * @param OutputPage $out
	 * @param MediaWikiFarmer_Wiki $wiki
	 * @param string $group
	 * @param string $permission
	 * @param string $description
	 */
	private function doPermissionInput( $out, $wiki, $group, $permission, $description ) {
		$value = $wiki->getPermission( $group, $permission );

		$out->addHTML( '<p>' . $description .
			Sanitizer::escapeHtmlAllowEntities( wfMessage( 'colon-separator' )->text() ) );
		wfMessage( 'eh' )->escaped();

		$input = "<input type=\"radio\" name=\"permission[$group][$permission]\" value=\"1\" ";

		if ( $wiki->getPermission( $group, $permission ) ) {
			$input .= 'checked="checked" ';
		}

		$input .= ' />' . wfMessage( 'farmer-yes' )->escaped() . '&#160;&#160;';

		$out->addHTML( $input );

		$input = "<input type=\"radio\" name=\"permission[$group][$permission]\" value=\"0\" ";

		if ( !$wiki->getPermission( $group, $permission ) ) {
			$input .= 'checked="checked" ';
		}

		$input .= ' />' . wfMessage( 'farmer-no' )->escaped();

		$out->addHTML( $input . '</p>' );
	}

	/**
	 * @param OutputPage $out
	 * @param string $text
	 */
	private function outputWikiText( $out, $text ) {
		if ( method_exists( $out, 'addWikiTextAsInterface' ) ) {
			// MW 1.32+
			$out->addWikiTextAsInterface( $text . "\n" );
		} else {
			$out->addWikiText( $text . "\n" );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}
}
