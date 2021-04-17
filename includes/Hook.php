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
use RequestContext;
use Title;
use User;

class Hook {

	/** @var bool */
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
		$GLOBALS["wgWhitelistRead"][] = "Special:" . SpecialWikiMerge::PAGENAME;
		$GLOBALS["wgWhitelistRead"][] = "Special:" . SpecialLDAPMerge::PAGENAME;
	}

	/**
	 * @param User $user
	 * @return ?Title
	 */
	private static function getWizardPage( User $user ): ?Title {
		$status = UserStatus::singleton();
		$page = null;
		if ( $status->isMerged( $user ) ) {
			wfDebugLog( "wikitoldap", "Merged wiki user, no redirect needed" );
		} elseif ( $status->isWiki( $user ) ) {
			wfDebugLog( "wikitoldap", "Old wiki user, will redirecto to Special::WikiMerge" );
			$page = Title::makeTitleSafe( NS_SPECIAL, SpecialWikiMerge::PAGENAME );
		} elseif ( $status->isInProgress( $user ) ) {
			wfDebugLog( "wikitoldap", "In progress user, will redirecto to Special::LDAPMerge" );
			$page = Title::makeTitleSafe( NS_SPECIAL, SpecialLDAPMerge::PAGENAME );
		}
		return $page;
	}

	/**
	 * Redirect all users in the migrate-from-ldap group to the migration page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/userCan
	 */
	public static function onUserCan(
		Title $title, User $user, string $action, &$result
	): ?bool {
		if ( self::$isWorking === false ) {
			return null;
		}

		$perm = MediaWikiServices::getInstance()->getPermissionManager();
		if (
			$perm->userHasRight( $user, 'migrate-from-ldap' ) &&
			$title->getNamespace() !== NS_SPECIAL
		) {
			$migrate = self::getWizardPage( $user );

			if ( $migrate !== null ) {
				header( "Location: " . $migrate->getFullURL(
					[ 'returnto' => $title->getPrefixedDBkey() ]
				) );

				$logEntry = new ManualLogEntry( "wikitoldap", "redirect" );
				$logEntry->setPerformer( $user );
				$logEntry->setTarget( $title );
				$logId = $logEntry->insert();
				$logEntry->publish( $logId );

				// Don't do anything else with this hook since we're redirecting
				return false;
			}
		}
		return null;
	}

	/**
	 * Set up groups on a new login.
	 *
	 * This has to be here and not at the time of LDAP authentication. Before
	 * this point, we can't tell if we have a new user or not, let alone what
	 * groups the user has been a member of.
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:PluggableAuth/Hooks/PluggableAuthPopulatedGroups
	 */
	public static function onPluggableAuthPopulateGroups( User $user ): void {
		if ( self::$isWorking === false ) {
			return;
		}

		$status = UserStatus::singleton();
		$username = $user->getName();

		wfDebugLog( "wikitoldap", "Checking to see if we need to migrate $username..." );

		# If they are not and never have been in the in-progress group, we need them in it.
		if ( !$status->wasEverInProgress( $user ) ) {
			$msg = "Setting $username to 'in progress'.";
			if ( !$status->setInProgress( $user ) ) {
				$msg = "Trouble setting $username to 'in progress'.";
			}
			wfDebugLog( "wikitoldap", $msg );
		}
	}

	/**
	 * Make sure people aren't confused by the old username prefix.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/AuthChangeFormFields
	 */
	public static function onAuthChangeFormFields(
		array $requests,
		array $fieldInfo,
		array &$form,
		string $action
	) {
		$req = RequestContext::getMain()->getRequest();
		$conf = Config::newInstance();
		$prefix = $conf->get( Config::OLD_USER_PREFIX );
		$prefixLen = mb_strlen( $prefix );

		$username = $req->getCookie( "UserName" ) ?? "";
		if ( mb_substr( $username, 0, $prefixLen ) === $prefix ) {
			if ( isset( $form['username']['type'] ) ) {
				$form['username']['default'] = mb_substr( $username, $prefixLen );
			}
		}
	}
}
