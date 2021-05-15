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
use Html;
use HTMLForm;
use MediaWiki\Extension\LDAPProvider\ClientFactory;
use MediaWiki\Extension\LDAPProvider\DomainConfigFactory;
use MergeUser;
use Message;
use MWException;
use Status;
use Title;
use User;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Special page that handles account merging.
 */
class SpecialWikiMerge extends FormSpecialPage {

	/** The text of the submit button. */
	private $submitButton = null;

	/**
	 * How this page is accessed ... This is here so we can do static calls
	 * from other classes.
	 */
	public const PAGENAME = "WikiUserMerge";

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
			"par" => "confirm",
			"method" => "confirmMerge"
		],
		[
			"par" => "merged",
			"method" => "merged"
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
		$this->handleAnon();

		$this->session = $this->getRequest()->getSession();
		$this->session->persist();

		$this->setupStepMap();
	}

	public function execute( $par ) {
		$status = UserStatus::singleton();
		if ( !$status->isWiki( $this->getUser() ) ) {
			$this->getOutput()->redirect( Title::newMainPage()->getFullURL() );
			return;
		}

		$this->setReturnto();
		parent::execute( $par );
	}

	protected function handleAnon(): void {
		// After the user merge, they end up back here, but they're anonymous.
		// So we'll send them to the front page.
		if ( $this->getUser()->isAnon() ) {
			parent::__construct( self::PAGENAME );
		} else {
			parent::__construct( self::PAGENAME, 'migrate-from-ldap' );
		}
	}

	protected function setReturnto(): void {
		if ( !$this->getSession( "returnto" ) ) {
			$return = $this->getRequest()->getVal( "returnto" );
			if ( !$return ) {
				$return = Title::newMainPage()->getPrefixedDBKey();
			}
			$this->setSession( "returnto", $return );
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

	/**
	 * The display format for HTMLForm
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	public function getMessagePrefix() {
		return "wikitoldap";
	}

	public function showStep( string $step = "" ): array {
		$form = [];
		if ( isset( $this->stepMap[$step]['method'] ) ) {
			$method = $this->stepMap[$step]['method'];
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
		$this->submitButton = "next-page";
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
		$this->submitButton = "next";
		return [
			'username' => [
				'label-message' => $this->getMessagePrefix() . '-select-account',
				'size' => 30,
				'type' => 'text',
				'autofocus' => true,
				'validation-callback' => [ $this, 'validateUsername' ],
				'default' => $this->getSession( "user" ),
				'required' => true
			],
			'password' => [
				'label-message' => new Message( $this->getMessagePrefix() . '-ldap-password' ),
				'validation-callback' => [ $this, 'validatePassword' ],
				'type' => 'password',
				'required' => true
			]
		];
	}

	public function validateUsername( ?string $username, array $data ) {
		if ( empty( $username ) ) {
			return new Message( $this->getMessagePrefix() . "-empty-username" );
		}

		return true;
	}

	public function validatePassword( ?string $password, array $data ) {
		$username = $data['username'];
		if ( empty( $password ) ) {
			return new Message( $this->getMessagePrefix() . "-empty-password" );
		}

		$ldapClient = ClientFactory::getInstance()->getForDomain( $this->getDomain() );
		if ( !$ldapClient->canBindAs( $username, $password ) ) {
			return new Message( $this->getMessagePrefix() . "-invalid-password" );
		}
		$this->setSession( "authenticated", ConvertibleTimestamp::now() );

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
		$prefixLen = mb_strlen( $prefix );

		foreach ( $this->session as $key => $value ) {
			if ( mb_substr( $key, 0, $prefixLen ) === $prefix && $prefixLen < mb_strlen( $key ) ) {
				$this->session->remove( $key );
			}
		}
	}

	public function confirmMerge(): array {
		$username = $this->getSession( "username" );
		$authenticated = $this->getSession( "authenticated" );

		if ( !$username || !$authenticated ) {
			wfDebugLog(
				"wikitoldap", "Empty session information: " .
				var_export(
					[ "username" => $username, "authenticated" => $authenticated ], true
				)
			);
			throw new IncompleteFormException();
		}

		$ldapUser = User::newFromName( ucFirst( $username ) );
		# The ldap user is the same username as the wiki user, so report complete
		if ( $ldapUser && $ldapUser->getId() === $this->getUser()->getId() ) {
			$status = UserStatus::singleton();
			$status->setNotInProgress( $ldapUser );
			$status->setNotWiki( $ldapUser );
			$this->submitButton = $this->getMessagePrefix() . "-return";

			$msg = new Message( $this->getMessagePrefix() . "-merge-not-needed" );
			return [
				'message' => [
					"type" => "info",
					"raw" => true,
					"default" => $msg->parse()
				],
				'done' => [
					"type" => "hidden",
					"default" => true
				]
			];
		}

		$msg = new Message(
			$this->getMessagePrefix() . "-confirm-wiki-merge", [ $this->getUser(), $username ]
		);
		return [
			"message" => [
				"type" => "info",
				"raw" => true,
				"default" => $msg->parse()
			],
			"confirmed" => [
				"type" => "hidden",
				"default" => true
			]
		];
	}

	public function merged(): array {
		$this->submitButton = $this->getMessagePrefix() . "-continue";

		$username = $this->getSession( "username" );
		$authenticated = $this->getSession( "authenticated" );
		$confirmed = $this->getSession( "confirmed" );
		$done = $this->getSession( "done" );

		if ( $done ) {
			$title = Title::newFromText( $this->getSession( 'returnto' ) )
				   ?? Title::newMainPage();
			$this->getOutput()->redirect( $title->getFullURL() );
			return [];
		}

		if ( !$username || !$authenticated || !$confirmed ) {
			wfDebugLog( "wikitoldap", "Incomplete: $username/$authenticated/$confirmed" );
			throw new IncompleteFormException();
		}
		$ldapUser = User::newFromName( ucFirst( $username ) );
		if ( $ldapUser->getId() === 0 ) {
			$ldapUser = User::createNew( ucFirst( $username ) );
		}
		if ( $ldapUser === null ) {
			throw new MWException( "Couldn't create a user for $username!" );
		}
		$this->mergeUser( $ldapUser );

		$status = new UserStatus();
		$status->setMerged( $ldapUser );

		$msg = new Message( $this->getMessagePrefix() . "-merge-done" );
		return [
			"message" => [
				"type" => "info",
				"raw" => true,
				"default" => $msg->parse()
			]
		];
	}

	public function mergeUser( User $ldapUser ) {
		// We're saying the LDAP user is the "performer" since we're merging the two accounts
		$um = new MergeUser( $this->getUser(), $ldapUser, new Logger() );
		$um->merge( $ldapUser, __METHOD__ );

		$out = $this->getOutput();
		$out->addWikiMsg(
			'usermerge-success',
			$this->getUser()->getName(), $this->getUser()->getId(),
			$ldapUser->getName(), $ldapUser->getId()
		);

		$failed = $um->delete( $this->getUser(), [ $this, 'msg' ] );
		$out->addWikiMsg(
			'usermerge-userdeleted', $this->getUser()->getName(), $this->getUser()->getId()
		);

		if ( $failed ) {
			// Output an error message for failed moves
			$out->addHTML( Html::openElement( 'ul' ) );
			$linkRenderer = $this->getLinkRenderer();
			foreach ( $failed as $oldTitleText => $newTitle ) {
				$oldTitle = Title::newFromText( $oldTitleText );
				$out->addHTML(
					Html::rawElement( 'li', [],
									  $this->msg( 'usermerge-page-unmoved' )->rawParams(
										  $linkRenderer->makeLink( $oldTitle ),
										  $linkRenderer->makeLink( $newTitle )
									  )->escaped()
					)
				);
			}

			$out->addHTML( Html::closeElement( 'ul' ) );
		}
	}

	/**
	 * Give the parent methods the form
	 */
	protected function getFormFields(): array {
		if ( !$this->isValidStep( $this->par ) ) {
			$this->restartForm();
			return [];
		}
		return $this->showStep( $this->par ?? "" );
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
		unset( $data["next"] );

		$redirect = null;
		if ( $next ) {
			$redirect = self::getTitleFor( self::PAGENAME, $next )->getFullURL();
			foreach ( $data as $key => $val ) {
				if ( !in_array( $key, $this->noPersist ) ) {
					wfDebugLog( "wikitoldap", "persisting '$key' with '$val'" );
					$this->setSession( $key, $val );
				}
			}
		} else {
			$this->clearSession();
		}

		if ( $redirect ) {
			$this->getOutput()->redirect( $redirect );
		}
		return Status::newGood();
	}
}
