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
 * Provider of label descriptor group information from the gateway.
 */
class WalleeProviderLabeldescriptiongroup extends WalleeProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('wallee_label_description_group');
    }

    /**
     * Returns the label descriptor group by the given code.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\LabelDescriptorGroup
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptor groups.
     *
     * @return \Wallee\Sdk\Model\LabelDescriptorGroup[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorGroupService = new \Wallee\Sdk\Service\LabelDescriptionGroupService(
            WalleeHelper::getApiClient()
        );
        return $labelDescriptorGroupService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\LabelDescriptorGroup $entry */
        return $entry->getId();
    }
}
