<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Provider of label descriptor information from the gateway.
 */
class WalleeProviderLabeldescription extends WalleeProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('wallee_label_description');
    }

    /**
     * Returns the label descriptor by the given code.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptors.
     *
     * @return \Wallee\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorService = new \Wallee\Sdk\Service\LabelDescriptionService(
            WalleeHelper::getApiClient()
        );
        return $labelDescriptorService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}
