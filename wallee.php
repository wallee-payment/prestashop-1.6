<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

define('WALLEE_VERSION', '1.0.0');

require_once (__DIR__ . DIRECTORY_SEPARATOR . 'wallee_autoloader.php');
require_once (__DIR__ . DIRECTORY_SEPARATOR . 'wallee-sdk' . DIRECTORY_SEPARATOR . 'autoload.php');

class Wallee extends PaymentModule
{

    const CK_BASE_URL = 'WLE_BASE_GATEWAY_URL';

    const CK_USER_ID = 'WLE_USER_ID';

    const CK_APP_KEY = 'WLE_APP_KEY';

    const CK_SPACE_ID = 'WLE_SPACE_ID';

    const CK_SPACE_VIEW_ID = 'WLE_SPACE_VIEW_ID';

    const CK_MAIL = 'WLE_SHOP_EMAIL';

    const CK_INVOICE = 'WLE_INVOICE_DOWNLOAD';

    const CK_PACKING_SLIP = 'WLE_PACKING_SLIP_DOWNLOAD';

    const CK_FEE_ITEM = 'WLE_FEE_ITEM';

    const CK_SHOW_CART = 'WLE_SHOW_CART';

    const CK_SHOW_TOS = 'WLE_SHOW_TOS';

    const CK_REMOVE_TOS = 'WLE_REMOVE_TOS';

    const CK_CRONJOB_TIMESTAMP = 'WLE_CRONJOB_TIMESTAMP';

    const CK_CRONJOB_RUNNING = 'WLE_CRONJOB_RUNNING';

    const CRON_MIN_INTERVAL_SEC = 300;

    const MYSQL_DUPLICATE_CONSTRAINT_ERROR_CODE = 1062;

    const TOTAL_MODE_BOTH_INC = 0;

    const TOTAL_MODE_BOTH_EXC = 1;

    const TOTAL_MODE_PRODUCTS_INC = 2;

    const TOTAL_MODE_PRODUCTS_EXC = 3;

    const TOTAL_MODE_WITHOUT_SHIPPING_INC = 4;

    const TOTAL_MODE_WITHOUT_SHIPPING_EXC = 5;

    private static $recordMailMessages = false;

    private static $recordedMailMessages = array();

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'wallee';
        $this->tab = 'payments_gateways';
        $this->version = WALLEE_VERSION;
        $this->author = 'Customweb GmbH';
        $this->bootstrap = true;
        
        parent::__construct();
        
        $this->displayName = 'wallee';
        $this->description = $this->l(
            'This PrestaShop module enables to process payments with wallee.');
        $this->confirmUninstall = $this->l(
            'Are you sure you want to uninstall the wallee module?');
        
