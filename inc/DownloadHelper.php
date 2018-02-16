<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * This class provides function to download documents from wallee 
 */
class Wallee_DownloadHelper {

	/**
	 * Downloads the transaction's invoice PDF document.
	 */
	public static function downloadInvoice($order){
	    $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
	    if ($transactionInfo != null && in_array($transactionInfo->getState(), 
				array(
					\Wallee\Sdk\Model\TransactionState::COMPLETED,
					\Wallee\Sdk\Model\TransactionState::FULFILL,
					\Wallee\Sdk\Model\TransactionState::DECLINE 
				))) {
			$service = new \Wallee\Sdk\Service\TransactionService(Wallee_Helper::getApiClient());
			$document = $service->getInvoiceDocument($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
			self::download($document);
		}
		
	}

	/**
	 * Downloads the transaction's packing slip PDF document.
	 */
	public static function downloadPackingSlip($order){
	    $transactionInfo = Wallee_Helper::getTransactionInfoForOrder($order);
	    if ($transactionInfo != null && $transactionInfo->getState() == \Wallee\Sdk\Model\TransactionState::FULFILL) {
			
	        $service = new \Wallee\Sdk\Service\TransactionService(Wallee_Helper::getApiClient());
	        $document = $service->getPackingSlip($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
			self::download($document);
		}
	}

	/**
	 * Sends the data received by calling the given path to the browser and ends the execution of the script
	 *
	 * @param string $path
	 */
	protected static function download(\Wallee\Sdk\Model\RenderedDocument $document){
		header('Pragma: public');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $document->getTitle() . '.pdf"');
		header('Content-Description: ' . $document->getTitle());
		echo base64_decode($document->getData());
		exit();
	}
}