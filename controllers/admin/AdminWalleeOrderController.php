<?php
/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2024 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class AdminWalleeOrderController extends ModuleAdminController
{
    public function postProcess()
    {
        parent::postProcess();
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess(
            $this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        if ($access['edit'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l(
                        'You do not have permission to edit the order.',
                        'adminwalleeordercontroller'
                    )
                )
            );
            die();
        }
    }

    public function ajaxProcessUpdateOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                WalleeServiceTransactioncompletion::instance()->updateForOrder($order);
                WalleeServiceTransactioncompletion::instance()->updateForOrder($order);
                echo Tools::jsonEncode(array(
                    'success' => 'true'
                ));
                die();
            } catch (Exception $e) {
                echo Tools::jsonEncode(array(
                    'success' => 'false',
                    'message' => $e->getMessage()
                ));
                die();
            }
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminwalleeordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessVoidOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                WalleeServiceTransactionvoid::instance()->executeVoid($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the void is processed.',
                            'adminwalleeordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => WalleeHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminwalleeordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessCompleteOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                WalleeServiceTransactioncompletion::instance()->executeCompletion($order);
                echo Tools::jsonEncode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the completion is processed.',
                            'adminwalleeordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo Tools::jsonEncode(
                    array(
                        'success' => 'false',
                        'message' => WalleeHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo Tools::jsonEncode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminwalleeordercontroller')
                )
            );
            die();
        }
    }
}
