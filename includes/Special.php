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

use Exception;
use SpecialPage;

class Special extends SpecialPage {

    /**
     * How this page is accessed.
     */
    public const PageName = "MigrateUser";

	/**
	 * The steps for migrating a user and the method tho
	 */
	protected $step = [
	    [
			"par" => "",
			"method" => "displayIntro"
		],
		[
			"par" => "account",
			"method" => "selectAccount"
		],
		[
			"par" => "authenticate",
			"method" => "checkAccount"
		],
		[
			"par" => "merge",
			"method" => "mergeAccount"
		]
	];
	protected $stepMap = [];

	public function __construct( $par = "" ) {
		parent::__construct( self::PageName );
		$this->setupStepMap();
	}

	protected function isValidMethod( string $method ): bool {
		return method_exists( $this, $method );
	}

	protected function isValidStep( ?string $step ): bool {
        $step = $step ?? "";

		return isset( $this->stepMap[$step] );
	}

	protected function setupStepMap() {
		foreach( $this->step as $num => $info ) {
			if ( !isset( $info["par"] ) ) {
				throw new Exception( "Step $num does not have a parameter" );
			}
			$step = $info["par"];

			if ( !isset( $info["method"] ) ) {
				throw new Exception( "Step $num does not have a method" );
			}
			$method = $info['method'];

			if ( !$this->isValidMethod( $method ) ) {
				throw new Exception( "Step $num's method ($method) is not valid" );
			}
			$this->stepMap[$step]['method'] = $method;
			if ( isset( $this->step[$num+1] ) ) {
				$this->stepMap[$step]['next'] = $num + 1;
			}
			if ( isset( $this->step[$num-1] ) ) {
				$this->stepMap[$step]['prev'] = $num - 1;
			}
		}
	}

	public function execute( $par = null ) {
		if ( !$this->isValidStep( $par ) ) {
			$this->getOutput()->redirect(
				self::getTitleFor( self::PageName )->getFullURL()
			);
			return;
		}
		$this->showStep( $par );
	}

	public function showStep( ?string $step ): void {
        $method = $this->stepMap[$step]['method'];
        $this->$method();
	}

    public function displayIntro() {
        $this->getOutput()->addWikiMsg( "" );
    }

    public function selectAccount() {
    }

    public function checkAccount() {
    }

    public function mergeAccount() {
    }
}
