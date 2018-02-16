<?php
if (! defined('_PS_VERSION_')) {
    exit();
}
/**
 * Provider of payment method information from the gateway.
 */
class Wallee_Provider_PaymentMethod extends Wallee_Provider_Abstract {

	protected function __construct(){
		parent::__construct('wallee_methods');
	}

	/**
	 * Returns the payment method by the given id.
	 *
	 * @param int $id
	 * @return \Wallee\Sdk\Model\PaymentMethod
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of payment methods.
	 *
	 * @return \Wallee\Sdk\Model\PaymentMethod[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
	    $methodService = new \Wallee\Sdk\Service\PaymentMethodService(Wallee_Helper::getApiClient());
		return $methodService->all();
	}

	protected function getId($entry){
		/* @var \Wallee\Sdk\Model\PaymentMethod $entry */
		return $entry->getId();
	}
}