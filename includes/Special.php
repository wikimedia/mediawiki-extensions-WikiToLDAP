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
use FormSpecialPage;
use HTMLForm;
use MediaWiki\Extension\LDAPProvider\ClientFactory;
use MediaWiki\Extension\LDAPProvider\DomainConfigFactory;
use Message;
use Status;
use User;

class Special extends FormSpecialPage {

	/** The text of the submit button. */
	private $submitButton = null;

	/**
	 * How this page is accessed ... This is here so we can do static calls
	 * from other classes.
	 */
	public const PAGENAME = "MigrateUser";

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
		parent::__construct( self::PAGENAME, 'migrate-from-ldap' );
		$this->setupStepMap();
	}

	protected function isValidMethod( string $method ): bool {
		return method_exists( $this, $method );
	}

	protected function isValidStep( ?string $step ): bool {
		$step = $step ?? "";

		return isset( $this->stepMap[$step] );
	}

	public function getMessagePrefix() {
		return "wikitoldap";
	}

	protected function setupStepMap() {
		foreach ( $this->step as $num => $info ) {
			if ( !isset( $info["par"] ) ) {
				throw new Exception( "Step $num does not have a parameter" );
			}
			$step = $info["par"];

			if ( !isset( $info["method"] ) ) {
				throw new Exception( "Step $num does not have a method" );
			}
			$method = $info['method'];

			if ( !$this->isValidMethod( $method ) ) {
				throw new Exception(
					"Step $num's method ($method) is not valid"
				);
			}
			$this->stepMap[$step]['method'] = $method;
			if ( isset( $this->step[$num + 1] ) ) {
				$this->stepMap[$step]['next'] = $num + 1;
			}
			if ( isset( $this->step[$num - 1] ) ) {
				$this->stepMap[$step]['prev'] = $num - 1;
			}
		}
	}

	public function showStep( ?string $step ): array {
		$method = $this->stepMap[$step]['method'];
		$form = $this->$method();
		$form['next'] = [
			"type" => "hidden",
			"default" => $this->getNextStep( $this->par )
		];
		return $form;
	}

	public function getNextStep( ?string $thisStep ): ?string {
		$thisStep = $thisStep ?? "";
		$nextStep = $this->stepMap[$thisStep]['next'] ?? null;

		return $this->step[$nextStep]['par'] ?? null;
	}

	public function displayIntro(): array {
		return [
			"message" => [
				"type" => "info",
				"label-message" => $this->getMessagePrefix() . "-introduction"
			]
		];
	}

	/**
	 * Allow the user to select an account to merge the current one with.
	 */
	public function selectAccount(): array {
		return [
			'user' => [
				'type' => 'user',
				'label-message' => $this->getMessagePrefix() . '-select-account',
				'size' => 30,
				'autofocus' => true,
				'validation-callback' => [ $this, 'validate' . __FUNCTION__ ],
				'value' => $this->getRequest()->getSession()->get( "user" ),
				'required' => true
			]
		];
	}

	public function validateSelectAccount( ?string $account, array $data ) {
		$user = User::newFromName( $account );
		if ( $user === false || $user->getId() === 0 ) {
			return new Message( $this->getMessagePrefix() . "-invalid-account", [ $account ] );
		}

		$groups = $user->getGroups();
		$conf = Config::newInstance();
		if ( in_array( $conf->get( "MigrationGroup" ), $groups ) ) {
			return new Message(
				$this->getMessagePrefix() . "-not-migratable-account", [ $account ]
			);
		}
		return true;
	}

	protected function getDomain(): string {
		$domain = DomainConfigFactory::getInstance()->getConfiguredDomains();

		if ( count( $domain ) !== 1 ) {
			throw new MWException( $this->getMessagePrefix() . "-only-one-domain" );
		}
		return $domain[0];
	}

	public function checkAccount(): array {
		$session = $this->getRequest()->getSession();
		$username = $session->get( "user" );

		return [
			'password' => [
				'label-message' => new Message(
					$this->getMessagePrefix() . '-ldap-password', [ $username ]
				),
				'validation-callback' => [ $this, 'validate' . __FUNCTION__ ],
				'type' => 'password',
				'required' => true
			]
		];
	}

	public function validateCheckAccount( ?string $password, array $data ) {
		$session = $this->getRequest()->getSession();
		$username = $session->get( "user" );
		$domain = $this->getDomain();

		if ( $this->validateSelectAccount( $username, [] ) !== true ) {
			return new Message(
				$this->getMessagePrefix() . "-account-problems", [ $username ]
			);
		}

		$ldapClient = ClientFactory::getInstance()->getForDomain( $domain );
		if ( !$ldapClient->canBindAs( $username, $password ) ) {
			return new Message( $this->getMessagePrefix() . "-invalid-password" );
		}
		return true;
	}

	public function mergeAccount(): array {
		return [];
	}

	/**
	 * Give the parent methods the form
	 */
	protected function getFormFields(): array {
		if ( !$this->isValidStep( $this->par ) ) {
			$this->getOutput()->redirect(
				self::getTitleFor( self::PAGENAME )->getFullURL()
			);
			return [];
		}
		return $this->showStep( $this->par );
	}

	/**
	 * Set the submit button to the text desired
	 */
	protected function alterForm( HTMLForm $form ): void {
		if ( $this->submitButton !== null ) {
			$form->setSubmitTextMsg( $this->submitButton );
		}
	}

	/**
	 * Handle submission.... redirect, etc
	 */
	public function onSubmit( array $data ): Status {
		$session = $this->getRequest()->getSession();
		$session->persist();

		$next = $data["next"] ?? "";
		unset( $data["next" ] );

		foreach ( $data as $key => $val ) {
			$session->set( $key, $val );
		}
		$this->getOutput()->redirect( self::getTitleFor( self::PAGENAME, $next )->getFullURL() );
		return Status::newGood();
	}
}
