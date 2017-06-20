<?php
/**
 * Class containing updater functions for a Farmer environment
 */
class FarmerUpdaterHooks {

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function addSchemaUpdates( DatabaseUpdater $updater ) {
		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionTable( 'farmer_wiki', __DIR__ . "/farmer.sql" );
			$updater->addExtensionTable( 'farmer_extension', __DIR__ . "/farmer.sql" );
			$updater->addExtensionTable( 'farmer_wiki_extension', __DIR__ . "/farmer.sql" );
			$updater->addExtensionIndex( 'farmer_wiki_extension_wiki', 'farmer_wiki_extension',
				__DIR__ . "/farmer.sql" );
		}
		return true;
	}
}
