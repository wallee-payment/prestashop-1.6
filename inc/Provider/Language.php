<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Provider of language information from the gateway.
 */
class WalleeProviderLanguage extends WalleeProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('wallee_languages');
    }

    /**
     * Returns the language by the given code.
     *
     * @param string $code
     * @return \Wallee\Sdk\Model\RestLanguage
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns the primary language in the given group.
     *
     * @param string $code
     * @return \Wallee\Sdk\Model\RestLanguage
     */
    public function findPrimary($code)
    {
        $code = Tools::substr($code, 0, 2);
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
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $languageService = new \Wallee\Sdk\Service\LanguageService(
            WalleeHelper::getApiClient()
        );
        return $languageService->all();
    }

    protected function getId($entry)
    {
        /* @var \Wallee\Sdk\Model\RestLanguage $entry */
        return $entry->getIetfCode();
    }
}
