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
use User;
use UserGroupMembership;

class PluggableAuth extends PluggableAuthBase {
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
 		$config = Config::newInstance();
		$user = User::newFromName( $username );
		if ( $user === false || $user->getId() === 0 ) {
			return null;
		}

		// Validate local user the mediawiki way
		if ( $this->checkLocalPassword( $username, $password ) ) {
			$id = $user->getId();
			$dbw = wfGetDB( DB_MASTER );
			$group = UserGroupMembership::getMembershipsForUser( $id, $dbw );

			$sectionId = $dbw->startAtomic( __METHOD__ );
			$groupOut = $config->get( Config::MIGRATION_GROUP );
			$groupIn = $config->get( Config::IN_PROGRESS_GROUP );
			if ( isset( $group[ $groupOut ] ) ) {
				if ( $group[ $groupOut ]->delete( $dbw ) === false ) {
					throw new \MWException( "Trouble removing $username from $groupOut" );
					wfDebugLog( "wikitoldap", "Trouble removing $username from $groupOut" );
				}
			}
			if ( !isset( $group[ $groupIn ] ) ) {
				$ugm = new UserGroupMembership( $id, $groupIn );
				if ( $ugm->insert( $dbw ) === false ) {
					throw new \MWException( "Trouble adding $username to $groupIn" );
					wfDebugLog( "wikitoldap", "Trouble adding $username to $groupIn" );
				}
			}
			$dbw->endAtomic( __METHOD__ );

			return true;
		}

		$errorMessage = wfMessage( "loginerror" )->plain();
		return false;
	}
}
