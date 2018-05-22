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

class WalleeDocumentsModuleFrontController extends ModuleFrontController
{
	protected $display_header = false;
	protected $display_footer = false;
	
	public $content_only = true;
	
	
	public function postProcess()
	{
	    if (!$this->context->customer->isLogged() && !Tools::getValue('secure_key')) {
	        Tools::redirect('index.php?controller=authentication');
	    }
	    
	    $id_order = (int)Tools::getValue('id_order');
	    if (Validate::isUnsignedId($id_order)) {
	        $order = new Order((int)$id_order);
	    }
	    
	    if (!isset($order) || !Validate::isLoadedObject($order)) {
	        die(Tools::displayError($this->module->l('The document was not found.')));
	    }
	    
	    if ((isset($this->context->customer->id) && $order->id_customer != $this->context->customer->id) || (Tools::isSubmit('secure_key') && $order->secure_key != Tools::getValue('secure_key'))) {
	        die(Tools::displayError($this->module->l('The document was not found.')));
	    }
	    if ($type = Tools::getValue('type')) {
	        switch($type){
	            case 'invoice':
	                if ((bool) Configuration::get(Wallee::CK_INVOICE)) {
	                   $this->processWalleeInvoice($order);
	                }
	                break;
	            case 'packingSlip':
	                if((bool) Configuration::get(Wallee::CK_PACKING_SLIP)){
	                   $this->processWalleePackingSlip($order);
	                }
	                break;
	        }
	    } 
	    die(Tools::displayError($this->module->l('The document was not found.')));
	   
	}
	
	private function processWalleeInvoice($order)
	{
        try {
            Wallee_DownloadHelper::downloadInvoice($order);
        } catch (Exception $e) {
            die(Tools::displayError($this->module->l('Could not fetch the document.')));
        }
	    
	}
	
	private function processWalleePackingSlip($order)
	{
        try {
            Wallee_DownloadHelper::downloadPackingSlip($order);
        } catch (Exception $e) {
            die(Tools::displayError($this->module->l('Could not fetch the document.')));
        }
	    
	}
	
	
	public function setMedia()
	{
	    // We do not need styling here
	}
	
	protected function displayMaintenancePage()
	{
	    // We never display the maintenance page.
	}
	
	protected function displayRestrictedCountryPage()
	{
	    // We do not want to restrict the content by any country.
	}
	
	protected function canonicalRedirection($canonical_url = '')
	{
	    // We do not need any canonical redirect
	}
	
}
