<?php
/**
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
 * @file
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\WikiToLDAP;

use IUserMergeLogger;
use ManualLogEntry;
use User;

class Logger implements IUserMergeLogger {

	/**
	 * Adds a merge log entry
	 *
	 * @param User $performer
	 * @param User $oldUser
	 * @param User $newUser
	 */
	public function addMergeEntry( User $performer, User $oldUser, User $newUser ) {
		$logEntry = new ManualLogEntry( 'wikitoldap', 'mergeuser' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $newUser->getUserPage() );
		$logEntry->setParameters( [
			'oldName' => $oldUser->getName(),
			'oldId' => $oldUser->getId(),
			'newName' => $newUser->getName(),
			'newId' => $newUser->getId(),
		] );
		$logEntry->setRelations( [ 'oldname' => $oldUser->getName() ] );
		$logEntry->insert();
	}

	/**
	 * Adds a user deletion log entry
	 *
	 * @param User $performer
	 * @param User $oldUser
	 */
	public function addDeleteEntry( User $performer, User $oldUser ) {
		$logEntry = new ManualLogEntry( 'wikitoldap', 'deleteuser' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $oldUser->getUserPage() );
		$logEntry->setParameters( [
			'oldName' => $oldUser->getName(),
			'oldId' => $oldUser->getId(),
		] );
		$logEntry->insert();
	}
}