        // Remove Fee Item
        if (isset($this->context->cart)) {
            $feeProductId = Configuration::get(self::CK_FEE_ITEM);
            if ($feeProductId != null) {
                $defaultAttributeId = Product::getDefaultAttribute($feeProductId);
                SpecificPrice::deleteByIdCart($this->context->cart->id, $feeProductId,
                    $defaultAttributeId);
                $this->context->cart->deleteProduct($feeProductId, $defaultAttributeId);
            }
        }
        if (! empty($this->context->cookie->wallee_error)) {
            $errors = $this->context->cookie->wallee_error;
            if (is_string($errors)) {
                $this->context->controller->errors[] = $errors;
            }
            elseif (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->context->controller->errors[] = $error;
                }
            }
            unset($_SERVER['HTTP_REFERER']); // To disable the back button in the error message
            $this->context->cookie->wallee_error = null;
        }
    }

    public function install()
    {
        if (! $this->checkRequirements()) {
            return false;
        }
        if (! parent::install()) {
            return false;
        }
        if (! $this->installHooks()) {
            $this->_errors[] = Tools::displayError('Unable to install hooks.');
            return false;
        }
        if (! $this->installControllers()) {
            $this->_errors[] = Tools::displayError('Unable to install controllers.');
            return false;
        }
        if (! Wallee_Migration::installDb()) {
            $this->_errors[] = Tools::displayError('Unable to install database tables.');
            return false;
        }
        if (! $this->installConfiguration()) {
            $this->_errors[] = Tools::displayError('Unable to install configuration.');
        }
        $this->registerOrderStates();
        return true;
    }

    private function checkRequirements()
    {
        try {
            \Wallee\Sdk\Http\HttpClientFactory::getClient();
        }
        catch (Exception $e) {
            $this->_errors[] = Tools::displayError(
                'Install the PHP cUrl extension or ensure the \'stream_socket_client\' function is available.');
            return false;
        }
        return true;
    }

    protected function installHooks()
    {
        return $this->registerHook('payment') && $this->registerHook('displayPaymentEU') &&
             $this->registerHook('paymentReturn') && $this->registerHook('displayOrderConfirmation') &&
             $this->registerHook('displayHeader') && $this->registerHook('displayMobileHeader') &&
             $this->registerHook('displayTop') && $this->registerHook('displayBackOfficeHeader') &&
             $this->registerHook('actionMailSend') && $this->registerHook('walleeSettingsChanged') &&
             $this->registerHook('walleeCron') && $this->registerHook('displayOrderDetail') &&
             $this->registerHook('actionValidateOrder') &&
             $this->registerHook('displayAdminOrderLeft') && $this->registerHook(
                'displayAdminOrder') && $this->registerHook('actionAdminControllerSetMedia') &&
             $this->registerHook('actionAdminOrdersControllerBefore') &&
             $this->registerHook('actionOrderEdited') &&
             $this->registerHook('actionFrontControllerSetMedia') &&
             $this->registerHook('displayAdminOrderTabOrder') &&
             $this->registerHook('displayAdminOrderContentOrder') &&
             $this->registerHook('displayAdminAfterHeader');
    }

    protected function installControllers()
    {
        $controllers = array(
            'AdminWalleeMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentModules'),
                'name' => $this->l('wallee Payment Methods')
            ),
            'AdminWalleeDocuments' => array(
                'parentId' => - 1, // No Tab in navigation
                'name' => $this->l('wallee Documents')
            ),
            'AdminWalleeOrder' => array(
                'parentId' => - 1, // No Tab in navigation
                'name' => $this->l('wallee Order Management')
            ),
            'AdminWalleeCronJobs' => array(
                'parentId' => Tab::getIdFromClassName('AdminTools'),
                'name' => $this->l('wallee CronJobs')
            )
        );
        foreach ($controllers as $className => $data) {
            if (Tab::getIdFromClassName($className)) {
                continue;
            }
            if (! $this->addTab($className, $data['name'], $data['parentId'])) {
                return false;
            }
        }
        return true;
    }

    protected function addTab($className, $name, $parentId)
    {
        $tab = new Tab();
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->class_name = $className;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $this->l($name);
        }
        return $tab->save();
    }

    protected function installConfiguration()
    {
        return true;
        return Configuration::updateValue(self::CK_MAIL, true) &&
             Configuration::updateValue(self::CK_INVOICE, true) &&
             Configuration::updateValue(self::CK_PACKING_SLIP, true) &&
             Configuration::updateValue(self::CK_SHOW_CART, true) &&
             Configuration::updateValue(self::CK_SHOW_TOS, false) &&
             Configuration::updateValue(self::CK_REMOVE_TOS, false);
    }

    protected function registerOrderStates()
    {
        Wallee_OrderStatus::registerOrderStatus();
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallControllers() &&
             $this->uninstallConfigurationValues();
    }

    protected function uninstallConfigurationValues()
    {
        return true;
         return Configuration::deleteByName(self::CK_USER_ID) &&
             Configuration::deleteByName(self::CK_APP_KEY) &&
             Configuration::deleteByName(self::CK_SPACE_ID) &&
             Configuration::deleteByName(self::CK_SPACE_VIEW_ID) &&
             Configuration::deleteByName(self::CK_MAIL) &&
             Configuration::deleteByName(self::CK_INVOICE) &&
             Configuration::deleteByName(self::CK_PACKING_SLIP) &&
             Configuration::deleteByName(self::CK_FEE_ITEM) &&
             Configuration::deleteByName(self::CK_SHOW_CART) &&
             Configuration::deleteByName(self::CK_SHOW_TOS) &&
             Configuration::deleteByName(self::CK_REMOVE_TOS) &&
             Configuration::deleteByName(Wallee_Service_ManualTask::CONFIG_KEY);
    }

    protected function uninstallControllers()
    {
        $result = true;
        $controllers = array(
            'AdminWalleeMethodSettings',
            'AdminWalleeDocuments',
            'AdminWalleeOrder',
            'AdminWalleeCronJobs'
        );
        foreach ($controllers as $class_name) {
            $id = Tab::getIdFromClassName($class_name);
            if (! $id) {
                continue;
            }
            $tab = new Tab($id);
            if (! Validate::isLoadedObject($tab) || ! $tab->delete()) {
                $result = false;
            }
        }
        return $result;
    }

    public function getContent()
    {
        $output = "";
        if (! Module::isInstalled('mailhook') || ! Module::isEnabled('mailhook')) {
            $error = "<b>".$this->l(
                "The module 'Mail Hook' is not active.")."</b>";
            $error .= "<br/>";
            $error .= $this->l("This module is recommend for handling the shop emails. Otherwise the mail sending behavior may be inappropriate.");
            $error .= "<br/>";
            $error .= $this->l('You can download the module ').
                    ' <a href="https://github.com/wallee-payment/prestashop-mailhook/releases" target="_blank">'.
                    $this->l('here').
                    '</a>.';
            $output .= $this->displayError($error);
        }        
        $languages = $this->context->controller->getLanguages();
        if (Tools::isSubmit('submit' . $this->name . '_all')) {
            $refresh = true;
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                    Configuration::updateGlobalValue(self::CK_USER_ID,
                        Tools::getValue(self::CK_USER_ID));
                    Configuration::updateGlobalValue(self::CK_APP_KEY,
                        Tools::getValue(self::CK_APP_KEY));
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_SPACE_ID,
                        Tools::getValue(self::CK_SPACE_ID));
                    Configuration::updateValue(self::CK_SPACE_VIEW_ID,
                        Tools::getValue(self::CK_SPACE_VIEW_ID));
                    Configuration::updateValue(self::CK_MAIL,
                        Tools::getValue(self::CK_MAIL));
                    Configuration::updateValue(self::CK_FEE_ITEM,
                        Tools::getValue(self::CK_FEE_ITEM));
                    Configuration::updateValue(self::CK_INVOICE,
                        Tools::getValue(self::CK_INVOICE));
                    Configuration::updateValue(self::CK_PACKING_SLIP,
                        Tools::getValue(self::CK_PACKING_SLIP));
                    Configuration::updateValue(self::CK_SHOW_CART,
                        Tools::getValue(self::CK_SHOW_CART));
                    Configuration::updateValue(self::CK_SHOW_TOS,
                        Tools::getValue(self::CK_SHOW_TOS));
                    Configuration::updateValue(self::CK_REMOVE_TOS,
                        Tools::getValue(self::CK_REMOVE_TOS));
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                else {
                    $refresh = false;
                    $output .= $this->displayError(
                        $this->l('You can not store the configuration for Shop Group.'));
                }
            }
            else {
                Configuration::updateGlobalValue(self::CK_USER_ID,
                    Tools::getValue(self::CK_USER_ID));
                Configuration::updateGlobalValue(self::CK_APP_KEY,
                    Tools::getValue(self::CK_APP_KEY));
                Configuration::updateValue(self::CK_SPACE_ID,
                    Tools::getValue(self::CK_SPACE_ID));
                Configuration::updateValue(self::CK_SPACE_VIEW_ID,
                    Tools::getValue(self::CK_SPACE_VIEW_ID));
                Configuration::updateValue(self::CK_MAIL,
                    Tools::getValue(self::CK_MAIL));
                Configuration::updateValue(self::CK_FEE_ITEM,
                    Tools::getValue(self::CK_FEE_ITEM));
                Configuration::updateValue(self::CK_INVOICE,
                    Tools::getValue(self::CK_INVOICE));
                Configuration::updateValue(self::CK_PACKING_SLIP,
                    Tools::getValue(self::CK_PACKING_SLIP));
                Configuration::updateValue(self::CK_SHOW_CART,
                    Tools::getValue(self::CK_SHOW_CART));
                Configuration::updateValue(self::CK_SHOW_TOS,
                    Tools::getValue(self::CK_SHOW_TOS));
                Configuration::updateValue(self::CK_REMOVE_TOS,
                    Tools::getValue(self::CK_REMOVE_TOS));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
            if ($refresh) {
                $error = Hook::exec('walleeSettingsChanged');
                if (! empty($error)) {
                    $output .= $this->displayError($error);
                }
            }
        }
        if (Tools::isSubmit('submit' . $this->name . '_application')) {
            $refresh = true;
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                    Configuration::updateGlobalValue(self::CK_USER_ID,
                        Tools::getValue(self::CK_USER_ID));
                    Configuration::updateGlobalValue(self::CK_APP_KEY,
                        Tools::getValue(self::CK_APP_KEY));
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_SPACE_ID,
                        Tools::getValue(self::CK_SPACE_ID));
                    Configuration::updateValue(self::CK_SPACE_VIEW_ID,
                        Tools::getValue(self::CK_SPACE_VIEW_ID));
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                else {
                    $refresh = false;
                    $output .= $this->displayError(
                        $this->l('You can not store the configuration for Shop Group.'));
                }
            }
            else {
                Configuration::updateGlobalValue(self::CK_USER_ID,
                    Tools::getValue(self::CK_USER_ID));
                Configuration::updateGlobalValue(self::CK_APP_KEY,
                    Tools::getValue(self::CK_APP_KEY));
                Configuration::updateValue(self::CK_SPACE_ID,
                    Tools::getValue(self::CK_SPACE_ID));
                Configuration::updateValue(self::CK_SPACE_VIEW_ID,
                    Tools::getValue(self::CK_SPACE_VIEW_ID));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
            if ($refresh) {
                $error = Hook::exec('walleeSettingsChanged');
                if (! empty($error)) {
                    $output .= $this->displayError($error);
                }
            }
        }
        if (Tools::isSubmit('submit' . $this->name . '_email')) {
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_MAIL,
                        Tools::getValue(self::CK_MAIL));
                    
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                else {
                    $output .= $this->displayError(
                        $this->l(
                            'You can not store the configuration for all Shops or a Shop Group.'));
                }
            }
            else {
                Configuration::updateValue(self::CK_MAIL,
                    Tools::getValue(self::CK_MAIL));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        if (Tools::isSubmit('submit' . $this->name . '_fee_item')) {
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_FEE_ITEM,
                        Tools::getValue(self::CK_FEE_ITEM));
                    
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                else {
                    $output .= $this->displayError(
                        $this->l(
                            'You can not store the configuration for all Shops or a Shop Group.'));
                }
            }
            else {
                Configuration::updateValue(self::CK_FEE_ITEM,
                    Tools::getValue(self::CK_FEE_ITEM));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        if (Tools::isSubmit('submit' . $this->name . '_download')) {
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_INVOICE,
                        Tools::getValue(self::CK_INVOICE));
                    Configuration::updateValue(self::CK_PACKING_SLIP,
                        Tools::getValue(self::CK_PACKING_SLIP));
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                else {
                    $output .= $this->displayError(
                        $this->l(
                            'You can not store the configuration for all Shops or a Shop Group.'));
                }
            }
            else {
                Configuration::updateValue(self::CK_INVOICE,
                    Tools::getValue(self::CK_INVOICE));
                Configuration::updateValue(self::CK_PACKING_SLIP,
                    Tools::getValue(self::CK_PACKING_SLIP));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        if (Tools::isSubmit('submit' . $this->name . '_checkout')) {
            if ($this->context->shop->isFeatureActive()) {
                if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                    Configuration::updateValue(self::CK_SHOW_CART,
                        Tools::getValue(self::CK_SHOW_CART));
                    Configuration::updateValue(self::CK_SHOW_TOS,
                        Tools::getValue(self::CK_SHOW_TOS));
                    Configuration::updateValue(self::CK_REMOVE_TOS,
                        Tools::getValue(self::CK_REMOVE_TOS));
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }
                else {
                    $output .= $this->displayError(
                        $this->l(
                            'You can not store the configuration for all Shops or a Shop Group.'));
                }
            }
            else {
                Configuration::updateGlobalValue(self::CK_SHOW_CART,
                    Tools::getValue(self::CK_SHOW_CART));
                Configuration::updateGlobalValue(self::CK_SHOW_TOS,
                    Tools::getValue(self::CK_SHOW_TOS));
                Configuration::updateValue(self::CK_REMOVE_TOS,
                    Tools::getValue(self::CK_REMOVE_TOS));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output . $this->displayForm();
    }

    private function getFormHelper()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get(
            'PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        
        $helper->identifier = $this->identifier;
        
        $helper->title = $this->displayName;
        
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name .
             '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper;
    }

    private function displayForm()
    {
        $userConfig = array(
            array(
                'type' => 'text',
                'label' => $this->l('User Id'),
                'name' => self::CK_USER_ID,
                'required' => true,
                'col' => 3,
                'lang' => false
            ),
            array(
                'type' => 'wallee_password',
                'label' => $this->l('Application Key'),
                'name' => self::CK_APP_KEY,
                'required' => true,
                'col' => 3,
                'lang' => false
            )
        );
        
        $userInfo = array(
            array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
                'html_content' => '<b>' . $this->l('The User Id needs to be configured globally.') .
                     '</b>'
            ),
            array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
                'html_content' => '<b>' .
                 $this->l('The Application Key needs to be configured globally.') . '</b>'
            )
        );
        
        $spaceConfig = array(
            array(
                'type' => 'text',
                'label' => $this->l('Space Id'),
                'name' => self::CK_SPACE_ID,
                'required' => true,
                'col' => 3,
                'lang' => false
            ),
            array(
                'type' => 'text',
                'label' => $this->l('SpaceView Id'),
                'name' => self::CK_SPACE_VIEW_ID,
                'col' => 3,
                'lang' => false
            )
        
        );
        
        $spaceInfo = array(
            array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
                'html_content' => '<b>' . $this->l('The Space Id needs to be configured per shop.') .
                     '</b>'
            ),
            array(
                'type' => 'html',
                'name' => 'IGNORE',
                'col' => 3,
                'html_content' => '<b>' .
                 $this->l('The Space View Id needs to be configured per shop.') . '</b>'
            
            )
        );
              
        $generalInputs = array_merge($userConfig, $spaceConfig);
        $buttons =  array(array(
            'title' =>$this->l('Save'),
            'class' => 'pull-right',
            'type' => 'input',
            'icon' => 'process-icon-save',
            'name' => 'submit' . $this->name . '_application'
        ));
        
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                $generalInputs = array_merge($userConfig, $spaceInfo);
            }
            elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $generalInputs = array_merge($userInfo, $spaceConfig);
                array_unshift($buttons, array(
                    'title' =>$this->l('Save All'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ));
            }
            else {
                $generalInputs = array_merge($userInfo, $spaceInfo);
                $buttons = array();
            }
        }
        $fieldsForm = array();
        // General Settings
        $fieldsForm[]['form'] = array(
            'legend' => array(
                'title' => $this->l('wallee General Settings')
            ),
            'input' => $generalInputs,
            'buttons' => $buttons
        );        
        
        if (! $this->context->shop->isFeatureActive() ||
            $this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
            $fieldsForm[]['form'] = $this->getCheckoutForm();
            $fieldsForm[]['form'] = $this->getEmailForm();
            $fieldsForm[]['form'] = $this->getFeeForm();
            $fieldsForm[]['form'] = $this->getDocumentForm();
        }
        
        $helper = $this->getFormHelper();
        $helper->tpl_vars['fields_value'] = array_merge(
            $this->getApplicationConfigValues(), 
            $this->getCheckoutConfigValues(),
            $this->getEmailConfigValues(), 
            $this->getFeeItemConfigValues(), 
            $this->getDownloadConfigValues()
        );       
        
        return $helper->generateForm($fieldsForm);
    }

    private function getApplicationConfigValues()
    {
        $values = array();
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_ALL) {
                $values[self::CK_USER_ID] = Configuration::getGlobalValue(
                    self::CK_USER_ID);
                $values[self::CK_APP_KEY] = Configuration::getGlobalValue(
                    self::CK_APP_KEY);
            }
            elseif ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_SPACE_ID] = Configuration::get(
                    self::CK_SPACE_ID);
                $values[self::CK_SPACE_VIEW_ID] = Configuration::get(
                    self::CK_SPACE_VIEW_ID);
            }
        }
        else {
            $values[self::CK_USER_ID] = Configuration::getGlobalValue(
                self::CK_USER_ID);
            $values[self::CK_APP_KEY] = Configuration::getGlobalValue(
                self::CK_APP_KEY);
            $values[self::CK_SPACE_ID] = Configuration::get(self::CK_SPACE_ID);
            $values[self::CK_SPACE_VIEW_ID] = Configuration::get(
                self::CK_SPACE_VIEW_ID);
        }
        return $values;
    }
    
    private function getCheckoutForm(){
        
        $checkoutConfig = array(
            array(
                'type' => 'switch',
                'label' => $this->l('Show Cart Summary'),
                'name' => self::CK_SHOW_CART,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Show')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Hide')
                    )
                ),
                'desc' => $this->l(
                    'Should a cart summary be shown on the payment details input page.'),
                'lang' => false
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Show Terms of Service'),
                'name' => self::CK_SHOW_TOS,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Show')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Hide')
                    )
                ),
                'desc' => $this->l(
                    'Should the Terms of Service be shown and checked on the payment details input page.'),
                'lang' => false
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Remove default Terms of Service'),
                'name' => self::CK_REMOVE_TOS,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Keep')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Remove')
                    )
                ),
                'desc' => $this->l(
                    'Should the default Terms of Service be removed during the checkout. CAUTION: This option will remove the ToS for all payment methods.'),
                'lang' => false
            )
        );
        
        return array(
            'legend' => array(
                'title' => $this->l('Checkout Settings')
            ),
            'input' => $checkoutConfig,
            'buttons' => array(
                array(
                    'title' =>$this->l('Save All'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' =>$this->l('Save'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_checkout'
                )
            )
        );
    }
   
    private function getCheckoutConfigValues()
    {
        $values = array();
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_SHOW_CART] = (bool) Configuration::get(
                    self::CK_SHOW_CART);
                $values[self::CK_SHOW_TOS] = (bool) Configuration::get(
                    self::CK_SHOW_TOS);
                $values[self::CK_REMOVE_TOS] = (bool) Configuration::get(
                    self::CK_REMOVE_TOS);
            }
        }
        else {
            $values[self::CK_SHOW_CART] = (bool) Configuration::getGlobalValue(
                self::CK_SHOW_CART);
            $values[self::CK_SHOW_TOS] = (bool) Configuration::getGlobalValue(
                self::CK_SHOW_TOS);
            $values[self::CK_REMOVE_TOS] = (bool) Configuration::get(
                self::CK_REMOVE_TOS);
        }
        return $values;
    }
    
    private function getEmailForm(){
        
        $emailConfig = array(
            array(
                'type' => 'switch',
                'label' => $this->l('Send Order Emails'),
                'name' => self::CK_MAIL,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Send')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Disabled')
                    )
                ),
                'desc' => $this->l('Send the prestashop order emails.'),
                'lang' => false
            )
        );
        
        return array(
            'legend' => array(
                'title' => $this->l('Order Email Settings')
            ),
            'input' => $emailConfig,
            'buttons' => array(
                array(
                    'title' =>$this->l('Save All'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' =>$this->l('Save'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_email'
                )
            )
        );
    }
    
    private function getEmailConfigValues()
    {
        $values = array();
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_MAIL] = (bool) Configuration::get(
                    self::CK_MAIL);
            }
        }
        else {
            $values[self::CK_MAIL] = (bool) Configuration::get(self::CK_MAIL);
        }
        return $values;
    }
    
    private function getFeeForm(){
        
        $products = Product::getSimpleProducts($this->context->language->id);
        $options = array(
            '-1' => $this->l('None (disables payment fees)')
        );
        
        array_unshift($products,
            array(
                'id_product' => '-1',
                'name' => $this->l('None (disables payment fees')
            ));
        
        $feeItemConfig = array(
            array(
                'type' => 'select',
                'label' => $this->l('Payment Fee Product'),
                'desc' => $this->l(
                    'Select the product that should be inserted into the cart as a payment fee.'),
                'name' => self::CK_FEE_ITEM,
                'options' => array(
                    'query' => $products,
                    'id' => 'id_product',
                    'name' => 'name'
                )
            )
        );
        
        
        return array(
            'legend' => array(
                'title' => $this->l('Fee Item Settings')
            ),
            'input' => $feeItemConfig,
            'buttons' => array(
                array(
                    'title' =>$this->l('Save All'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' =>$this->l('Save'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_fee_item'
                )
            )
        );
    }    
    
    private function getFeeItemConfigValues()
    {
        $values = array();
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_FEE_ITEM] = (int) Configuration::get(
                    self::CK_FEE_ITEM);
            }
        }
        else {
            $values[self::CK_FEE_ITEM] = (bool) Configuration::get(
                self::CK_FEE_ITEM);
        }
        return $values;
    }
    
    private function getDocumentForm(){
        
        $documentConfig = array(
            array(
                'type' => 'switch',
                'label' => $this->l('Invoice Download'),
                'name' => self::CK_INVOICE,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Allow')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Disallow')
                    )
                ),
                'desc' => $this->l('Allow the customers to download the wallee invoice.'),
                'lang' => false
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Packing Slip Download'),
                'name' => self::CK_PACKING_SLIP,
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Allow')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('Disallow')
                    )
                ),
                'desc' => $this->l('Allow the customers to download the wallee packing slip.'),
                'lang' => false
            )
        );
        
        return array(
            'legend' => array(
                'title' => $this->l('Document Settings')
            ),
            'input' => $documentConfig,
            'buttons' => array(
                array(
                    'title' =>$this->l('Save All'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_all'
                ),
                array(
                    'title' =>$this->l('Save'),
                    'class' => 'pull-right',
                    'type' => 'input',
                    'icon' => 'process-icon-save',
                    'name' => 'submit' . $this->name . '_download'
                )
            )
        );
    }

    private function getDownloadConfigValues()
    {
        $values = array();
        if ($this->context->shop->isFeatureActive()) {
            if ($this->context->shop->getContext() == Shop::CONTEXT_SHOP) {
                $values[self::CK_INVOICE] = (bool) Configuration::get(
                    self::CK_INVOICE);
                $values[self::CK_PACKING_SLIP] = (bool) Configuration::get(
                    self::CK_PACKING_SLIP);
            }
        }
        else {
            $values[self::CK_INVOICE] = (bool) Configuration::get(
                self::CK_INVOICE);
            $values[self::CK_PACKING_SLIP] = (bool) Configuration::get(
                self::CK_PACKING_SLIP);
        }
        return $values;
    }


    public function hookWalleeCron($params)
    {
        $tasks = array();
        $tasks[] = 'Wallee_Cron::cleanUpCronDB';
        $voidService = Wallee_Service_TransactionVoid::instance();
        if ($voidService->hasPendingVoids()) {
            $tasks[] = array(
                $voidService,
                "updateVoids"
            );
        }
        $completionService = Wallee_Service_TransactionCompletion::instance();
        if ($completionService->hasPendingCompletions()) {
            $tasks[] = array(
                $completionService,
                "updateCompletions"
            );
        }
        $refundService = Wallee_Service_Refund::instance();
        if ($refundService->hasPendingRefunds()) {
            $tasks[] = array(
                $refundService,
                "updateRefunds"
            );
        }
        return $tasks;
    }

    public function hookWalleeSettingsChanged($params)
    {
        try {
            Wallee_Helper::resetApiClient();
            Wallee_Helper::getApiClient();
        }
        catch (Wallee_Exception_IncompleteConfig $e) {
            // We stop here as the configuration is not complete
            return "";
        }
        $errors = array();
        try {
            Wallee_Service_MethodConfiguration::instance()->synchronize();
        }
        catch (Exception $e) {
            $errors[] = $e->getMessage();
            $errors[] = $this->l('Synchronization of the payment method configurations failed.');
        }
        try {
            Wallee_Service_Webhook::instance()->install();
        }
        catch (Exception $e) {
            $errors[] = $e->getMessage();
            $errors[] = $this->l('Installation of the webhooks failed.');
        }
        try {
            Wallee_Service_ManualTask::instance()->update();
        }
        catch (Exception $e) {
            $errors[] = $e->getMessage();
            $errors[] = $this->l('Update of Manual Tasks failed.');
        }
        $this->deleteCachedEntries();
        return implode(" ", $errors);
    }

    private function deleteCachedEntries()
    {
        $toDelete = array(
            'wallee_currencies',
            'wallee_label_description',
            'wallee_label_description_group',
            'wallee_languages',
            'wallee_connectors',
            'wallee_methods'
        );
        foreach ($toDelete as $delete) {
            Cache::clean($delete);
        }
    }

    public function hookDisplayHeader($params)
    {
        return $this->getDeviceIdentifierScript();
    }

    public function hookDisplayMobileHeader($params)
    {
        return $this->getDeviceIdentifierScript();
    }

    public function hookDisplayTop($params)
    {
        return $this->getCronJobItem();
    }

    /**
     * hookPayment replacement for compatibility with module eu_legal
     *
     * @param array $params
     * @return string Generated html
     */
    public function hookDisplayPaymentEU($params)
    {
        if (! $this->active) {
            return;
        }
        if (! isset($params['cart']) || ! ($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = Wallee_Service_Transaction::instance()->getPossiblePaymentMethods(
                $cart);
        }
        catch (Exception $e) {
            return;
        }
        $shopId = $cart->id_shop;
        $configurations = array();
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = Wallee_Model_MethodConfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(), $possible->getId(), $shopId);
            if (! $methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = array();
        foreach (Wallee_Helper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = $this->getParametersFromMethodConfiguration($methodConfiguration, $cart,
                $shopId, $language);
            $this->smarty->assign($parameters);
            
            $result[] = array(
                'cta_text' => $this->display(__DIR__, 'hook/payment_eu_text.tpl'),
                'logo' => $parameters['image'],
                'form' => $this->display(__DIR__, 'hook/payment_eu_form.tpl')
            );
        }
        return $result;
    }

    public function hookPayment($params)
    {
        if (! $this->active) {
            return;
        }
        if (! isset($params['cart']) || ! ($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = Wallee_Service_Transaction::instance()->getPossiblePaymentMethods(
                $cart);
        }
        catch (Exception $e) {
            return;
        }
        $shopId = $cart->id_shop;
        $configurations = array();
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = Wallee_Model_MethodConfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(), $possible->getId(), $shopId);
            if (! $methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = "";
        foreach (Wallee_Helper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $templateVars = $this->getParametersFromMethodConfiguration($methodConfiguration, $cart,
                $shopId, $language);
            $this->smarty->assign($templateVars);
            $result .= $this->display(__DIR__, 'hook/payment.tpl');
        }
        return $result;
    }

    public function hookDisplayPaymentReturn($params)
    {
        if ($this->active == false) {
            return false;
        }
        $order = $params['objOrder'];
        if ($order->module != $this->name) {
            return false;
        }
        $this->smarty->assign(
            array(
                'reference' => $order->reference,
                'params' => $params,
                'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'],
                    false)
            ));
        return $this->display(__DIR__, 'hook/payment_return.tpl');
    }

    private function getParametersFromMethodConfiguration(
        Wallee_Model_MethodConfiguration $methodConfiguration, Cart $cart, $shopId, $language)
    {
        $spaceId = Configuration::get(self::CK_SPACE_ID, null, null, $shopId);
        $spaceViewId = Configuration::get(self::CK_SPACE_VIEW_ID, null, null, $shopId);
        $parameters = array();
        $parameters['link'] = $this->context->link->getModuleLink('wallee', 'payment',
            array(
                'methodId' => $methodConfiguration->getId()
            ), true);
        $name = $methodConfiguration->getConfigurationName();
        $translatedName = Wallee_Helper::translate($methodConfiguration->getTitle(), $language);
        if (! empty($translatedName)) {
            $name = $translatedName;
        }
        $parameters['name'] = $name;
        if (! empty($methodConfiguration->getImage()) && $methodConfiguration->isShowImage()) {
            $parameters['image'] = Wallee_Helper::getResourceUrl($methodConfiguration->getImage(),
                Wallee_Helper::convertLanguageIdToIETF($cart->id_lang), $spaceId, $spaceViewId);
        }
        $description = Wallee_Helper::translate($methodConfiguration->getDescription(), $language);
        if (! empty($description) && $methodConfiguration->isShowDescription()) {
            $parameters['description'] = $description;
        }
        $feeValues = Wallee_Helper::getWalleeFeeValues($cart, $methodConfiguration);
        if ($feeValues['fee_total'] > 0) {
            $parameters['feeValues'] = $feeValues;
        }
        else {
            $parameters['feeValues'] = array();
        }
        return $parameters;
    }

    private function getDeviceIdentifierScript()
    {
        $uniqueId = $this->context->cookie->wallee_device_id;
        if ($uniqueId == false) {
            $uniqueId = Wallee_Helper::generateUUID();
            $this->context->cookie->wallee_device_id = $uniqueId;
        }
        $scriptUrl = Wallee_Helper::getBaseGatewayUrl() . '/s/' .
             Configuration::get(self::CK_SPACE_ID) . '/payment/device.js?sessionIdentifier=' .
             $uniqueId;
        return '<script src="' . $scriptUrl . '" async="async"></script>';
    }

    private function getCronJobItem()
    {
        Wallee_Cron::cleanUpHangingCrons();
        Wallee_Cron::insertNewPendingCron();
        
        $currentToken = Wallee_Cron::getCurrentSecurityTokenForPendingCron();
        if ($currentToken) {
            $url = $this->context->link->getModuleLink('wallee', 'cron',
                array(
                    'security_token' => $currentToken
                ), true);
            return '<img src="' . $url . '" style="display:none" />';
        }
    }

    public function hookActionValidateOrder($params)
    {
        $cart = $params['cart'];
        $order = $params['order'];
    }

    public function hookActionMailSend($data)
    {
        if (! isset($data['event'])) {
            throw new Exception("No item 'event' provided in the mail action function.");
        }
        $event = $data['event'];
        if (! ($event instanceof MailMessageEvent)) {
            throw new Exception("Invalid type provided by the mail send action.");
        }
        
        if (self::isRecordingMailMessages()) {
            foreach ($event->getMessages() as $message) {
                self::$recordedMailMessages[] = $message;
            }
            $event->setMessages(array());
        }
    }

    public static function isRecordingMailMessages()
    {
        return self::$recordMailMessages;
    }

    public static function startRecordingMailMessages()
    {
        self::$recordMailMessages = true;
        self::$recordedMailMessages = array();
    }

    /**
     *
     * @return MailMessage[]
     */
    public static function stopRecordingMailMessages()
    {
        self::$recordMailMessages = false;
        
        return self::$recordedMailMessages;
    }

    public function validateOrder($id_cart, $id_order_state, $amount_paid,
        $payment_method = 'Unknown', $message = null, $extra_vars = array(), $currency_special = null,
        $dont_touch_amount = false, $secure_key = false, Shop $shop = null)
    {
        if ($this->active) {
            $originalCart = new Cart($id_cart);
            $rs = $originalCart->duplicate();
            if (! isset($rs['success']) || ! isset($rs['cart'])) {
                $error = Tools::displayError(
                    'The cart duplication failed. May be some module prevents it.');
                PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', intval($this->id));
                throw new Exception("There was a techincal issue, please try again.");
            }
            $cart = $rs['cart'];
            if (! ($cart instanceof Cart)) {
                $error = Tools::displayError('The duplicated cart is not of type "Cart".');
                PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', intval($this->id));
                throw new Exception("There was a techincal issue, please try again.");
            }
            foreach ($originalCart->getCartRules() as $rule) {
                $ruleObject = $rule['obj'];
                // Because free gift cart rules adds a product to the order, the product is already in the duplicated order,
                // before we can add the cart rule to the new cart we have to remove the existing gift.
                if ((int) $ruleObject->gift_product) { // We use the same check as the shop, to get the gift product
                    $cart->updateQty(1, $ruleObject->gift_product,
                        $ruleObject->gift_product_attribute, false, 'down', 0, null, false);
                }
                $cart->addCartRule($ruleObject->id);
            }
            // Update customizations
            $customizationCollection = new PrestaShopCollection('Customization');
            $customizationCollection->where('id_cart', '=', (int) $cart->id);
            foreach ($customizationCollection->getResults() as $customization) {
                $customization->id_address_delivery = $cart->id_address_delivery;
                $customization->save();
            }
            
            // Updated all specific Prices to the duplicated cart
            $specificPriceCollection = new PrestaShopCollection('SpecificPrice');
            $specificPriceCollection->where('id_cart', '=', (int) $id_cart);
            foreach ($specificPriceCollection->getResults() as $specificPrice) {
                $specificPrice->id_cart = $cart->id;
                $specificPrice->save();
            }
            
            $methodConfiguration = null;
            if (strpos($payment_method, "wallee_") === 0) {
                $id = substr($payment_method, strpos($payment_method, "_") + 1);
                $methodConfiguration = new Wallee_Model_MethodConfiguration($id);
            }
            
            if ($methodConfiguration == null || $methodConfiguration->getId() == null ||
                 $methodConfiguration->getState() != Wallee_Model_MethodConfiguration::STATE_ACTIVE || $methodConfiguration->getSpaceId() !=
                 Configuration::get(self::CK_SPACE_ID, null, null, $cart->id_shop)) {
                $error = Tools::displayError(
                    'wallee method configuration called with wrong payment method configuration. Method: ' .
                     $payment_method);
                PrestaShopLogger::addLog($error, 3, '0000002', 'PaymentModule', intval($this->id));
                throw new Exception("There was a techincal issue, please try again.");
            }
            
            $title = $methodConfiguration->getConfigurationName();
            $translatedTitel = Wallee_Helper::translate($methodConfiguration->getTitle(),
                $cart->id_lang);
            if ($translatedTitel !== null) {
                $title = $translatedTitel;
            }
            
            Wallee::startRecordingMailMessages();
            parent::validateOrder((int) $cart->id, $id_order_state, (float) $amount_paid, $title,
                $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop);
            
            $lastOrderId = $this->currentOrder;
            $dataOrder = new Order($lastOrderId);
            $orders = $dataOrder->getBrother()->getResults();
            $orders[] = $dataOrder;
            foreach ($orders as $order) {
                Wallee_Helper::updateOrderMeta($order, 'walleeMethodId',
                    $methodConfiguration->getId());
                Wallee_Helper::updateOrderMeta($order, 'walleeMainOrderId', $dataOrder->id);
                $order->save();
            }
            $emailMessages = Wallee::stopRecordingMailMessages();
            
            // Update cart <-> wallee mapping <-> order mapping
            $ids = Wallee_Helper::getCartMeta($originalCart, 'mappingIds');
            Wallee_Helper::updateOrderMeta($dataOrder, 'mappingIds', $ids);
            if (Configuration::get(self::CK_MAIL, null, null, $cart->id_shop)) {
                Wallee_Helper::storeOrderEmails($dataOrder, $emailMessages);
            }
            Wallee_Helper::updateOrderMeta($dataOrder, 'originalCart', $originalCart->id);
            
            Wallee_Service_Transaction::instance()->updateTransaction($dataOrder, $orders, true);
        }
        else {
            throw new Exception("There was a techincal issue, please try again.");
        }
    }

    public function hookActionFrontControllerSetMedia($arr)
    {
        if ($this->context->controller instanceof ParentOrderControllerCore) {
            $this->context->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/css/frontend/checkout.css');
            $cart = $this->context->cart;
            if (Configuration::get(self::CK_REMOVE_TOS, null, null, $cart->id_shop)) {
                $this->context->cookie->checkedTOS = 1;
                $this->context->controller->addJS(
                    __PS_BASE_URI__ . 'modules/' . $this->name . '/js/frontend/tos-handling.js');
            }
        }
    }

    public function hookDisplayOrderDetail($params)
    {
        $order = $params['order'];
        
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $documentVars = array();
        if (in_array($transactionInfo->getState(),
            array(
                \Wallee\Sdk\Model\TransactionState::COMPLETED,
                \Wallee\Sdk\Model\TransactionState::FULFILL,
                \Wallee\Sdk\Model\TransactionState::DECLINE
            )) && (bool) Configuration::get(self::CK_INVOICE)) {
            
            $documentVars['walleeInvoice'] = $this->context->link->getModuleLink('wallee',
                'documents',
                array(
                    'type' => 'invoice',
                    'id_order' => $order->id
                ), true);
        }
        if ($transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::FULFILL &&
             (bool) Configuration::get(self::CK_PACKING_SLIP)) {
            $documentVars['walleePackingSlip'] = $this->context->link->getModuleLink(
                'wallee', 'documents',
                array(
                    'type' => 'packingSlip',
                    'id_order' => $order->id
                ), true);
        }
        $this->context->smarty->assign($documentVars);
        return $this->display(__DIR__, 'hook/order_detail.tpl');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        if (isset($_POST['submitChangeCurrency'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            $backendController = Context::getContext()->controller;
            if (Validate::isLoadedObject($order) && $order->module == $this->name) {
                $backendController->errors[] = Tools::displayError(
                    'You cannot change the currency.');
                unset($_POST['submitChangeCurrency']);
                return;
            }
        }
        $this->handleVoucherAddRequest();
        $this->handleVoucherDeleteRequest();
        $this->handleRefundRequest();
        $this->handleCancelProductRequest();
    }

    private function handleVoucherAddRequest()
    {
        if (isset($_POST['submitNewVoucher'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            $backendController = Context::getContext()->controller;
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $postData = $_POST;
            unset($_POST['submitNewVoucher']);
            $backendController = Context::getContext()->controller;
            if ($backendController->tabAccess['edit'] == '1') {
                $strategy = Wallee_Backend_StrategyProvider::getStrategy();
                try {
                    $strategy->processVoucherAddRequest($order, $data);
                    Tools::redirectAdmin(
                        $backendController::$currentIndex . '&id_order=' . $order->id .
                             '&vieworder&conf=4&token=' . $backendController->token);
                }
                catch (Exception $e) {
                    $backendController->errors[] = Wallee_Helper::cleanWalleeExceptionMessage(
                        $e->getMessage());
                }
            }
            else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to edit this.');
            }
        }
    }

    private function handleVoucherDeleteRequest()
    {
        if (Tools::isSubmit('submitDeleteVoucher')) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $data = $_GET;
            unset($_GET['submitDeleteVoucher']);
            $backendController = Context::getContext()->controller;
            if ($backendController->tabAccess['edit'] == '1') {
                $strategy = Wallee_Backend_StrategyProvider::getStrategy();
                try {
                    $strategy->processVoucherDeleteRequest($order, $data);
                    Tools::redirectAdmin(
                        $backendController::$currentIndex . '&id_order=' . $order->id .
                             '&vieworder&conf=4&token=' . $backendController->token);
                }
                catch (Exception $e) {
                    $backendController->errors[] = Wallee_Helper::cleanWalleeExceptionMessage(
                        $e->getMessage());
                }
            }
            else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to delete this.');
            }
        }
    }

    private function handleRefundRequest()
    {
        // We need to do some special handling for refunds requests
        if (isset($_POST['partialRefund'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $refundParameters = $_POST;
            $strategy = Wallee_Backend_StrategyProvider::getStrategy();
            if ($strategy->isVoucherOnlyWallee($order, $refundParameters)) {
                return;
            }
            unset($_POST['partialRefund']);
            
            $backendController = Context::getContext()->controller;
            if ($backendController->tabAccess['edit'] == '1') {
                
                try {
                    $parsedData = $strategy->validateAndParseData($order, $refundParameters);
                    Wallee_Service_Refund::instance()->executeRefund($order, $parsedData);
                    Tools::redirectAdmin(
                        $backendController::$currentIndex . '&id_order=' . $order->id .
                             '&vieworder&conf=30&token=' . $backendController->token);
                }
                catch (Exception $e) {
                    $backendController->errors[] = Wallee_Helper::cleanWalleeExceptionMessage(
                        $e->getMessage());
                }
            }
            else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to delete this.');
            }
        }
    }

    private function handleCancelProductRequest()
    {
        if (isset($_POST['cancelProduct'])) {
            $idOrder = Tools::getValue('id_order');
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }
            $cancelParameters = $_POST;
            
            $strategy = Wallee_Backend_StrategyProvider::getStrategy();
            if ($strategy->isVoucherOnlyWallee($order, $cancelParameters)) {
                return;
            }
            unset($_POST['cancelProduct']);
            $backendController = Context::getContext()->controller;
            if ($backendController->tabAccess['delete'] === '1') {
                $strategy = Wallee_Backend_StrategyProvider::getStrategy();
                if ($strategy->isCancelRequest($order, $cancelParameters)) {
                    try {
                        $strategy->processCancel($order, $cancelParameters);
                    }
                    catch (Exception $e) {
                        $backendController->errors[] = $e->getMessage();
                    }
                }
                else {
                    try {
                        $parsedData = $strategy->validateAndParseData($order, $cancelParameters);
                        Wallee_Service_Refund::instance()->executeRefund($order, $parsedData);
                        Tools::redirectAdmin(
                            $backendController::$currentIndex . '&id_order=' . $order->id .
                                 '&vieworder&conf=31&token=' . $backendController->token);
                    }
                    catch (Exception $e) {
                        $backendController->errors[] = Wallee_Helper::cleanWalleeExceptionMessage(
                            $e->getMessage());
                    }
                }
            }
            else {
                $backendController->errors[] = Tools::displayError(
                    'You do not have permission to delete this.');
            }
        }
    }

    public function hookActionAdminControllerSetMedia($arr)
    {
        if (strtolower(Tools::getValue('controller')) == 'adminorders') {
            $this->context->controller->addJS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/js/admin/jAlert.min.js');
            $this->context->controller->addJS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/js/admin/order.js');
            $this->context->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/css/admin/order.css');
            $this->context->controller->addCSS(
                __PS_BASE_URI__ . 'modules/' . $this->name . '/css/admin/jAlert.css');
        }
        $this->context->controller->addJS(
            __PS_BASE_URI__ . 'modules/' . $this->name . '/js/admin/general.js');
    }

    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $manualTasks = Wallee_Service_ManualTask::instance()->getNumberOfManualTasks();
        $url = Wallee_Helper::getBaseGatewayUrl();
        if (count($manualTasks) == 1) {
            $spaceId = Configuration::get(self::CK_SPACE_ID, null, null, key($manualTasks));
            $url .= '/s/' . $spaceId . '/manual-task/list';
        }
        $templateVars = array(
            'manualTotal' => array_sum($manualTasks),
            'manualUrl' => $url
        );
        $this->context->smarty->assign($templateVars);
        $result = $this->display(__DIR__, 'views/templates/admin/hook/admin_after_header.tpl');
        $result .= $this->getCronJobItem();
        return $result;
    }

    /**
     * Show transaction information
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        if ($order->module != $this->name) {
            return;
        }
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $methodId = Wallee_Helper::getOrderMeta($order, 'walleeMethodId');
        $method = new Wallee_Model_MethodConfiguration($methodId);
        $tplVars = array(
            'currency' => new Currency($order->id_currency),
            'configurationName' => $method->getConfigurationName(),
            'methodImage' => Wallee_Helper::getResourceUrl($transactionInfo->getImage(),
                Wallee_Helper::convertLanguageIdToIETF($order->id_lang), $spaceId,
                $transactionInfo->getSpaceViewId()),
            'transactionState' => Wallee_Helper::getTransactionState($transactionInfo),
            'failureReason' => Wallee_Helper::translate($transactionInfo->getFailureReason()),
            'authorizationAmount' => $transactionInfo->getAuthorizationAmount(),
            'transactionUrl' => Wallee_Helper::getTransactionUrl($transactionInfo),
            'labelsByGroup' => Wallee_Helper::getGroupedChargeAttemptLabels($transactionInfo),
            'voids' => Wallee_Model_VoidJob::loadByTransactionId($spaceId, $transactionId),
            'completions' => Wallee_Model_CompletionJob::loadByTransactionId($spaceId,
                $transactionId),
            'refunds' => Wallee_Model_RefundJob::loadByTransactionId($spaceId, $transactionId)
        );
        $this->context->smarty->registerPlugin('function', 'wallee_translate',
            array(
                'Wallee_SmartyFunctions',
                'translate'
            ));
        $this->context->smarty->registerPlugin('function', 'wallee_refund_url',
            array(
                'Wallee_SmartyFunctions',
                'getRefundUrl'
            ));
        $this->context->smarty->registerPlugin('function', 'wallee_refund_amount',
            array(
                'Wallee_SmartyFunctions',
                'getRefundAmount'
            ));
        $this->context->smarty->registerPlugin('function', 'wallee_refund_type',
            array(
                'Wallee_SmartyFunctions',
                'getRefundType'
            ));
        $this->context->smarty->registerPlugin('function', 'wallee_completion_url',
            array(
                'Wallee_SmartyFunctions',
                'getCompletionUrl'
            ));
        $this->context->smarty->registerPlugin('function', 'wallee_void_url',
            array(
                'Wallee_SmartyFunctions',
                'getVoidUrl'
            ));
        
        $this->context->smarty->assign($tplVars);
        return $this->display(__DIR__, 'views/templates/admin/hook/admin_order_left.tpl');
    }

    /**
     * Show wallee documents tab
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderTabOrder($params)
    {
        $order = $params['order'];
        if ($order->module != $this->name) {
            return;
        }
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
        $templateVars['walleeDocumentsCount'] = 0;
        if (in_array($transactionInfo->getState(),
            array(
                \Wallee\Sdk\Model\TransactionState::COMPLETED,
                \Wallee\Sdk\Model\TransactionState::FULFILL,
                \Wallee\Sdk\Model\TransactionState::DECLINE
            ))) {
            $templateVars['walleeDocumentsCount'] ++;
        }
        if ($transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::FULFILL) {
            $templateVars['walleeDocumentsCount'] ++;
        }
        $this->context->smarty->assign($templateVars);
        return $this->display(__DIR__, 'views/templates/admin/hook/admin_order_tab_order.tpl');
    }

    /**
     * Show wallee documents table.
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderContentOrder($params)
    {
        $order = $params['order'];
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars['walleeDocuments'] = array();
        if (in_array($transactionInfo->getState(),
            array(
                \Wallee\Sdk\Model\TransactionState::COMPLETED,
                \Wallee\Sdk\Model\TransactionState::FULFILL,
                \Wallee\Sdk\Model\TransactionState::DECLINE
            ))) {
            $templateVars['walleeDocuments'][] = array(
                'icon' => 'file-text-o',
                'name' => $this->l('Invoice'),
                'url' => $this->context->link->getAdminLink('AdminWalleeDocuments') .
                     '&walleeAction=walleeInvoice&id_order=' . $order->id
            );
        }
        if ($transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::FULFILL) {
            $templateVars['walleeDocuments'][] = array(
                'icon' => 'truck',
                'name' => $this->l('Packing Slip'),
                'url' => $this->context->link->getAdminLink('AdminWalleeDocuments') .
                     '&walleeAction=walleePackingSlip&id_order=' . $order->id
            );
        }
        $this->context->smarty->assign($templateVars);
        return $this->display(__DIR__, 'views/templates/admin/hook/admin_order_content_order.tpl');
    }

    public function hookDisplayAdminOrder($params)
    {
        $orderId = $params['id_order'];
        $order = new Order($orderId);
        $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
        if ($transactionInfo == null) {
            return '';
        }
        $templateVars = array();
        $templateVars['isWalleeTransaction'] = true;
        if ($transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
            if (! Wallee_Model_CompletionJob::isCompletionRunningForTransaction(
                $transactionInfo->getSpaceId(), $transactionInfo->getTransactionId()) && ! Wallee_Model_VoidJob::isVoidRunningForTransaction(
                $transactionInfo->getSpaceId(), $transactionInfo->getTransactionId())) {
                
                $affectedOrders = $order->getBrother()->getResults();
                $affectedIds = array();
                foreach ($affectedOrders as $other) {
                    $affectedIds[] = $other->id;
                }
                sort($affectedIds);
                $templateVars['showAuthorizedActions'] = true;
                $templateVars['affectedOrders'] = $affectedIds;
                $templateVars['voidUrl'] = $this->context->link->getAdminLink('AdminWalleeOrder',
                    true) . '&walleeAction=voidOrder&id_order=' . $orderId;
                $templateVars['completionUrl'] = $this->context->link->getAdminLink(
                    'AdminWalleeOrder', true) . '&walleeAction=completeOrder&id_order=' . $orderId;
            }
        }
        if (in_array($transactionInfo->getState(),
            array(
                \Wallee\Sdk\Model\TransactionState::COMPLETED,
                \Wallee\Sdk\Model\TransactionState::DECLINE,
                \Wallee\Sdk\Model\TransactionState::FULFILL
            ))) {
            $templateVars['editButtons'] = true;
            $templateVars['refundChanges'] = true;
        }
        if ($transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::VOIDED) {
            $templateVars['editButtons'] = true;
            $templateVars['cancelButtons'] = true;
        }
        
        if (Wallee_Model_CompletionJob::isCompletionRunningForTransaction(
            $transactionInfo->getSpaceId(), $transactionInfo->getTransactionId())) {
            $templateVars['completionPending'] = true;
        }
        if (Wallee_Model_VoidJob::isVoidRunningForTransaction($transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId())) {
            $templateVars['voidPending'] = true;
        }
        if (Wallee_Model_RefundJob::isRefundRunningForTransaction($transactionInfo->getSpaceId(),
            $transactionInfo->getTransactionId())) {
            $templateVars['refundPending'] = true;
        }
        
        $this->context->smarty->assign($templateVars);
        return $this->display(__DIR__, 'views/templates/admin/hook/admin_order.tpl');
    }

    public function hookActionAdminOrdersControllerBefore($params)
    {
        // We need to start a db transaction here to revert changes to the order, if the update to wallee fails.
        // But we can not use the ActionAdminOrdersControllerAfter, because these are ajax requests and all of
        // exit the process before the ActionAdminOrdersControllerAfter Hook is called.
        $action = Tools::getValue('action');
        if (in_array($action,
            array(
                'editProductOnOrder',
                'deleteProductLine',
                'addProductOnOrder'
            ))) {
            $order = new Order((int) Tools::getValue('id_order'));
            if ($order->module != $this->name) {
                return;
            }
            Wallee_Helper::startDBTransaction();
        }
    }

    public function hookActionOrderEdited($params)
    {
        // We send the changed line items to wallee after the order has been edited
        $action = Tools::getValue('action');
        if (in_array($action,
            array(
                'editProductOnOrder',
                'deleteProductLine',
                'addProductOnOrder'
            ))) {
            $modifiedOrder = $params['order'];
            if ($modifiedOrder->module != $this->name) {
                return;
            }
            
            $orders = $modifiedOrder->getBrother()->getResults();
            $orders[] = $modifiedOrder;
            
            $lineItems = Wallee_Service_LineItem::instance()->getItemsFromOrders($orders);
            $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($modifiedOrder);
            if (! $transactionInfo) {
                Wallee_Helper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                sprintf(
                                    Wallee_Helper::translatePS(
                                        'Could not load the coresponding transaction for order with id %d'),
                                    $order->id))
                        )));
            }
            if ($transactionInfo->getState() != \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
                Wallee_Helper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                Wallee_Helper::translatePS(
                                    'The line items for this order can not be changed'))
                        )));
            }
            
            try {
                Wallee_Service_Transaction::instance()->updateLineItems(
                    $transactionInfo->getSpaceId(), $transactionInfo->getTransactionId(), $lineItems);
            }
            catch (Exception $e) {
                Wallee_Helper::rollbackDBTransaction();
                die(
                    Tools::jsonEncode(
                        array(
                            'result' => false,
                            'error' => Tools::displayError(
                                sprintf(
                                    Wallee_Helper::translatePS(
                                        'Could not update the line items at wallee. Reason: %s'),
                                    
                                    Wallee_Helper::cleanWalleeExceptionMessage($e->getMessage())))
                        )));
            }
            Wallee_Helper::commitDBTransaction();
        }
    }
}



