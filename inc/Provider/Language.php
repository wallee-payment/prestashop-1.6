<?php
if (! defined('_PS_VERSION_')) {
    exit();
}
/**
 * Provider of language information from the gateway.
 */
class Wallee_Provider_Language extends Wallee_Provider_Abstract {

	protected function __construct(){
		parent::__construct('wallee_languages');
	}

	/**
	 * Returns the language by the given code.
	 *
	 * @param string $code
	 * @return \Wallee\Sdk\Model\RestLanguage
	 */
	public function find($code){
		return parent::find($code);
	}

	/**
	 * Returns the primary language in the given group.
	 *
	 * @param string $code
	 * @return \Wallee\Sdk\Model\RestLanguage
	 */
	public function findPrimary($code){
		$code = substr($code, 0, 2);
		foreach ($this->getAll() as $language) {
			if ($language->getIso2Code() == $code && $language->getPrimaryOfGroup()) {
				return $language;
			}
		}
		
		return false;
	}

	/**
	 * Returns a list of language.
	 *
	 * @return \Wallee\Sdk\Model\RestLanguage[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
	    $languageService = new \Wallee\Sdk\Service\LanguageService(Wallee_Helper::getApiClient());
		return $languageService->all();
	}

	protected function getId($entry){
		/* @var \Wallee\Sdk\Model\RestLanguage $entry */
		return $entry->getIetfCode();
	}
}