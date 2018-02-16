<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * This service provides methods to handle manual tasks.
 */
class Wallee_Service_ManualTask extends Wallee_Service_Abstract {
	
    const CONFIG_KEY = 'WLE_MANUAL_TASKS';

	
	/**
	 * Returns the number of open manual tasks.
	 *
	 * @return array
	 */
	public function getNumberOfManualTasks()
	{
	    $numberOfManualTasks = array();
	    foreach (Shop::getShops(true, null, true) as $shopId) {
	        $shopNumberOfManualTasks = Configuration::get(self::CONFIG_KEY, null, null, $shopId);
	        if ($shopNumberOfManualTasks != null && $shopNumberOfManualTasks > 0) {
	            $numberOfManualTasks[$shopId] = $shopNumberOfManualTasks;
	        }
	    }
	    return $numberOfManualTasks;
	}
	
	/**
	 * Updates the number of open manual tasks.
	 *
	 * @return array
	 */
	public function update()
	{
	    $numberOfManualTasks = array();
	    $spaceIds = array();
	    $manualTaskService = new \Wallee\Sdk\Service\ManualTaskService(Wallee_Helper::getApiClient());
	    foreach (Shop::getShops(true, null, true) as $shopId) {
	        $spaceId = Configuration::get(Wallee::CK_SPACE_ID, null, null, $shopId);
	        if ($spaceId && ! in_array($spaceId, $spaceIds)) {
	            $shopNumberOfManualTasks = $manualTaskService->count($spaceId, $this->createEntityFilter('state', \Wallee\Sdk\Model\ManualTaskState::OPEN));
                Configuration::updateValue(self::CONFIG_KEY,$shopNumberOfManualTasks, false, null, $shopId);
	            if ($shopNumberOfManualTasks > 0) {
	                $numberOfManualTasks[$shopId] = $shopNumberOfManualTasks;
	            }
	            $spaceIds[] = $spaceId;
	        } else {
	            Configuration::updateValue(self::CONFIG_KEY, 0, false, null, $shopId);
	        }
	    }
	    return $numberOfManualTasks;
	}
	
}