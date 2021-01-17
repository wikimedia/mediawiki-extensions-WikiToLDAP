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

use GlobalVarConfig;

class Config extends GlobalVarConfig {

	public const MIGRATION_GROUP = "MigrationGroup";
	public const IN_PROGRESS_GROUP = "InProgressGroup";
	public const MERGED_GROUP = "MergedGroup";
	public const OLD_USER_PREFIX = "OldUsernamePrefix";
    public const OLD_USERS_ARE_RENAMED = "OldUsersAreRenamed";
	public const MIGRATION_IN_PROGRESS = "MigrationInProgress";

	public function __construct() {
		parent::__construct( 'WikiToLDAP' );
	}

	/**
	 * Factory method for MediaWikiServices
	 * @return Config
	 */
	public static function newInstance() {
		return new self();
	}
}
