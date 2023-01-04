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
 * Wallee_Service_Method_Configuration Class.
 */
class WalleeServiceMethodconfiguration extends WalleeServiceAbstract
{

    /**
     * Updates the data of the payment method configuration.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
     */
    public function updateData(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        $entities = WalleeModelMethodconfiguration::loadByConfiguration(
            $configuration->getLinkedSpaceId(),
            $configuration->getId()
        );
        foreach ($entities as $entity) {
            if ($this->hasChanged($configuration, $entity)) {
                $entity->setConfigurationName($configuration->getName());
                $entity->setState($this->getConfigurationState($configuration));
                $entity->setTitle($configuration->getResolvedTitle());
                $entity->setDescription($configuration->getResolvedDescription());
                $entity->setImage($this->getResourcePath($configuration->getResolvedImageUrl()));
                $entity->setImageBase($this->getResourceBase($configuration->getResolvedImageUrl()));
                $entity->setSortOrder($configuration->getSortOrder());
                $entity->save();
            }
        }
    }

    private function hasChanged(
        \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration,
        WalleeModelMethodconfiguration $entity
    ) {
        if ($configuration->getName() != $entity->getConfigurationName()) {
            return true;
        }

        if ($this->getConfigurationState($configuration) != $entity->getState()) {
            return true;
        }

        if ($configuration->getSortOrder() != $entity->getSortOrder()) {
            return true;
        }

        if ($configuration->getResolvedTitle() != $entity->getTitle()) {
            return true;
        }

        if ($configuration->getResolvedDescription() != $entity->getDescription()) {
            return true;
        }

        $image = $this->getResourcePath($configuration->getResolvedImageUrl());
        if ($image != $entity->getImage()) {
            return true;
        }

        $imageBase = $this->getResourceBase($configuration->getResolvedImageUrl());
        if ($imageBase != $entity->getImageBase()) {
            return true;
        }

        return false;
    }

    /**
     * Synchronizes the payment method configurations from wallee.
     */
    public function synchronize()
    {
        $existingFound = array();

        $existingConfigurations = WalleeModelMethodconfiguration::loadAll();

        $spaceIdCache = array();

        $paymentMethodConfigurationService = new \Wallee\Sdk\Service\PaymentMethodConfigurationService(
            WalleeHelper::getApiClient()
        );

        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(WalleeBasemodule::CK_SPACE_ID, null, null, $shopId);

            if ($spaceId) {
                if (! array_key_exists($spaceId, $spaceIdCache)) {
                    $spaceIdCache[$spaceId] = $paymentMethodConfigurationService->search(
                        $spaceId,
                        new \Wallee\Sdk\Model\EntityQuery()
                    );
                }
                $configurations = $spaceIdCache[$spaceId];
                foreach ($configurations as $configuration) {
                    $method = WalleeModelMethodconfiguration::loadByConfigurationAndShop(
                        $spaceId,
                        $configuration->getId(),
                        $shopId
                    );
                    if ($method->getId() !== null) {
                        $existingFound[] = $method->getId();
                    }
                    $method->setShopId($shopId);
                    $method->setSpaceId($spaceId);
                    $method->setConfigurationId($configuration->getId());
                    $method->setConfigurationName($configuration->getName());
                    $method->setState($this->getConfigurationState($configuration));
                    $method->setTitle($configuration->getResolvedTitle());
                    $method->setDescription($configuration->getResolvedDescription());
                    $method->setImage($this->getResourcePath($configuration->getResolvedImageUrl()));
                    $method->setImageBase($this->getResourceBase($configuration->getResolvedImageUrl()));
                    $method->setSortOrder($configuration->getSortOrder());
                    $method->save();
                }
            }
        }
        foreach ($existingConfigurations as $existingConfiguration) {
            if (! in_array($existingConfiguration->getId(), $existingFound)) {
                $existingConfiguration->setState(WalleeModelMethodconfiguration::STATE_HIDDEN);
                $existingConfiguration->save();
            }
        }
        Cache::clean('wallee_methods');
    }

    /**
     * Returns the payment method for the given id.
     *
     * @param int $id
     * @return \Wallee\Sdk\Model\PaymentMethod
     */
    protected function getPaymentMethod($id)
    {
        /* @var Wallee_Provider_Payment_Method */
        $methodProvider = WalleeProviderPaymentmethod::instance();
        return $methodProvider->find($id);
    }

    /**
     * Returns the state for the payment method configuration.
     *
     * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
     * @return string
     */
    protected function getConfigurationState(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration)
    {
        switch ($configuration->getState()) {
            case \Wallee\Sdk\Model\CreationEntityState::ACTIVE:
                return WalleeModelMethodconfiguration::STATE_ACTIVE;
            case \Wallee\Sdk\Model\CreationEntityState::INACTIVE:
                return WalleeModelMethodconfiguration::STATE_INACTIVE;
            default:
                return WalleeModelMethodconfiguration::STATE_HIDDEN;
        }
    }
}
