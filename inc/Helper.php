<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2024 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class WalleeHelper
{
	protected static $shop_system = 'x-meta-shop-system';
	protected static $shop_system_version = 'x-meta-shop-system-version';
	protected static $shop_system_and_version = 'x-meta-shop-system-and-version';

    private static $apiClient;

    /**
     * Returns the base URL to the gateway.
     *
     * @return string
     */
    public static function getBaseGatewayUrl()
    {
        $url = Configuration::getGlobalValue(WalleeBasemodule::CK_BASE_URL);

        if ($url) {
            return rtrim($url, '/');
        }
        return 'https://app-wallee.com';
    }

    /**
     *
     * @throws Exception
     * @return \Wallee\Sdk\ApiClient
     */
    public static function getApiClient()
    {
        if (self::$apiClient === null) {
            $userId = Configuration::getGlobalValue(WalleeBasemodule::CK_USER_ID);
            $userKey = Configuration::getGlobalValue(WalleeBasemodule::CK_APP_KEY);
            if (! empty($userId) && ! empty($userKey)) {
                self::$apiClient = new \Wallee\Sdk\ApiClient($userId, $userKey);
                self::$apiClient->setBasePath(self::getBaseGatewayUrl() . '/api');
                foreach (self::getDefaultHeaderData() as $key => $value) {
                    self::$apiClient->addDefaultHeader($key, $value);
                }
            } else {
                throw new WalleeExceptionIncompleteconfig();
            }
        }
        return self::$apiClient;
    }

    public static function resetApiClient()
    {
        self::$apiClient = null;
    }

    public static function startDBTransaction()
    {
        $dbLink = Db::getInstance()->getLink();
        if ($dbLink instanceof mysqli) {
            $dbLink->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            $dbLink->begin_transaction();
        } elseif ($dbLink instanceof PDO) {
            $dbLink->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            $dbLink->beginTransaction();
        } else {
            throw new Exception('This module needs a PDO or MYSQLI link to use DB transactions');
        }
    }

    public static function commitDBTransaction()
    {
        $dbLink = Db::getInstance()->getLink();
        if ($dbLink instanceof mysqli) {
            $dbLink->commit();
        } elseif ($dbLink instanceof PDO) {
            $dbLink->commit();
        }
    }

    public static function rollbackDBTransaction()
    {
        $dbLink = Db::getInstance()->getLink();
        if ($dbLink instanceof mysqli) {
            $dbLink->rollback();
        } elseif ($dbLink instanceof PDO) {
            $dbLink->rollBack();
        }
    }

    /**
     * Create a lock to prevent concurrency.
     */
    public static function lockByTransactionId($spaceId, $transactionId)
    {
        Db::getInstance()->getLink()->query(
            'SELECT * FROM ' . _DB_PREFIX_ . 'wle_transaction_info WHERE transaction_id = "' .
            (int) $transactionId . '" AND space_id = "' . (int) $spaceId . '" FOR UPDATE;'
        );

        Db::getInstance()->getLink()->query(
            'UPDATE ' . _DB_PREFIX_ . 'wle_transaction_info SET locked_at = "' . pSQL(date('Y-m-d H:i:s')) .
            '" WHERE transaction_id = "' . (int) $transactionId . '" AND space_id = "' . (int) $spaceId . '";'
        );
    }

    /**
     * Returns the fraction digits of the given currency.
     *
     * @param string $currencyCode
     * @return number
     */
    public static function getCurrencyFractionDigits($currencyCode)
    {
        /* @var WalleeProviderCurrency $currency_provider */
        $currencyProvider = WalleeProviderCurrency::instance();
        $currency = $currencyProvider->find($currencyCode);
        if ($currency) {
            return $currency->getFractionDigits();
        } else {
            return 2;
        }
    }

    public static function roundAmount($amount, $currencyCode)
    {
        return round($amount, self::getCurrencyFractionDigits($currencyCode));
    }

    public static function convertCurrencyIdToCode($id)
    {
        $currency = Currency::getCurrencyInstance($id);
        return $currency->iso_code;
    }

    public static function convertLanguageIdToIETF($id)
    {
        $language = Language::getLanguage($id);
        return $language['language_code'];
    }

    public static function convertCountryIdToIso($id)
    {
        return Country::getIsoById($id);
    }

    public static function convertStateIdToIso($id)
    {
        $state = new State($id);
        return $state->iso_code;
    }

    /**
     * Returns the total amount including tax of the given line items.
     *
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @return float
     */
    public static function getTotalAmountIncludingTax(array $lineItems)
    {
        $sum = 0;
        foreach ($lineItems as $lineItem) {
            $sum += $lineItem->getAmountIncludingTax();
        }
        return $sum;
    }

    /**
     * Cleans the given line items by ensuring uniqueness and introducing adjustment line items if necessary.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @param float $expectedSum
     * @param string $currencyCode
     *
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     * @throws \WalleeExceptionInvalidtransactionamount
     */
    public static function cleanupLineItems(array &$lineItems, $expectedSum, $currencyCode)
    {
        $effectiveSum = self::roundAmount(self::getTotalAmountIncludingTax($lineItems), $currencyCode);
        $roundedExpected = self::roundAmount($expectedSum, $currencyCode);
        $diff = $roundedExpected - $effectiveSum;
        if ($diff != 0) {
            if ((int) Configuration::getGlobalValue(WalleeBasemodule::CK_LINE_ITEM_CONSISTENCY)) {
                throw new WalleeExceptionInvalidtransactionamount($effectiveSum, $roundedExpected);
            } else {
                $diffAmount = self::roundAmount($diff, $currencyCode);
                $lineItem = (new \Wallee\Sdk\Model\LineItemCreate())
                    ->setName(self::getModuleInstance()->l('Adjustment LineItem', 'helper'))
                    ->setUniqueId('Adjustment-Line-Item')
                    ->setSku('Adjustment-Line-Item')
                    ->setQuantity(1);
                /** @noinspection PhpParamsInspection */
                $lineItem->setAmountIncludingTax($diffAmount)->setType(($diff > 0) ? \Wallee\Sdk\Model\LineItemType::FEE : \Wallee\Sdk\Model\LineItemType::DISCOUNT);

                if (!$lineItem->valid()) {
                    throw new \Exception('Adjustment LineItem payload invalid:' . json_encode($lineItem->listInvalidProperties()));
                }
                $lineItems[] = $lineItem;
            }
        }

        return self::ensureUniqueIds($lineItems);
    }

    /**
     * Ensures uniqueness of the line items.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     *
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     * @throws \Exception
     */
    public static function ensureUniqueIds(array $lineItems)
    {
        $uniqueIds = array();
        foreach ($lineItems as $lineItem) {
            $uniqueId = $lineItem->getUniqueId();
            if (empty($uniqueId)) {
                $uniqueId = preg_replace("/[^a-z0-9]/", '', Tools::strtolower($lineItem->getSku()));
            }
            if (empty($uniqueId)) {
                throw new Exception("There is an invoice item without unique id.");
            }
            if (isset($uniqueIds[$uniqueId])) {
                $backup = $uniqueId;
                $uniqueId = $uniqueId . '_' . $uniqueIds[$uniqueId];
                $uniqueIds[$backup] ++;
            } else {
                $uniqueIds[$uniqueId] = 1;
            }
            $lineItem->setUniqueId($uniqueId);
        }
        return $lineItems;
    }

    /**
     * Returns the amount of the line item's reductions.
     *
     * @param \Wallee\Sdk\Model\LineItem[] $lineItems
     * @param \Wallee\Sdk\Model\LineItemReduction[] $reductions
     * @return float
     */
    public static function getReductionAmount(array $lineItems, array $reductions)
    {
        $lineItemMap = array();
        foreach ($lineItems as $lineItem) {
            $lineItemMap[$lineItem->getUniqueId()] = $lineItem;
        }
        $amount = 0;
        foreach ($reductions as $reduction) {
            $lineItem = $lineItemMap[$reduction->getLineItemUniqueId()];
            $amount += $lineItem->getUnitPriceIncludingTax() * $reduction->getQuantityReduction();
            $amount += $reduction->getUnitPriceReduction() *
                ($lineItem->getQuantity() - $reduction->getQuantityReduction());
        }
        return $amount;
    }

    public static function updateCartMeta(Cart $cart, $key, $value)
    {
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'wle_cart_meta (cart_id, meta_key, meta_value) VALUES ("' . (int) $cart->id .
            '", "' . pSQL($key) . '", "' . pSQL(WalleeTools::base64Encode(serialize($value))) .
            '") ON DUPLICATE KEY UPDATE meta_value = "' .
            pSQL(WalleeTools::base64Encode(serialize($value))) . '";'
        );
    }

    public static function getCartMeta(Cart $cart, $key)
    {
        $value = Db::getInstance()->getValue(
            'SELECT meta_value FROM ' . _DB_PREFIX_ . 'wle_cart_meta WHERE cart_id = "' . (int) $cart->id .
            '" AND meta_key = "' . pSQL($key) . '";',
            false
        );
        if ($value !== false) {
            $decoded = WalleeTools::base64Decode($value, true);
            if ($decoded === false) {
                $decoded = $value;
            }
            return unserialize($decoded);
        }
        return null;
    }

    public static function clearCartMeta(Cart $cart, $key)
    {
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'wle_cart_meta WHERE cart_id = "' . (int) $cart->id . '" AND meta_key = "' .
            pSQL($key) . '";',
            false
        );
    }

    /**
     * Returns the translation in the given language.
     *
     * @param
     *            array($language => $transaltion) $translatedString
     * @param int|string $language
     *            the language id or the ietf code
     * @return string
     */
    public static function translate($translatedString, $language = null)
    {
        if (is_string($translatedString)) {
            return $translatedString;
        }

        if ($language === null) {
            $language = Context::getContext()->language->language_code;
        } elseif ($language instanceof Language) {
            $language = $language->language_code;
        } elseif (ctype_digit($language)) {
            $language = self::convertLanguageIdToIETF($language);
        }

        $language = str_replace('_', '-', $language);
        if (isset($translatedString[$language])) {
            return $translatedString[$language];
        }
        try {
            /* @var WalleeProviderLanguage $language_provider */
            $languageProvider = WalleeProviderLanguage::instance();
            $primaryLanguage = $languageProvider->findPrimary($language);
            if ($primaryLanguage && isset($translatedString[$primaryLanguage->getIetfCode()])) {
                return $translatedString[$primaryLanguage->getIetfCode()];
            }
        } catch (Exception $e) {
        }
        if (isset($translatedString['en-US'])) {
            return $translatedString['en-US'];
        }

        return null;
    }

    /**
     * Returns the URL to a resource on wallee in the given context (space, space view, language).
     *
     * @param string $path
     * @param string $language
     * @param int $spaceId
     * @param int $spaceViewId
     * @return string
     */
    public static function getResourceUrl($base, $path, $language = null, $spaceId = null, $spaceViewId = null)
    {
        if (empty($base)) {
            $url = self::getBaseGatewayUrl();
        } else {
            $url = $base;
        }
        if (! empty($language)) {
            $url .= '/' . str_replace('_', '-', $language);
        }
        if (! empty($spaceId)) {
            $url .= '/s/' . $spaceId;
        }
        if (! empty($spaceViewId)) {
            $url .= '/' . $spaceViewId;
        }
        $url .= '/resource/' . $path;
        return $url;
    }

    public static function calculateCartHash(Cart $cart)
    {
        $toHash = $cart->getOrderTotal(true, Cart::BOTH) . ';';
        $summary = $cart->getSummaryDetails(null, true);
        foreach ($summary['products'] as $productItem) {
            $toHash .= ((float) $productItem['total_wt']) . '-' . $productItem['reference'] . '-' .
                $productItem['quantity'] . ';';
        }
        // Add shipping costs
        $toHash .= ((float) $summary['total_shipping']) . '-' . ((float) $summary['total_shipping_tax_exc']) . ';';
        // Add wrapping costs
        $toHash .= ((float) $summary['total_wrapping']) . '-' . ((float) $summary['total_wrapping_tax_exc']) . ';';
        // Add discounts
        if (count($summary['discounts']) > 0) {
            foreach ($summary['discounts'] as $discount) {
                $toHash .= ((float) $discount['value_real']) . '-' . $discount['id_cart_rule'] . ';';
            }
        }

        return WalleeTools::hashHmac('sha256', $toHash, $cart->secure_key);
    }

    /**
     * Get the Module instance
     */
    public static function getModuleInstance()
    {
        return Module::getInstanceByName('wallee');
    }

    public static function updateOrderMeta(Order $order, $key, $value)
    {
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'wle_order_meta (order_id, meta_key, meta_value) VALUES ("' . (int) $order->id .
            '", "' . pSQL($key) . '", "' . pSQL(serialize($value)) .
            '") ON DUPLICATE KEY UPDATE meta_value = "' . pSQL(serialize($value)) . '";'
        );
    }

    public static function getOrderMeta(Order $order, $key)
    {
        $value = Db::getInstance()->getValue(
            'SELECT meta_value FROM ' . _DB_PREFIX_ . 'wle_order_meta WHERE order_id = "' . (int) $order->id .
            '" AND meta_key = "' . pSQL($key) . '";',
            false
        );
        if ($value !== false) {
            return unserialize($value);
        }
        return null;
    }

    public static function clearOrderMeta(Order $order, $key)
    {
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'wle_order_meta WHERE order_id = "' . (int) $order->id . '" AND meta_key = "' .
            pSQL($key) . '";',
            false
        );
    }

    public static function storeOrderEmails(Order $order, $mails)
    {
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'wle_order_meta (order_id, meta_key, meta_value) VALUES ("' . (int) $order->id .
            '", "' . pSQL('mails') . '", "' .
            pSQL(WalleeTools::base64Encode(serialize($mails))) .
            '") ON DUPLICATE KEY UPDATE meta_value = "' .
            pSQL(WalleeTools::base64Encode(serialize($mails))) . '";'
        );
    }

    public static function getOrderEmails(Order $order)
    {
        class_exists('Mail');
        $value = Db::getInstance()->getValue(
            'SELECT meta_value FROM ' . _DB_PREFIX_ . 'wle_order_meta WHERE order_id = "' . (int) $order->id .
            '" AND meta_key = "' . pSQL('mails') . '";',
            false
        );
        if ($value !== false) {
            return unserialize(WalleeTools::base64Decode($value));
        }
        return array();
    }

    public static function deleteOrderEmails(Order $order)
    {
        self::clearOrderMeta($order, 'mails');
    }

    /**
     * Returns the security hash of the given data.
     *
     * @param string $data
     * @return string
     */
    public static function computeOrderSecret(Order $order)
    {
        return WalleeTools::hashHmac('sha256', $order->id, $order->secure_key, false);
    }

    /**
     * Sorts an array of WalleeModelMethodconfiguration by their sort order
     *
     * @param WalleeModelMethodconfiguration[] $configurations
     *
     * @return array
     */
    public static function sortMethodConfiguration(array $configurations)
    {
        usort(
            $configurations,
            function ($a, $b) {
                if ($a->getSortOrder() == $b->getSortOrder()) {
                    return $a->getConfigurationName() > $b->getConfigurationName();
                }
                return $a->getSortOrder() > $b->getSortOrder();
            }
        );
        return $configurations;
    }

    /**
     * Returns the translated name of the transaction's state.
     *
     * @return string
     */
    public static function getTransactionState(WalleeModelTransactioninfo $info)
    {
        switch ($info->getState()) {
            case \Wallee\Sdk\Model\TransactionState::AUTHORIZED:
                return self::getModuleInstance()->l('Authorized', 'helper');
            case \Wallee\Sdk\Model\TransactionState::COMPLETED:
                return self::getModuleInstance()->l('Completed', 'helper');
            case \Wallee\Sdk\Model\TransactionState::CONFIRMED:
                return self::getModuleInstance()->l('Confirmed', 'helper');
            case \Wallee\Sdk\Model\TransactionState::DECLINE:
                return self::getModuleInstance()->l('Decline', 'helper');
            case \Wallee\Sdk\Model\TransactionState::FAILED:
                return self::getModuleInstance()->l('Failed', 'helper');
            case \Wallee\Sdk\Model\TransactionState::FULFILL:
                return self::getModuleInstance()->l('Fulfill', 'helper');
            case \Wallee\Sdk\Model\TransactionState::PENDING:
                return self::getModuleInstance()->l('Pending', 'helper');
            case \Wallee\Sdk\Model\TransactionState::PROCESSING:
                return self::getModuleInstance()->l('Processing', 'helper');
            case \Wallee\Sdk\Model\TransactionState::VOIDED:
                return self::getModuleInstance()->l('Voided', 'helper');
            default:
                return self::getModuleInstance()->l('Unknown State', 'helper');
        }
    }

    /**
     * Returns the URL to the transaction detail view in wallee.
     *
     * @return string
     */
    public static function getTransactionUrl(WalleeModelTransactioninfo $info)
    {
        return self::getBaseGatewayUrl() . '/s/' . $info->getSpaceId() . '/payment/transaction/view/' .
            $info->getTransactionId();
    }

    /**
     * Returns the URL to the refund detail view in wallee.
     *
     * @return string
     */
    public static function getRefundUrl(WalleeModelRefundjob $refundJob)
    {
        return self::getBaseGatewayUrl() . '/s/' . $refundJob->getSpaceId() . '/payment/refund/view/' .
            $refundJob->getRefundId();
    }

    /**
     * Returns the URL to the completion detail view in wallee.
     *
     * @return string
     */
    public static function getCompletionUrl(WalleeModelCompletionjob $completion)
    {
        return self::getBaseGatewayUrl() . '/s/' . $completion->getSpaceId() . '/payment/completion/view/' .
            $completion->getCompletionId();
    }

    /**
     * Returns the URL to the void detail view in wallee.
     *
     * @return string
     */
    public static function getVoidUrl(WalleeModelVoidjob $void)
    {
        return self::getBaseGatewayUrl() . '/s/' . $void->getSpaceId() . '/payment/void/view/' . $void->getVoidId();
    }

    /**
     * Returns the charge attempt's labels by their groups.
     *
     * @return \Wallee\Sdk\Model\Label[]
     */
    public static function getGroupedChargeAttemptLabels(WalleeModelTransactioninfo $info)
    {
        try {
            $labelDescriptionProvider = WalleeProviderLabeldescription::instance();
            $labelDescriptionGroupProvider = WalleeProviderLabeldescription::instance();

            $labelsByGroupId = array();
            foreach ($info->getLabels() as $descriptorId => $value) {
                $descriptor = $labelDescriptionProvider->find($descriptorId);
                if ($descriptor &&
                    $descriptor->getCategory() == \Wallee\Sdk\Model\LabelDescriptorCategory::HUMAN) {
                    $labelsByGroupId[$descriptor->getGroup()][] = array(
                        'descriptor' => $descriptor,
                        'translatedName' => WalleeHelper::translate($descriptor->getName()),
                        'value' => $value
                    );
                }
            }

            $labelsByGroup = array();
            foreach ($labelsByGroupId as $groupId => $labels) {
                $group = $labelDescriptionGroupProvider->find($groupId);
                if ($group) {
                    usort(
                        $labels,
                        function ($a, $b) {
                            return $a['descriptor']->getWeight() - $b['descriptor']->getWeight();
                        }
                    );
                    $labelsByGroup[] = array(
                        'group' => $group,
                        'id' => $group->getId(),
                        'translatedTitle' => WalleeHelper::translate($group->getName()),
                        'labels' => $labels
                    );
                }
            }
            usort($labelsByGroup, function ($a, $b) {
                return $a['group']->getWeight() - $b['group']->getWeight();
            });
            return $labelsByGroup;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Returns the transaction info for the given orderId.
     * If the order id is not associated with a wallee transaciton it returns null
     *
     * @return WalleeModelTransactioninfo | null
     */
    public static function getTransactionInfoForOrder($order)
    {
        if (! $order->module == 'wallee') {
            return null;
        }
        $searchId = $order->id;

        $mainOrder = self::getOrderMeta($order, 'walleeMainOrderId');
        if ($mainOrder !== null) {
            $searchId = $mainOrder;
        }
        $info = WalleeModelTransactioninfo::loadByOrderId($searchId);
        if ($info->getId() == null) {
            return null;
        }
        return $info;
    }

    public static function cleanExceptionMessage($message)
    {
        return preg_replace("/^\[[A-Fa-f\d\-]+\] /", "", $message);
    }

    public static function generateUUID()
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    public static function getMaxExecutionTime()
    {
        $maxExecutionTime = ini_get('max_execution_time');
        
        // Returns the default value, in case the ini_get fails.
        if ($maxExecutionTime === null || empty($maxExecutionTime) || $maxExecutionTime < 0) {
            return 30;
        } else {
            return intval($maxExecutionTime);
        }
    }

	/**
	 * @return array
	 */
	protected static function getDefaultHeaderData()
	{
		$shop_version = _PS_VERSION_;
        $version_array = explode('.', $shop_version, 3);
        if (count($version_array) < 2) {
            // The format in _PS_VERSION_ has changed and it's unknown to us.
            return [];

        }
        $major_version = current($version_array);
        $minor_version = next($version_array);
		return [
			self::$shop_system             => 'prestashop',
			self::$shop_system_version     => $shop_version,
			self::$shop_system_and_version => 'prestashop-' . $major_version . '.' . $minor_version,
		];
	}
}
