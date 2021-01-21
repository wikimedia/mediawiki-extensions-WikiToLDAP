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
use MediaWiki\Extension\LDAPAuthentication2\ExtraLoginFields;
use PluggableAuthLogin;
use User;

class PluggableAuth extends PluggableAuthBase {
	protected $migrationGroup;
	protected $inProgressGroup;
	protected $usersRenamed;
	protected $oldUserPrefix;
	protected $canCheckOldUser;

	public function __construct() {
		$config = Config::newInstance();
		$this->migrationGroup = $config->get( Config::MIGRATION_GROUP );
		$this->inProgressGroup = $config->get( Config::IN_PROGRESS_GROUP );
		$this->usersRenamed = $config->get( Config::OLD_USERS_ARE_RENAMED );
		$this->oldUserPrefix = $config->get( Config::OLD_USER_PREFIX );
		$this->canCheckOldUser = $config->get( Config::CAN_CHECK_OLD_USER );
	}

	/**
	 * Authenticates against LDAP
	 * @param int &$id not used
	 * @param string &$username set to username
	 * @param string &$realname set to real name
	 * @param string &$email set to email
	 * @param string &$errorMessage any errors
	 * @return bool false on failure
	 * @SuppressWarnings( UnusedFormalParameter )
	 * @SuppressWarnings( ShortVariable )
	 */
	public function authenticate( &$id, &$username, &$realname, &$email, &$errorMessage ) {
		$authManager = $this->getAuthManager();
		$extraLoginFields = $authManager->getAuthenticationSessionData(
			PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY
		);

		$domain = $extraLoginFields[ExtraLoginFields::DOMAIN];
		$username = $extraLoginFields[ExtraLoginFields::USERNAME];
		$password = $extraLoginFields[ExtraLoginFields::PASSWORD];

		wfDebugLog( "wikitoldap", "Trying $username" );
		// We want them to be able to use the LDAP account if that works, so we have this
		// work-around
		$oldUsername = $username;
		$oldErrorMessage = $errorMessage;
		if ( $this->usersRenamed ) {
			$username = $this->oldUserPrefix . ucFirst( $username );
		}

		wfDebugLog( "wikitoldap", "checking $username for local login" );
		$isLocal = $this->maybeLocalLogin( $domain, $username, $password, $id, $errorMessage );
		if ( $isLocal === true ) {
			wfDebugLog( "wikitoldap", "checking $username for local login was successful" );
			return true;
		}
		$errorMessage = $oldErrorMessage;
		$username = $oldUsername;

		if (
			strtolower( substr( $username, 0, strlen( $this->oldUserPrefix ) ) )
			=== strtolower( $this->oldUserPrefix )
		) {
			$errorMessage = wfMessage( "wikitoldap-no-ldap-login-prefix" )->plain();
			return false;
		}

		wfDebugLog( "wikitoldap", "checking $username for ldap login" );
		if ( !$this->checkLDAPLogin(
			$domain, $username, $password, $realname, $email, $errorMessage
		) ) {
			wfDebugLog( "wikitoldap", "ldap login for $userame failed" );
			$errorMessage = wfMessage( "wikitoldap-ldap-login-failed" )->plain();
			return false;
		}
		$username = $this->normalizeUsername( $username );
		$user = User::newFromName( $username );
		if ( $user === false ) {
			wfDebug( "wikitoldap", "The username '$username' is not valid." );
			return false;
		}

		if ( $user->getId() > 0 ) {
			$id = $user->getId();
		}
		wfDebugLog( "wikitoldap", "ldap login for $username got id '$id'" );

		return true;
	}

	/**
	 * Determine if this is a wiki account that they are logging into.	If it
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
		&$username,
		$password,
		&$id,
		&$errorMessage
	) {
		$user = User::newFromName( $username );
		if ( $user === false || $user->getId() === 0 ) {
			wfDebugLog( "wikitoldap", "No DB entry for $username. Not a local user." );
			return null;
		}

		$status = UserStatus::singleton();

		// If they aren't a wiki user, pass
		if ( !$status->isWiki( $user ) ) {
			wfDebugLog( "wikitoldap", "$username is not a wiki user." );
			return null;
		}

		// If they are designated merged, they are't a wiki user
		if ( $status->isMerged( $user ) ) {
			# HACK!! The user merge copies over the wiki group after we can make adjustments, so we
			# fix it here.
			$status->setNotWiki( $user );
			wfDebugLog( "wikitoldap", "$username has been merged not a wiki user." );
			return null;
		}

		// Validate local user the mediawiki way
		if ( $this->checkLocalPassword( $username, $password ) ) {
			$status->setInProgress( $user );

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
