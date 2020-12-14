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
use OutputPage;
use RequestContext;
use Skin;
use Title;
use User;

class Hook {

	/**
	 * Handle any initialisation
	 */
	public static function init(): void {
		global $wgWhitelistRead;

		$wgWhitelistRead[] = "Special:" . Special::PageName;
	}

	/**
	 * When someone logs in with LDAP, take that user off the ldap migration list.
	 */
	public static function onPluggableAuthUserAuthorization(
		User $user, bool &$authorized
	): bool {
		$conf = Config::newInstance();
		$user->removeGroup( $conf->get( "MigrationGroup" ) );
		return true;
	}

	/**
	 * Redirect all users in the migrate-from-ldap group to the migration page
	 */
	public static function onUserCan(
		Title $title, User $user, string $action, &$result
	): bool {
		$perm = MediaWikiServices::getInstance()->getPermissionManager();
		$migrate = Title::makeTitleSafe( NS_SPECIAL, Special::PageName );

		if (
			$perm->userHasRight( $user, 'migrate-from-ldap' ) &&
			$title->getNamespace() !== NS_SPECIAL
		) {
			header( "Location: " . $migrate->getFullURL() );

			$logEntry = new ManualLogEntry( "wikitoldap", "redirect" );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $title );
			$logId = $logEntry->insert();
			$logEntry->publish($logId);
		}
		return true;
	}
}
