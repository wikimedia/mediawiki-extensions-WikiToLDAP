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
	/**
	 * Adjust the groups for a user based on how it logged in.
	 */
	protected function fixupGroups( User $user ) {
		$id = $user->getId();
		$dbw = wfGetDB( DB_MASTER );
		$group = UserGroupMembership::getMembershipsForUser( $id, $dbw );
		$config = Config::newInstance();
		$migrationGroup = $config->get( Config::MIGRATION_GROUP );
		$inProgressGroup = $config->get( Config::IN_PROGRESS_GROUP );

		$sectionId = $dbw->startAtomic( __METHOD__ );
		if ( isset( $group[ $migrationGroup ] ) ) {
			$msg = "Removed $username from $migrationGroup";
			if ( $group[ $migrationGroup ]->delete( $dbw ) === false ) {
				$msg = "Trouble removing $username from $migrationGroup";
			}
			wfDebugLog( "wikitoldap", $msg );
		}
		if ( !isset( $group[ $inProgressGroup ] ) ) {
			$ugm = new UserGroupMembership( $id, $inProgressGroup );
			$msg = "Added $username to $inProgressGroup";
			if ( $ugm->insert( $dbw ) === false ) {
				$msg = "Trouble adding $username to $inProgressGroup";
			}
			wfDebugLog( "wikitoldap", $msg );
		}
		$dbw->endAtomic( __METHOD__ );
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
			return null;
		}

		$allGroups = array_merge( $user->getFormerGroups(), $user->getGroups() );

		// If they were never in the migration_group, they aren't a wiki user
		if ( !in_array( Config::MIGRATION_GROUP, $allGroups ) ) {
			return null;
		}

		// Validate local user the mediawiki way
		if ( $this->checkLocalPassword( $username, $password ) ) {
			$this->fixupGroups( $user );

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
