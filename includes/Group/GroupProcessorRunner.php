<?php

namespace MediaWiki\Extension\PluggableAuth\Group;

use MediaWiki\Extension\PluggableAuth\CaseInsensitiveHashConfig;
use MediaWiki\Extension\PluggableAuth\PluggableAuthPlugin;
use MediaWiki\User\UserIdentity;
use MWException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GroupProcessorRunner implements LoggerAwareInterface {

	/**
	 * @var GroupProcessorFactory
	 */
	private $groupProcessorFactory = null;

	/**
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * @param GroupProcessorFactory $groupProcessorFactory
	 */
	public function __construct( GroupProcessorFactory $groupProcessorFactory ) {
		$this->groupProcessorFactory = $groupProcessorFactory;
		$this->logger = new NullLogger();
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param UserIdentity $user
	 * @param PluggableAuthPlugin $pluggableauth
	 * @return void
	 */
	public function run( UserIdentity $user, PluggableAuthPlugin $pluggableauth ) {
		$groupSyncs = $pluggableauth->getGroupSyncs();
		if ( empty( $groupSyncs ) ) {
			$this->logger->debug( "No groupsync set." );
			return;
		}
		$pluginAttributes = $pluggableauth->getAttributes( $user );
		foreach ( $groupSyncs as $name => $config ) {
			try {
				$groupSyncConfig = new CaseInsensitiveHashConfig( $config );
				if ( !$groupSyncConfig->has( 'type' ) ) {
					throw new MWException( "No type set for '$name' groupsync!" );
				}
				$type = $groupSyncConfig->get( 'type' );
				$this->logger->debug( "Running '$name' groupsync of type '$type' with attributes: "
					. json_encode( $pluginAttributes, true )
				);
				$processor = $this->groupProcessorFactory->getInstance( $type );
				$processor->run( $user, $pluginAttributes, $groupSyncConfig );
			} catch ( MWException $e ) {
				$this->logger->error(
					"Error creating '$name' groupsync: {$e->getMessage()}"
				);
			}
		}
	}
}
