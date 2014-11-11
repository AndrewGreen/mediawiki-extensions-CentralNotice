<?php

/**
 * Utility functions for CentralNotice that don't belong elsewhere
 */
class CNDatabase {
	/**
	 * Gets a database object. Will be the DB_MASTER if the user has the
	 * centralnotice-admin right. NOTE: $force is ignored for such users.
	 *
	 * @param int|bool    $force   If false will return a DB master/slave based
	 *                             on users permissions. Set to DB_MASTER or
	 *                             DB_SLAVE to force that type for users that
	 *                             don't have the centralnotice-admin right.
	 *
	 * @param string|bool $wiki    Wiki database to connect to, if false will be
	 *                             the infrastructure DB.
	 *
	 * @return DatabaseBase
	 */
	public static function getDb( $force = false, $wiki = false ) {
		global $wgCentralDBname;
		global $wgUser;

		if ( $wgUser->isAllowed( 'centralnotice-admin' ) ) {
			$dbmode = DB_MASTER;
		} elseif ( $force === false ) {
			$dbmode = DB_SLAVE;
		} else {
			$dbmode = $force;
		}

		$db = ( $wiki === false ) ? $wgCentralDBname : $wiki;

		return wfGetDB( $dbmode, array(), $db );
	}
}
