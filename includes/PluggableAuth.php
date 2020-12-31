<?php
/**
 * Copyright (C) 2020  NicheWork, LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\WikiToLDAP;

use MediaWiki\Extension\LDAPAuthentication2\PluggableAuth as PluggableAuthBase;
use MWException;
use User;
use UserGroupMembership;

class PluggableAuth extends PluggableAuthBase {
	protected $migrationGroup;

	public function __construct() {
		$config = Config::newInstance();
		$this->migrationGroup = $config->get( Config::MIGRATION_GROUP );
	}

	/**
	 * Adjust the groups for a user based on how it logged in.
	 */
	protected function fixupGroups( int $id, string $username ) {
		$dbw = wfGetDB( DB_MASTER );
		$group = UserGroupMembership::getMembershipsForUser( $id, $dbw );

		if ( isset( $group[ $this->migrationGroup ] ) ) {
			$sectionId = $dbw->startAtomic( __METHOD__ );
			$msg = "Removed $username from $this->migrationGroup";
			if ( $group[ $this->migrationGroup ]->delete( $dbw ) === false ) {
				$msg = "Trouble removing $username from {$this->migrationGroup}";
			}
			$dbw->endAtomic( __METHOD__ );
			wfDebugLog( "wikitoldap", $msg );
		}
	}

	/**
	 * Determine if this is a wiki account that they are logging into.  If it
	 * is, ensure that it is removed from the MigrationGroup and is in the
	 * InProgressGroup.
	 *
	 * Return false if this is a Wiki account, but the login credentials were wrong.
	 * Return true if this is a Wiki account, and the login credentials are correct.
	 * Return null if this is not a wiki account.
	 *
	 * @param string $domain we are logging into
	 * @param string $username for the user
	 * @param string $password for the user
	 * @param int &$id value of id
	 * @param string &$errorMessage any error message for the user
	 *
	 * @return ?bool
	 */
	protected function maybeLocalLogin(
		$domain,
		$username,
		$password,
		&$id,
		&$errorMessage
	) {
		$user = User::newFromName( $username );
		if ( $user === false || $user->getId() === 0 ) {
			wfDebugLog( "wikitoldap", "No DB entry for $username. Not a local user." );
			return null;
		}

		$allGroups = array_merge( $user->getFormerGroups(), $user->getGroups() );

		// If they were never in the migration_group, they aren't a wiki user
		if ( !in_array( $this->migrationGroup, $allGroups ) ) {
			wfDebugLog( "wikitoldap", "$username was never in the {$this->migrationGroup}." );
			return null;
		}

		// Validate local user the mediawiki way
		if ( $this->checkLocalPassword( $username, $password ) ) {
			$id = $user->getId();
			$this->fixupGroups( $id, $user->getName() );

			wfDebugLog( "wikitoldap", "Successful local login for $username" );
			return true;
		}

		wfDebugLog( "wikitoldap", "Failed local login for $username" );
		$msg = "wrongpassword";
		if ( empty( $password ) ) {
			$msg .= "empty";
		}
		$errorMessage = wfMessage( $msg )->plain();
		return false;
	}
}
