<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

/**
 * Abstract webhook processor.
 */
abstract class Wallee_Webhook_Abstract {
	private static $instances = array();

	/**
	 * @return static
	 */
	public static function instance(){
		$class = get_called_class();
		if (!isset(self::$instances[$class])) {
			self::$instances[$class] = new $class();
		}
		return self::$instances[$class];
	}

	/**
	 * Processes the received webhook request.
	 *
	 * @param Wallee_Webhook_Request $request
	 */
	abstract public function process(Wallee_Webhook_Request $request);
}