<?php
if (! defined('_PS_VERSION_')) {
    exit();
}


class WalleePaymentModuleFrontController extends Wallee_FrontPaymentController
{

    public $display_column_left = false;
    public $ssl = true;

    /**
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $methodId = Tools::getValue('methodId', null);
        if ($methodId == null) {
            $this->context->cookie->wallee_error = $this->module->l("There was a techincal issue, please try again.");
            Tools::redirect('index.php?controller=order&step=3');
        }        
        $cart = $this->context->cart;
        
        $redirect = $this->checkAvailablility($cart);
        if(!empty($redirect)){
            Tools::redirect($redirect);
            die();
        }
                   
        $spaceId = Configuration::get(Wallee::CK_SPACE_ID, null, null, $cart->id_shop);
        $methodConfiguration = new Wallee_Model_MethodConfiguration($methodId, $cart->id_shop);
        
        if (! $methodConfiguration->isActive() || $methodConfiguration->getSpaceId() != $spaceId) {
            $this->context->cookie->wallee_error = $this->module->l("This payment method is no longer available, please try another one.");
            Tools::redirect($this->context->link->getPageLink('order', true, NULL, "step=3"));
        }

        $this->addFeeProductToCart($methodConfiguration, $cart);
        
        $this->assignSummaryInformations($cart);
        $cartHash = Wallee_Helper::calculateCartHash($cart);
        $showCart = Configuration::get(Wallee::CK_SHOW_CART, null, null, $cart->id_shop);
        $showTos = Configuration::get(Wallee::CK_SHOW_TOS, null, null, $cart->id_shop);
        
        $jsUrl = null;
        try {
            $jsUrl = Wallee_Service_Transaction::instance()->getJavascriptUrl($cart);
        } catch (Exception $e) {
            $this->context->cookie->wallee_error = $this->module->l("There was a techincal issue, please try again.");
            Tools::redirect('index.php?controller=order&step=3');
        }
        
        $name = $methodConfiguration->getConfigurationName();
        $language = $this->context->language->language_code;
        $translatedName = Wallee_Helper::translate($methodConfiguration->getTitle(), $language);
        if (! empty($translatedName)) {
            $name = $translatedName;
        }
        
        $hook_override_tos_display = Hook::exec('overrideTOSDisplay');
        $cms = new CMS(Configuration::get('PS_CONDITIONS_CMS_ID'), $this->context->language->id);
        $this->link_conditions = $this->context->link->getCMSLink($cms, $cms->link_rewrite,
            (bool) Configuration::get('PS_SSL_ENABLED'));
        if (! strpos($this->link_conditions, '?')) {
            $this->link_conditions .= '?content_only=1';
        } else {
            $this->link_conditions .= '&content_only=1';
        }
        
        $this->context->smarty->registerPlugin('function', 'wallee_resolve_template',
            array(
                $this,
                'resolveTemplatePath'
            ));
        $this->context->smarty->assign(
            array(
                'name' => $name,
                'showCart' => $showCart,
                'showTOS' => $showTos,
                'cmsId' => (int) Configuration::get('PS_CONDITIONS_CMS_ID'),
                'conditions' => (int) Configuration::get('PS_CONDITIONS'),
                'checkedTOS'=> 0,
                'linkConditions' => $this->link_conditions,
                'overrideTOSDisplay' => $hook_override_tos_display,
                'cartHash' => $cartHash,
                'methodId' => $methodConfiguration->getId(),
                'configurationId' => $methodConfiguration->getConfigurationId(),
                'this_path' => $this->module->getPathUri(),
                'this_path_bw' => $this->module->getPathUri(),
                'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' .
                     $this->module->name . '/',
                'form_target_url' => $this->context->link->getModuleLink('wallee', 'order', array(), true)
            ));
        $this->addJquery();
        $this->addJS($jsUrl, false);
        $this->addJS(__PS_BASE_URI__ . 'modules/' . $this->module->name . '/js/frontend/checkout.js');
        $this->addJqueryPlugin('fancybox');
        $this->addCSS(__PS_BASE_URI__ . 'modules/' . $this->module->name . '/css/frontend/checkout.css');
        
        $this->setTemplate('payment_execution.tpl');
    }

    public function resolveTemplatePath($params, $smarty)
    {
        $template = $params['template'];
        if (! $path = $this->getTemplatePath($template)) {
            throw new PrestaShopException("Template '$template' not found");
        }
        return $path;
    }

    protected function assignSummaryInformations(Cart $cart)
    {
        $summary = $cart->getSummaryDetails();
        $customizedDatas = Product::getAllCustomizedDatas($cart->id);
        
        // override customization tax rate with real tax (tax rules)
        if ($customizedDatas) {
            foreach ($summary['products'] as &$productUpdate) {
                $productId = (int) isset($productUpdate['id_product']) ? $productUpdate['id_product'] : $productUpdate['product_id'];
                $productAttributeId = (int) isset($productUpdate['id_product_attribute']) ? $productUpdate['id_product_attribute'] : $productUpdate['product_attribute_id'];
                
                if (isset($customizedDatas[$productId][$productAttributeId])) {
                    $productUpdate['tax_rate'] = Tax::getProductTaxRate($productId,
                        $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
                }
            }
            Product::addCustomizationPrice($summary['products'], $customizedDatas);
        }
        
        $cart_product_context = Context::getContext()->cloneContext();
        foreach ($summary['products'] as $key => &$product) {
            $product['quantity'] = $product['cart_quantity']; // for compatibility with 1.2 themes
            
            if ($cart_product_context->shop->id != $product['id_shop']) {
                $cart_product_context->shop = new Shop((int) $product['id_shop']);
            }
            $product['price_without_specific_price'] = Product::getPriceStatic(
                $product['id_product'], ! Product::getTaxCalculationMethod(),
                $product['id_product_attribute'], 6, null, false, false, 1, false, null, null, null,
                $null, true, true, $cart_product_context);
            
            if (Product::getTaxCalculationMethod()) {
                $product['is_discounted'] = Tools::ps_round(
                    $product['price_without_specific_price'], _PS_PRICE_COMPUTE_PRECISION_) !=
                     Tools::ps_round($product['price'], _PS_PRICE_COMPUTE_PRECISION_);
            } else {
                $product['is_discounted'] = Tools::ps_round(
                    $product['price_without_specific_price'], _PS_PRICE_COMPUTE_PRECISION_) !=
                     Tools::ps_round($product['price_wt'], _PS_PRICE_COMPUTE_PRECISION_);
            }
        }
        
        // Get available cart rules and unset the cart rules already in the cart
        $available_cart_rules = CartRule::getCustomerCartRules($this->context->language->id,
            (isset($this->context->customer->id) ? $this->context->customer->id : 0), true, true,
            true, $cart, false, true);
        $cart_cart_rules = $cart->getCartRules();
        foreach ($available_cart_rules as $key => $available_cart_rule) {
            foreach ($cart_cart_rules as $cart_cart_rule) {
                if ($available_cart_rule['id_cart_rule'] == $cart_cart_rule['id_cart_rule']) {
                    unset($available_cart_rules[$key]);
                    continue 2;
                }
            }
        }
        
        $this->context->smarty->assign($summary);
        $this->context->smarty->assign(
            array(
                'token_cart' => Tools::getToken(false),
                'isVirtualCart' => $cart->isVirtualCart(),
                'productNumber' => $cart->nbProducts(),
                'shippingCost' => $cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
                'shippingCostTaxExc' => $cart->getOrderTotal(false, Cart::ONLY_SHIPPING),
                'customizedDatas' => $customizedDatas,
                'CUSTOMIZE_FILE' => Product::CUSTOMIZE_FILE,
                'CUSTOMIZE_TEXTFIELD' => Product::CUSTOMIZE_TEXTFIELD,
                'displayVouchers' => $available_cart_rules,
                'smallSize' => Image::getSize(ImageType::getFormatedName('small'))
            ));
    }
}