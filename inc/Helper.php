<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class Wallee_Helper
{

    private static $apiClient;

    /**
     * Returns the base URL to the gateway.
     *
     * @return string
     */
    public static function getBaseGatewayUrl()
    {
        $url = Configuration::getGlobalValue(Wallee::CK_BASE_URL);
        
        if ($url) {
            return rtrim($url,'/');
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
            $userId = Configuration::getGlobalValue(Wallee::CK_USER_ID);
            $userKey = Configuration::getGlobalValue(Wallee::CK_APP_KEY);
            if (! empty($userId) && ! empty($userKey)) {
                self::$apiClient = new \Wallee\Sdk\ApiClient($userId, $userKey);
                self::$apiClient->setBasePath(self::getBaseGatewayUrl() . '/api');
            } else {
                throw new Wallee_Exception_IncompleteConfig();
            }
        }
        return self::$apiClient;
    }

    public static function resetApiClient()
    {
        self::$apiClient = null;
    }

    
    public static function startDBTransaction(){
        $dbLink = Db::getInstance()->getLink();
        if($dbLink instanceof mysqli){
            $dbLink->begin_transaction();
        }
        elseif($dbLink instanceof PDO){
            $dbLink->beginTransaction();
        }
        else{
            throw new Exception('This module needs a PDO or MYSQLI link to use DB transactions');
        }
    }
    
    public static function commitDBTransaction(){
        $dbLink = Db::getInstance()->getLink();
        if($dbLink instanceof mysqli){
            $dbLink->commit();
        }
        elseif($dbLink instanceof PDO){
            $dbLink->commit();
        }
    }
    
    public static function rollbackDBTransaction(){
        $dbLink = Db::getInstance()->getLink();
        if($dbLink instanceof mysqli){
            $dbLink->rollback();
        }
        elseif($dbLink instanceof PDO){
            $dbLink->rollBack();
        }
    }
    
    /**
     * Create a lock to prevent concurrency.
     */
    public static function lockByTransactionId($spaceId, $transactionId)
    {
        Db::getInstance()->getLink()->query(
            'SELECT locked_at FROM ' . _DB_PREFIX_ .
            'wle_transaction_info WHERE transaction_id = "' . pSQL($transactionId) .
            '" AND space_id = "' . pSQL($spaceId) . '" FOR UPDATE;');
        
        Db::getInstance()->getLink()->query('UPDATE ' . _DB_PREFIX_ .
            'wle_transaction_info SET locked_at = "'.pSQL(date('Y-m-d H:i:s')).'" WHERE transaction_id = "' . pSQL($transactionId) .
            '" AND space_id = "' . pSQL($spaceId) . '";');
    }

    /**
     * Returns the fraction digits of the given currency.
     *
     * @param string $currencyCode
     * @return number
     */
    public static function getCurrencyFractionDigits($currencyCode)
    {
        /* @var Wallee_Provider_Currency $currency_provider */
        $currencyProvider = Wallee_Provider_Currency::instance();
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

    public static function encodeObjectForStorage($value)
    {
        return serialize($value);
    }

    public static function decodeObjectFromStorage($value)
    {
        return unserialize($value);
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
     * @param string $currency
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public static function cleanupLineItems(array $lineItems, $expectedSum, $currencyCode)
    {
        $effectiveSum = self::roundAmount(self::getTotalAmountIncludingTax($lineItems),
            $currencyCode);
        $diff = self::roundAmount($expectedSum, $currencyCode) - $effectiveSum;
        if ($diff != 0) {
            $lineItem = new \Wallee\Sdk\Model\LineItemCreate();
            $lineItem->setAmountIncludingTax(self::roundAmount($diff, $currencyCode));
            $lineItem->setName(self::getModuleInstance()->l('Rounding Adjustment','helper'));
            $lineItem->setQuantity(1);
            $lineItem->setSku('rounding-adjustment');
            $lineItem->setType(
                $diff < 0 ? \Wallee\Sdk\Model\LineItemType::DISCOUNT : \Wallee\Sdk\Model\LineItemType::FEE);
            $lineItem->setUniqueId('rounding-adjustment');
            $lineItems[] = $lineItem;
        }
        
        return self::ensureUniqueIds($lineItems);
    }

    /**
     * Ensures uniqueness of the line items.
     *
     * @param \Wallee\Sdk\Model\LineItemCreate[] $lineItems
     * @return \Wallee\Sdk\Model\LineItemCreate[]
     */
    public static function ensureUniqueIds(array $lineItems)
    {
        $uniqueIds = array();
        foreach ($lineItems as $lineItem) {
            $uniqueId = $lineItem->getUniqueId();
            if (empty($uniqueId)) {
                $uniqueId = preg_replace("/[^a-z0-9]/", '', strtolower($lineItem->getSku()));
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
            'INSERT INTO ' . _DB_PREFIX_ .
            'wle_cart_meta (cart_id, meta_key, meta_value) VALUES ("' . pSQL($cart->id) .
            '", "' . pSQL($key) . '", "' . pSQL(serialize($value)) .
            '") ON DUPLICATE KEY UPDATE meta_value = "' . pSQL(serialize($value)) . '";');
    }
    
    public static function getCartMeta(Cart $cart, $key)
    {
        $value = Db::getInstance()->getValue(
            'SELECT meta_value FROM ' . _DB_PREFIX_ . 'wle_cart_meta WHERE cart_id = "' .
            pSQL($cart->id) . '" AND meta_key = "' . pSQL($key) . '";', false);
        if ($value !== false) {
            return unserialize($value);
        }
        return null;
    }
    
    public static function clearCartMeta(Cart $cart, $key)
    {
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'wle_cart_meta WHERE cart_id = "' . pSQL($cart->id) .
            '" AND meta_key = "' . pSQL($key) . '";', false);
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
        if(is_string($translatedString)){
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
            /* @var Wallee_Provider_Language $language_provider */
            $languageProvider = Wallee_Provider_Language::instance();
            $primaryLanguage = $languageProvider->findPrimary($language);
            if ($primaryLanguage && isset($translatedString[$primaryLanguage->getIetfCode()])) {
                return $translatedString[$primaryLanguage->getIetfCode()];
            }
        } catch (Exception $e) {}
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
        if(empty($base)){
            $url = self::getBaseGatewayUrl();
        }
        else{
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
        $summary = $cart->getSummaryDetails();
        foreach ($summary['products'] as $productItem) {
            $toHash .= floatval($productItem['total_wt']) . '-' . $productItem['reference'] . '-' .
                 $productItem['quantity'] . ';';
        }
        // Add shipping costs
        $toHash .= floatval($summary['total_shipping']) . '-' .
             floatval($summary['total_shipping_tax_exc']) . ';';
        // Add wrapping costs
        $toHash .= floatval($summary['total_wrapping']) . '-' .
             floatval($summary['total_wrapping_tax_exc']) . ';';
        // Add discounts
        if (count($summary['discounts']) > 0) {
            foreach ($summary['discounts'] as $discount) {
                $toHash .= floatval($discount['value_real']) . '-' . $discount['id_cart_rule'] . ';';
            }
        }
        
        return hash_hmac('sha256', $toHash, $cart->secure_key);
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
            'INSERT INTO ' . _DB_PREFIX_ .
                 'wle_order_meta (order_id, meta_key, meta_value) VALUES ("' . pSQL($order->id) .
                 '", "' . pSQL($key) . '", "' . pSQL(serialize($value)) .
                 '") ON DUPLICATE KEY UPDATE meta_value = "' . pSQL(serialize($value)) . '";');
    }

    public static function getOrderMeta(Order $order, $key)
    {
        $value = Db::getInstance()->getValue(
            'SELECT meta_value FROM ' . _DB_PREFIX_ . 'wle_order_meta WHERE order_id = "' .
                 pSQL($order->id) . '" AND meta_key = "' . pSQL($key) . '";', false);
        if ($value !== false) {
            return unserialize($value);
        }
        return null;
    }

    public static function clearOrderMeta(Order $order, $key)
    {
        Db::getInstance()->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'wle_order_meta WHERE order_id = "' . pSQL($order->id) .
                 '" AND meta_key = "' . pSQL($key) . '";', false);
    }
    
    public static function storeOrderEmails(Order $order, $mails)
    {
        Db::getInstance()->execute(
            'INSERT INTO ' . _DB_PREFIX_ .
            'wle_order_meta (order_id, meta_key, meta_value) VALUES ("' . pSQL($order->id) .
            '", "' . pSQL('mails') . '", "' . pSQL(base64_encode(serialize($mails))) .
            '") ON DUPLICATE KEY UPDATE meta_value = "' . pSQL(base64_encode(serialize($mails))) . '";');
    }
    
    public static function getOrderEmails(Order $order)
    {
        class_exists('Mail');
        $value = Db::getInstance()->getValue(
            'SELECT meta_value FROM ' . _DB_PREFIX_ . 'wle_order_meta WHERE order_id = "' .
            pSQL($order->id) . '" AND meta_key = "' . pSQL('mails') . '";', false);
        if ($value !== false) {
            return unserialize(base64_decode($value));
        }
        return array();
    }
    
    public static function deleteOrderEmails(Order $order){
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
        return hash_hmac('sha256', $order->id, $order->secure_key, false);
    }
    
    
    /**
     * Sorts an array of Wallee_Model_MethodConfiguration by their sort order
     * 
     * @param Wallee_Model_MethodConfiguration[] $configurations
     */
    public static function sortMethodConfiguration(array $configurations){
        usort($configurations, function($a, $b)
        {
            if ($a->getSortOrder() == $b->getSortOrder()){
                return $a->getConfigurationName() > $b->getConfigurationName();
                
            }
            return $a->getSortOrder() > $b->getSortOrder();
        });
        return $configurations;
        
        
    }
    
    
    /**
     * Returns the translated name of the transaction's state.
     *
     * @return string
     */
    public static function getTransactionState(Wallee_Model_TransactionInfo $info){
        switch ($info->getState()) {
            case \Wallee\Sdk\Model\TransactionState::AUTHORIZED:
                return self::getModuleInstance()->l('Authorized','helper');
            case \Wallee\Sdk\Model\TransactionState::COMPLETED:
                return self::getModuleInstance()->l('Completed','helper');
            case \Wallee\Sdk\Model\TransactionState::CONFIRMED:
                return self::getModuleInstance()->l('Confirmed','helper');
            case \Wallee\Sdk\Model\TransactionState::DECLINE:
                return self::getModuleInstance()->l('Decline','helper');
            case \Wallee\Sdk\Model\TransactionState::FAILED:
                return self::getModuleInstance()->l('Failed','helper');
            case \Wallee\Sdk\Model\TransactionState::FULFILL:
                return self::getModuleInstance()->l('Fulfill','helper');
            case \Wallee\Sdk\Model\TransactionState::PENDING:
                return self::getModuleInstance()->l('Pending','helper');
            case \Wallee\Sdk\Model\TransactionState::PROCESSING:
                return self::getModuleInstance()->l('Processing','helper');
            case \Wallee\Sdk\Model\TransactionState::VOIDED:
                return self::getModuleInstance()->l('Voided','helper');
            default:
                return self::getModuleInstance()->l('Unknown State','helper');
        }
    }
    
    /**
     * Returns the URL to the transaction detail view in wallee.
     *
     * @return string
     */
    public static function getTransactionUrl(Wallee_Model_TransactionInfo $info){
        return self::getBaseGatewayUrl() . '/s/' . $info->getSpaceId() . '/payment/transaction/view/' .
            $info->getTransactionId();
    }
    
    /**
     * Returns the URL to the refund detail view in wallee.
     *
     * @return string
     */
    public static function getRefundUrl(Wallee_Model_RefundJob $refundJob){
        return self::getBaseGatewayUrl() . '/s/' . $refundJob->getSpaceId() . '/payment/refund/view/' .
            $refundJob->getRefundId();
    }
    
    /**
     * Returns the URL to the completion detail view in wallee.
     *
     * @return string
     */
    public static function getCompletionUrl(Wallee_Model_CompletionJob $completion){
        return self::getBaseGatewayUrl() . '/s/' . $completion->getSpaceId() . '/payment/completion/view/' .
            $completion->getCompletionId();
    }
    
    /**
     * Returns the URL to the void detail view in wallee.
     *
     * @return string
     */
    public static function getVoidUrl(Wallee_Model_VoidJob $void){
        return self::getBaseGatewayUrl() . '/s/' . $void->getSpaceId() . '/payment/void/view/' .
            $void->getVoidId();
    }
    
    
    /**
     * Returns the charge attempt's labels by their groups.
     *
     * @return \Wallee\Sdk\Model\Label[]
     */
    public static function getGroupedChargeAttemptLabels(Wallee_Model_TransactionInfo $info){
        try {
            $labelDescriptionProvider = Wallee_Provider_LabelDescription::instance();
            $labelDescriptionGroupProvider = Wallee_Provider_LabelDescriptionGroup::instance();
            
            $labelsByGroupId = array();
            foreach ($info->getLabels() as $descriptorId => $value) {
                $descriptor = $labelDescriptionProvider->find($descriptorId);
                if ($descriptor) {
                    $labelsByGroupId[$descriptor->getGroup()][] = array(
                        'descriptor' => $descriptor,
                        'translatedName' => Wallee_Helper::translate($descriptor->getName()),
                        'value' => $value
                    );
                }
            }
            
            $labelsByGroup = array();
            foreach ($labelsByGroupId as $groupId => $labels) {
                $group = $labelDescriptionGroupProvider->find($groupId);
                if ($group) {
                    usort($labels, function ($a, $b){
                        return $a['descriptor']->getWeight() - $b['descriptor']->getWeight();
                    });
                        $labelsByGroup[] = array(
                            'group' => $group,
                            'id' => $group->getId(),
                            'translatedTitle' => Wallee_Helper::translate($group->getName()),
                            'labels' => $labels
                        );
                }
            }
            usort($labelsByGroup, function ($a, $b){
                return $a['group']->getWeight() - $b['group']->getWeight();
            });
            return $labelsByGroup;
        }
        catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Returns the transaction info for the given orderId.
     * If the order id is not associated with a wallee transaciton it returns null
     * 
     * @return Wallee_Model_TransactionInfo | null
     */
    public static function getTransactionInfoForOrder($order){
        if (! $order->module == 'wallee') {
            return null;
        }
        $searchId = $order->id;
        
        $mainOrder = self::getOrderMeta($order, 'walleeMainOrderId');
        if ($mainOrder !== null) {
            $searchId = $mainOrder;
        }
        $info = Wallee_Model_TransactionInfo::loadByOrderId($searchId);
        if ($info->getId() == null) {
            return null;
        }
        return $info;
    }
    
    public static function cleanExceptionMessage($message){
        return preg_replace("/^\[[A-Fa-f\d\-]+\] /", "", $message);
    }
    
    public static function generateUUID(){
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
}
