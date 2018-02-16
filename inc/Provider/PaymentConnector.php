<?php
if (! defined('_PS_VERSION_')) {
    exit();
}
/**
 * Provider of payment connector information from the gateway.
 */
class Wallee_Provider_PaymentConnector extends Wallee_Provider_Abstract {

	protected function __construct(){
		parent::__construct('wallee_connectors');
	}

	/**
	 * Returns the payment connector by the given id.
	 *
	 * @param int $id
	 * @return \Wallee\Sdk\Model\PaymentConnector
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of payment connectors.
	 *
	 * @return \Wallee\Sdk\Model\PaymentConnector[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
	    $connectorService = new \Wallee\Sdk\Service\PaymentConnectorService(Wallee_Helper::getApiClient());
		return $connectorService->all();
	}

	protected function getId($entry){
		/* @var \Wallee\Sdk\Model\PaymentConnector $entry */
		return $entry->getId();
	}
}