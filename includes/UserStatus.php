<?php
/**
 * Utility class to provide a consistent way to query information about a user.
 *
 * Copyright (C) 2021  NicheWork, LLC
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
 * @autho Mark A. Hershberger  <mah@nichework.com>
 */
namespace MediaWiki\Extension\WikiToLDAP;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManager;
use User;

class UserStatus {

	/** @var self */
	private static $singleton = null;
	protected $migrationGroup;
	protected $inProgressGroup;
	protected $mergedGroup;

	/** @var UserGroupManager */
	private $userGroupManager;

	public function __construct() {
		$config = Config::newInstance();
		$this->migrationGroup = $config->get( Config::MIGRATION_GROUP );
		$this->inProgressGroup = $config->get( Config::IN_PROGRESS_GROUP );
		$this->mergedGroup = $config->get( Config::MERGED_GROUP );
		$this->userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
	}

	public static function singleton(): self {
		if ( self::$singleton === null ) {
			self::$singleton = new static();
		}
		return self::$singleton;
	}

	/**
	 * Get all the groups this user has ever been in.
	 */
	protected function getAllGroups( User $user ): array {
		return array_merge(
			$this->userGroupManager->getUserFormerGroups( $user ),
			$this->userGroupManager->getUserGroups( $user )
		);
	}

	/**
	 * Check if this user is one that needs to be migrated.
	 */
	public function isWiki( User $user ): bool {
		return in_array( $this->migrationGroup, $this->userGroupManager->getUserGroups( $user ) );
	}

	/**
	 * Set this user as an original wiki user that needs to be migrated.
	 */
	public function setIsWiki( User $user ): bool {
		$ret = true;
		$username = $user->getName();
		if ( $this->isWiki( $user ) ) {
			wfDebugLog(
				"wikitoldap", "$username is in already in {$this->migrationGroup}"
			);
			return true;
		}

		$msg = "Adding $username to {$this->migrationGroup}.";
		if ( $this->userGroupManager->addUserToGroup( $user, $this->migrationGroup ) === false ) {
			$msg = "Trouble adding $username to {$this->migrationGroup}.";
			$ret = false;
		}
		wfDebugLog( "wikitoldap", $msg );

		return $ret;
	}

	/**
	 * This user is an original wiki user that no longer needs to be migrated.
	 */
	public function setNotWiki( User $user ): bool {
		$ret = true;
		$username = $user->getName();
		if ( !$this->isWiki( $user ) ) {
			wfDebugLog(
				"wikitoldap", "$username not in migration group -- cannot remove!"
			);
			return true;
		}

		$msg = "Removed $username from migration group.";
		if ( $this->userGroupManager->removeUserFromGroup( $user, $this->migrationGroup ) === false ) {
			$msg = "Trouble removing $username from migration group.";
			$ret = false;
		}
		wfDebugLog( "wikitoldap", $msg );

		return $ret;
	}

	/**
	 * Is this user in progress of being migrated right now?
	 */
	public function isInProgress( User $user ): bool {
		return in_array( $this->inProgressGroup, $this->userGroupManager->getUserGroups( $user ) );
	}

	/**
	 * Set this user in the process of being migrated.
	 */
	public function setInProgress( User $user ): bool {
		$ret = true;
		$username = $user->getName();
		if ( $this->isInProgress( $user ) ) {
			wfDebugLog(
				"wikitoldap", "$username is already in progress!"
			);
			return true;
		}

		$msg = "Adding $username to progress group.";
		if ( $this->userGroupManager->addUserToGroup( $user, $this->inProgressGroup ) === false ) {
			$msg = "Trouble adding $username to progress group.";
			$ret = false;
		}
		wfDebugLog( "wikitoldap", $msg );

		return $ret;
	}

	/**
	 * Set this user is not being migrated.
	 */
	public function setNotInProgress( User $user ): bool {
		$ret = true;
		$username = $user->getName();
		if ( !$this->isInProgress( $user ) ) {
			wfDebugLog(
				"wikitoldap", "$username not in progress -- cannot remove!"
			);
			return true;
		}

		$msg = "Removed $username from progress.";
		if ( $this->userGroupManager->removeUserFromGroup( $user, $this->inProgressGroup ) === false ) {
			$msg = "Trouble removing $username from progress.";
			$ret = false;
		}
		wfDebugLog( "wikitoldap", $msg );

		return $ret;
	}

	/**
	 * Check if this user has had a merge completed (is LDAP backed)
	 */
	public function isMerged( User $user ): bool {
		return in_array( $this->mergedGroup, $this->userGroupManager->getUserGroups( $user ) );
	}

	/**
	 * Set this user as having completed a merge (or not needing one).
	 */
	public function setMerged( User $user ): bool {
		$ret = true;
		$username = $user->getName();
		if ( $this->isMerged( $user ) ) {
			wfDebugLog(
				"wikitoldap", "$username is already merged!"
			);
			return true;
		}

		$msg = "Adding $username to {$this->mergedGroup}.";
		if ( $this->userGroupManager->addUserToGroup( $user, $this->mergedGroup ) === false ) {
			$msg = "Trouble adding $username to {$this->mergedGroup}.";
			$ret = false;
		}
		wfDebugLog( "wikitoldap", $msg );

		return $ret;
	}

	/**
	 * Was this user in progress of being migrated at some point?
	 */
	public function wasEverInProgress( User $user ): bool {
		return in_array( $this->inProgressGroup, $this->getAllGroups( $user ) );
	}
}
