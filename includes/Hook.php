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
 * @file
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\WikiToLDAP;

use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use Title;
use User;
use UserGroupMembership;

class Hook {

	private static $isWorking = false;

	/**
	 * Handle any initialisation
	 */
	public static function init(): void {
		$conf = Config::newInstance();
		if ( $conf->get( Config::MIGRATION_IN_PROGRESS ) === false ) {
			return;
		}
		self::$isWorking = true;
		$GLOBALS["wgPluggableAuth_Class"] = __NAMESPACE__ . "\\PluggableAuth";
		$GLOBALS["wgWhitelistRead"][] = "Special:" . Special::PAGENAME;
	}

	/**
	 * Redirect all users in the migrate-from-ldap group to the migration page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/userCan
	 */
	public static function onUserCan(
		Title $title, User $user, string $action, &$result
	): void {
		if ( self::$isWorking === false ) {
			return;
		}

		$perm = MediaWikiServices::getInstance()->getPermissionManager();
		$migrate = Title::makeTitleSafe( NS_SPECIAL, Special::PAGENAME );

		if (
			$perm->userHasRight( $user, 'migrate-from-ldap' ) &&
			$title->getNamespace() !== NS_SPECIAL
		) {
			header( "Location: " . $migrate->getFullURL(
				[ 'returnto' => $title->getPrefixedDBkey() ]
			) );

			$logEntry = new ManualLogEntry( "wikitoldap", "redirect" );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $title );
			$logId = $logEntry->insert();
			$logEntry->publish( $logId );
		}
	}

	/**
	 * Don't offer any input on whether a user should be authorized, but set
	 * up groups on a new login.
	 *
	 * This has to be here and not at the time of LDAP authentication.  Before
	 * this point, we can't tell if we have a new user or not, let alone what
	 * groups the user has been a member of.
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:PluggableAuth/Hooks/PluggableAuthPopulatedGroups
	 */
	public static function onPluggableAuthPopulateGroups( User $user ): void {
		if ( self::$isWorking === false ) {
			return;
		}

		$id = $user->getId();
		$username = $user->getName();

		wfDebugLog( "wikitoldap", "Checking to see if we need to migrate $user..." );
		$config = Config::newInstance();
		$inProgressGroup = $config->get( Config::IN_PROGRESS_GROUP );

		# If they are not and never have been in the in-progress group, we need them in it.
		$allGroups = array_merge( $user->getFormerGroups(), $user->getGroups() );
		if ( !in_array( $inProgressGroup, $allGroups ) ) {
			$ugm = new UserGroupMembership( $id, $inProgressGroup );
			$dbw = wfGetDB( DB_MASTER );
			$dbw->startAtomic( __METHOD__ );
			$msg = "Added $username to $inProgressGroup";
			if ( $ugm->insert( $dbw ) === false ) {
				$msg = "Trouble adding $username to $inProgressGroup";
			}
			wfDebugLog( "wikitoldap", $msg );
			$dbw->endAtomic( __METHOD__ );
		}
	}
}
