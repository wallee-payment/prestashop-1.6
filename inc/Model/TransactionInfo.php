<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

class Wallee_Model_TransactionInfo extends ObjectModel
{

    public $id_transaction_info;

    public $transaction_id;
    
    public $state;

    public $space_id;
    
    public $space_view_id;

    public $language;
    
    public $currency;

    public $authorization_amount;

    public $image;

    public $labels;
    
    public $payment_method_id;
    
    public $connector_id;
    
    public $order_id;
    
    public $failure_reason;
    
    public $locked_at;
    
    public $date_add;

    public $date_upd;

    
    /**
     *
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'wle_transaction_info',
        'primary' => 'id_transaction_info',
        'fields' => array(
            'transaction_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'state' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),
            'space_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
                'required' => true
            ),
            'space_view_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
            ),            
            'language' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),
            'currency' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255
            ),            
            'authorization_amount' => array(
                'type' => self::TYPE_FLOAT,
                'validate' => 'isAnything'
            ),
            'image' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything',
                'size' => 512
            ),
            'labels' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything',
            ),
            'payment_method_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
            ),
            'connector_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isAnything',
            ),
            'order_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ),
            'failure_reason' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isAnything',
            ),
            'locked_at' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isAnything',
                'copy_post' => false
            ),
            'date_add' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'copy_post' => false
            ),
            'date_upd' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'copy_post' => false
            )
        )
    );
    
    
    public function getId()
    {
        return $this->id;
    }
    
    
    public function getTransactionId(){
        return $this->transaction_id;
    }
    
    public function setTransactionId($id){
        $this->transaction_id = $id;
    }
    
    public function getState(){
        return $this->state;
    }
    
    public function setState($state){
        $this->state = $state;
    }
    
    public function getSpaceId(){
        return $this->space_id;
    }
    
    public function setSpaceId($id){
        $this->space_id = $id;
    }
    
    public function getSpaceViewId(){
        return $this->space_view_id;
    }
    
    public function setSpaceViewId($id){
        $this->space_view_id = $id;
    }
    
    public function getLanguage(){
        return $this->language;
    }
    
    public function setLanguage($language){
        $this->language = $language;
    }
    
    public function getCurrency(){
        return $this->currency;
    }
    
    public function setCurrency($currency){
        $this->currency = $currency;
    }
    
    public function getAuthorizationAmount(){
        return $this->authorization_amount;
    }
    
    public function setAuthorizationAmount($amount){
        $this->authorization_amount = $amount;
    }
    
    public function getImage(){
        return $this->image;
    }
    
    public function setImage($image){
        $this->image = $image;
    }
    
    public function getLabels(){
        return unserialize($this->labels);
    }
    
    public function setLabels(array $labels){
        $this->labels = serialize($labels);
    }
    
    public function getPaymentMethodId(){
        return $this->payment_method_id;
    }
    
    public function setPaymentMethodId($id){
        $this->payment_method_id = $id;
    }
    
    public function getConnectorId($id){
        return $this->connector_id;
    }
    
    public function setConnectorId($id){
        $this->connector_id = $id;
    }
    
    public function getOrderId(){
        return $this->order_id;
    }
    
    public function setOrderId($id){
        $this->order_id = $id;
    }
    
    public function getFailureReason(){
        return unserialize($this->failure_reason);
    }
    
    public function setFailureReason($failureReason){
        $this->failure_reason = serialize($failureReason);
    }
    
    /**
     * 
     * @param int $orderId
     * @return Wallee_Model_TransactionInfo
     */
    public static function loadByOrderId($orderId){
        $transactionInfos = new PrestaShopCollection('Wallee_Model_TransactionInfo');
        $transactionInfos->where('order_id', '=', $orderId);
        $result = $transactionInfos->getFirst();
        if($result === false){
            $result = new Wallee_Model_TransactionInfo();
        }
        return $result;
    }
    
    /**
     * 
     * @param int $spaceId
     * @param int $transactionId
     * @return Wallee_Model_TransactionInfo
     */
    public static function loadByTransaction($spaceId, $transactionId){
        $transactionInfos = new PrestaShopCollection('Wallee_Model_TransactionInfo');
        $transactionInfos->where('space_id', '=', $spaceId);
        $transactionInfos->where('transaction_id', '=', $transactionId);
        $result = $transactionInfos->getFirst();
        if($result === false){
            $result = new Wallee_Model_TransactionInfo();
        }
        return $result;
    }
}
