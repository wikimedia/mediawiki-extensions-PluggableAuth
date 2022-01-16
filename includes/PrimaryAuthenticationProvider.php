<?php

namespace MediaWiki\Extension\PluggableAuth;

use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use Message;
use MWException;
use RawMessage;
use Sanitizer;
use SpecialPage;
use StatusValue;
use User;

class PrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {

	public const CONSTRUCTOR_OPTIONS = [
		'PluggableAuth_ExtraLoginFields',
		'PluggableAuth_ButtonLabelMessage',
		'PluggableAuth_ButtonLabel',
		'PluggableAuth_EnableLocalProperties'
	];

	/**
	 * @var array
	 */
	private $extraLoginFields;

	/**
	 * @var string|null
	 */
	private $buttonLabelMessage;

	/**
	 * @var string|null
	 */
	private $buttonLabel;

	/**
	 * @var bool
	 */
	private $enableLocalProperties;

	public function __construct() {
		$options = new ServiceOptions(
			self::CONSTRUCTOR_OPTIONS,
			MediaWikiServices::getInstance()->getMainConfig()
		);
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->extraLoginFields = $options->get( 'PluggableAuth_ExtraLoginFields' );
		$this->buttonLabelMessage = $options->get( 'PluggableAuth_ButtonLabelMessage' );
		$this->buttonLabel = $options->get( 'PluggableAuth_ButtonLabel' );
		$this->enableLocalProperties = $options->get( 'PluggableAuth_EnableLocalProperties' );
	}

	/**
	 * Start an authentication flow
	 * @param array $reqs
	 * @return AuthenticationResponse
	 * @throws MWException
	 */
	public function beginPrimaryAuthentication( array $reqs ): AuthenticationResponse {
		$request = ButtonAuthenticationRequest::getRequestByName( $reqs,
			'pluggableauthlogin' );
		if ( !$request ) {
			return AuthenticationResponse::newAbstain();
		}
		$extraLoginFields = [];
		foreach ( $this->extraLoginFields as $key => $value ) {
			if ( isset( $request->$key ) ) {
				$extraLoginFields[$key] = $request->$key;
			}
		}
		$url = SpecialPage::getTitleFor( 'PluggableAuthLogin' )->getFullURL();
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOURL_SESSION_KEY, $request->returnToUrl );
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::EXTRALOGINFIELDS_SESSION_KEY, $extraLoginFields );
		// phpcs:disable MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$returnto = $_GET['returnto'] ?? '';
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOPAGE_SESSION_KEY, $returnto );
		$returntoquery = $_GET['returntoquery'] ?? '';
		// phpcs:enable
		$this->manager->setAuthenticationSessionData(
			PluggableAuthLogin::RETURNTOQUERY_SESSION_KEY, $returntoquery );

		return AuthenticationResponse::newRedirect( [
			new ContinueAuthenticationRequest()
		], $url );
	}

	/**
	 * Continue an authentication flow
	 * @param array $reqs
	 * @return AuthenticationResponse
	 */
	public function continuePrimaryAuthentication( array $reqs ): AuthenticationResponse {
		$request = AuthenticationRequest::getRequestByClass( $reqs,
			ContinueAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				new Message( 'pluggableauth-authentication-workflow-failure' ) );
		}
		$error = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::ERROR_SESSION_KEY );
		if ( $error !== null ) {
			$this->manager->removeAuthenticationSessionData(
				PluggableAuthLogin::ERROR_SESSION_KEY );
			return AuthenticationResponse::newFail( new RawMessage( $error ) );
		}
		$username = $request->username;
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $username );
		if ( $user && $user->getId() !== 0 ) {
			$this->updateUserRealnameAndEmail( $user );
		}
		return AuthenticationResponse::newPass( $username );
	}

	/**
	 * Determine whether a property can change
	 * @param string $property
	 * @return bool
	 */
	public function providerAllowsPropertyChange( $property ): bool {
		return $this->enableLocalProperties;
	}

	private function updateUserRealNameAndEmail( User $user, bool $force = false ): void {
		$realname = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::REALNAME_SESSION_KEY );
		$this->manager->removeAuthenticationSessionData(
			PluggableAuthLogin::REALNAME_SESSION_KEY );
		$email = $this->manager->getAuthenticationSessionData(
			PluggableAuthLogin::EMAIL_SESSION_KEY );
		$this->manager->removeAuthenticationSessionData(
			PluggableAuthLogin::EMAIL_SESSION_KEY );
		if ( $user->mRealName != $realname || $user->mEmail != $email ) {
			if ( $this->enableLocalProperties && !$force ) {
				$this->logger->debug( 'PluggableAuth: Local properties enabled.' );
				$this->logger->debug( 'PluggableAuth: Did not save updated real name and email address.' );
			} else {
				$this->logger->debug( 'PluggableAuth: Local properties disabled or has just been created.' );
				$user->mRealName = $realname;
				if ( $email && Sanitizer::validateEmail( $email ) ) {
					$user->mEmail = $email;
					$user->confirmEmail();
				}
				$user->saveSettings();
				$this->logger->debug( 'PluggableAuth: Saved updated real name and email address.' );
			}
		} else {
			$this->logger->debug( 'PluggableAuth: Real name and email address did not change.' );
		}
	}

	/**
	 * @param User $user
	 * @param string $source
	 */
	public function autoCreatedAccount( $user, $source ): void {
		$this->updateUserRealNameAndEmail( $user, true );
		$pluggableauth = PluggableAuth::singleton();
		if ( $pluggableauth ) {
			$pluggableauth->saveExtraAttributes( $user->mId );
		}
	}

	/**
	 * Test whether the named user exists
	 * @param string $username MediaWiki username
	 * @param int $flags Bitfield of User:READ_* constants
	 * @return bool
	 */
	public function testUserExists(
		$username,
		$flags = Authority::READ_NORMAL
	): bool {
		return false;
	}

	/**
	 * Validate a change of authentication data (e.g. passwords)
	 * @param AuthenticationRequest $req
	 * @param bool $checkData
	 * @return StatusValue
	 */
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req,
		$checkData = true
	): StatusValue {
		return StatusValue::newGood( 'ignored' );
	}

	/**
	 * Fetch the account-creation type
	 * @return string
	 */
	public function accountCreationType(): string {
		return self::TYPE_LINK;
	}

	/**
	 * Start an account creation flow
	 * @param User $user User being created (not added to the database yet).
	 * @param User $creator User doing the creation.
	 * @param AuthenticationRequest[] $reqs
	 * @return AuthenticationResponse
	 */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ): AuthenticationResponse {
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * Change or remove authentication data (e.g. passwords)
	 * @param AuthenticationRequest $req
	 */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ): void {
	}

	/**
	 * @param string $action
	 * @param array $options
	 * @return array|BeginAuthenticationRequest[]
	 */
	public function getAuthenticationRequests( $action, array $options ): array {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [
					new BeginAuthenticationRequest(
						$this->extraLoginFields,
						$this->buttonLabelMessage,
						$this->buttonLabel
					)
				];
			default:
				return [];
		}
	}
}