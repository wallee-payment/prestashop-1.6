<?php
if (! defined('_PS_VERSION_')) {
    exit();
}
/**
 * Provider of label descriptor information from the gateway.
 */
class Wallee_Provider_LabelDescription extends Wallee_Provider_Abstract {

	protected function __construct(){
		parent::__construct('wallee_label_description');
	}

	/**
	 * Returns the label descriptor by the given code.
	 *
	 * @param int $id
	 * @return \Wallee\Sdk\Model\LabelDescriptor
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of label descriptors.
	 *
	 * @return \Wallee\Sdk\Model\LabelDescriptor[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
	    $labelDescriptorService = new \Wallee\Sdk\Service\LabelDescriptionService(Wallee_Helper::getApiClient());
		return $labelDescriptorService->all();
	}

	protected function getId($entry){
		/* @var \Wallee\Sdk\Model\LabelDescriptor $entry */
		return $entry->getId();
	}
}