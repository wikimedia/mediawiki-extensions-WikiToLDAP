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

use Maintenance;
use MediaWiki\MediaWikiServices;
use Traversable;
use User;
use UserArrayFromResult;

$IP = getenv( "MW_INSTALL_PATH" );
if ( $IP === false ) {
	$IP = dirname( dirname( dirname( __DIR__ ) ) );
}
require "$IP/maintenance/Maintenance.php";

$maintClass = MoveToMigrationGroup::class;
class MoveToMigrationGroup extends Maintenance {
	private /** @var Config */ $config;
	private /** @var string */ $group;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			"Put all users in the LDAPMigration group."
		);
	}

	public function execute() {
		foreach ( $this->getUsers() as $user ) {
			$this->moveToMigrationGroup( $user );
		}
	}

	/**
	 * Get an iterator for the users.
	 */
	protected function getUsers() :Traversable {
		$dbr = MediaWikiServices::getInstance()
			 ->getDBLoadBalancer()->getMaintenanceConnectionRef( DB_REPLICA );
		return new UserArrayFromResult(
			$dbr->select( 'user', [ 'user_id' ], '', __METHOD__ )
		);
	}

	/**
	 * Move the user to the migration group
	 */
	protected function moveToMigrationGroup( User $user ) :void {
		$status = UserStatus::singleton();
		$esc = chr(27);
		if ( !$status->isWiki( $user ) ) {
			$this->output( "Adding $user to migration group... {$esc}[K" );
			if ( $status->setIsWiki( $user ) ) {
				$this->output( "done\r" );
			} else {
				$this->output( "error!\n" );
			}
		} else {
			$this->output( "$user is already in migration group.{$esc}[K\r" );
		}
	}
}
require_once RUN_MAINTENANCE_IF_MAIN;
