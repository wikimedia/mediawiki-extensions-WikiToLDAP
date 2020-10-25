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

use Countable;
use Maintenance;
use MediaWiki\MediaWikiServices;
use User;
use UserArrayFromResult;

$IP = getenv( "MW_INSTALL_PATH" );
if ( $IP === false ) {
	$IP = "../..";
}
require "$IP/maintenance/Maintenance.php";

class MoveToMigrationGroup extends Maintenance {
	private /** @var Config */ $config;
	private /** @var string */ $group;

	public function __construct() {
		parent::__construct();
		$this->config = Config::newInstance();
		$this->group = $this->config->get( Config::LDAP_MIGRATION_GROP );
		$this->addDescription(
			"Put all users in the LDAPMigration group ($group)"
		);
	}

	public function execute() {
		foreach ( $this->getUsers() as $user ) {
			$this->moveToMigrationGroup( $user );
		}
	}

	protected function getUsers() :Countable {
		$dbr = MediaWikiServices::getInstance()
			 ->getDBLoadBalancer()->getMaintenanceConnectionRef( DB_REPLICA );
		return new UserArrayFromResult(
			$dbr->select( 'user', [ 'user_id' ], '', __METHOD__ )
		);
	}

	protected function moveToMigrationGroup( User $user ) :void {
		$groups = $user->getGroups();
		if ( ! in_array( $this->group, $groups ) ) {
			$this->output( "Adding $user to $group... " );
			$user->addGroup( $this->group );
			$this->output( "done\n" );
		}
	}
}

$maintClass = MoveToMigrationGroup::class;

require_once RUN_MAINTENANCE_IF_MAIN;
