<?php
/**
 * Special page that handles account merging.
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
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\WikiToLDAP;

use Exception;
use FormSpecialPage;
use HTMLForm;
use MediaWiki\Extension\LDAPProvider\ClientFactory;
use MediaWiki\Extension\LDAPProvider\DomainConfigFactory;
use Message;
use MWException;
use Status;
use User;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Special page that handles account merging.
 */
class Special extends FormSpecialPage {

	/** The text of the submit button. */
	private $submitButton = null;

	/**
	 * How this page is accessed ... This is here so we can do static calls
	 * from other classes.
	 */
	public const PAGENAME = "MigrateUser";

	/**
	 * Key to use for return to url
	 */
	protected const RETURN_TO = "returnTo";

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

	/**
	 * A mapping of the steps for internal use.
	 */
	protected $stepMap = [];

	/**
	 * The last step, for ensuring things are working.
	 */
	protected $lastStep;

	/**
	 * Field variables that should not be persisted in the session
	 */
	protected $noPersist = [ 'password' ];

	/**
	 * Hold the session.
	 */
	protected $session;

	public function __construct( $par = "" ) {
		parent::__construct( self::PAGENAME, 'migrate-from-ldap' );
		$this->session = $this->getRequest()->getSession();
		$this->session->persist();

		$this->setupStepMap();
		$this->keepReturnTo();
	}

	public function keepReturnTo() {
		$returnTo = $this->getRequest()->getVal( "returnto" );

		if ( $returnTo ) {
			$this->setSession( self::RETURN_TO, $returnTo );
		}
	}

	protected function isValidMethod( string $method ): bool {
		return method_exists( $this, $method );
	}

	protected function isValidStep( ?string $step ): bool {
		$step = $step ?? "";

		return isset( $this->stepMap[$step] );
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
			$this->lastStep = $step;
		}
	}

	public function getMessagePrefix() {
		return "wikitoldap";
	}

	public function showStep( ?string $step = "" ): array {
		$method = $this->stepMap[$step]['method'];
		$form = [];
		try {
			$form = $this->$method();
			$form['next'] = [
				"type" => "hidden",
				"default" => $this->getNextStep()
			];
		} catch ( IncompleteFormException $e ) {
			$this->restartForm();
		} catch ( NoNextStepException $e ) {
			if ( $step !== $this->lastStep ) {
				throw $e;
			}
		}
		return $form;
	}

	public function getNextStep(): string {
		if ( !isset( $this->stepMap[$this->par]['next'] ) ) {
			throw new NoNextStepException( "Step {$this->par} does not have a next step" );
		}
		$next = $this->stepMap[$this->par]['next'];
		return $this->step[$next]['par'];
	}

	public function displayIntro(): array {
		return [
			"message" => [
				"type" => "info",
				"rawrow" => true,
				"default" => new Message( $this->getMessagePrefix() . "-introduction" )
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
				'default' => $this->getSession( "user" ),
				'required' => true
			]
		];
	}

	public function validateSelectAccount( string $account, array $data ) {
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

	protected function restartForm(): void {
		$this->getOutput()->redirect(
			self::getTitleFor( self::PAGENAME )->getFullURL()
		);
	}

	protected function getSession( string $key ): ?string {
		return $this->session->get( $this->getMessagePrefix() . $key );
	}

	protected function setSession( string $key, string $value ): void {
		$this->session->set( $this->getMessagePrefix() . $key, $value );
	}

	protected function clearSession(): void {
		$prefix = $this->getMessagePrefix();
		$prefixLen = strlen( $prefix );

		foreach ( $this->session as $key => $value ) {
			if ( substr( $key, 0, $prefixLen ) === $prefix && $prefixLen < strlen( $key ) ) {
				$this->session->remove( $key );
			}
		}
	}

	public function checkAccount(): array {
		$username = $this->getSession( "user" );

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
		$username = $this->getSession( "user" );
		$domain = $this->getDomain();

		if ( $username === null ) {
			$this->restartForm();
			return false;
		}

		if ( $this->validateSelectAccount( $username, [] ) !== true ) {
			return new Message(
				$this->getMessagePrefix() . "-account-problems", [ $username ]
			);
		}

		$ldapClient = ClientFactory::getInstance()->getForDomain( $domain );
		if ( !$ldapClient->canBindAs( $username, $password ) ) {
			return new Message( $this->getMessagePrefix() . "-invalid-password" );
		}

		$this->setSession( "authenticated", ConvertibleTimestamp::now() );
		return true;
	}

	public function mergeAccount(): array {
		$username = $this->getSession( "user" );
		$authenticated = $this->getSession( "authenticated" );

		if ( !$username || !$authenticated ) {
			throw new IncompleteFormException();
		}

		$out = $this->getOutput();
		$out->addJsConfigVars( "mergeInto", $username );
		$out->addModules( "ext.wikiToLDAP.mergeAccount" );

		return [
			"message" => [
				"type" => "info",
				"raw" => true,
				"default" => new Message(
					$this->getMessagePrefix() . "-confirm-merge", [ $this->getUser(), $username ]
				)
			]
		];
	}

	/**
	 * Give the parent methods the form
	 */
	protected function getFormFields(): array {
		if ( !$this->isValidStep( $this->par ) ) {
			$this->restartForm();
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
		$next = $data["next"] ?? "";
		unset( $data["next" ] );

		if ( $next ) {
			$redirect = self::getTitleFor( self::PAGENAME, $next )->getFullURL();
			foreach ( $data as $key => $val ) {
				if ( !in_array( $key, $this->noPersist ) ) {
					$this->setSession( $key, $val );
				}
			}
		} else {
			$redirect = $this->getSession( self::RETURN_TO );
			$this->clearSession();
		}

		if ( $redirect ) {
			$this->getOutput()->redirect( $redirect );
		}
		return Status::newGood();
	}
}
