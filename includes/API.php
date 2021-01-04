<?php
/**
 * Simple API to help the special page.
 *
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
 * @author Mark A. Hershberger  <mah@nichework.com>
 */
namespace MediaWiki\Extension\WikiToLDAP;

use ApiBase;

class API extends ApiBase {
	public function execute() {
		$conf = Config::newInstance();
		if ( $conf->get( Config::MIGRATION_IN_PROGRESS ) === false ) {
			return true;
		}

		$status = UserStatus::singleton();
		$status->setMerged( $this->getUser() );
		$status->setNotWiki( $this->getUser() );
		return $status->setNotInProgress( $this->getUser() );
	}
}
