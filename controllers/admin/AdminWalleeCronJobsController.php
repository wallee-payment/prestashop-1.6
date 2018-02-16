<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

class AdminWalleeCronJobsController extends ModuleAdminController
{
    
    public function __construct()
    {
        parent::__construct();
        $this->context->smarty->addTemplateDir($this->getTemplatePath());
        $this->tpl_folder = 'cronjob/';
        $this->bootstrap = true;
    }
    
    public function initContent()
    {
        $this->handleList();
        parent::initContent();
    }

    private function handleList()
    {
        $this->display = 'list';
        $this->context->smarty->assign('title', $this->module->l('wallee CronJobs'));
        $this->context->smarty->assign('jobs', Wallee_Cron::getAllCronJobs());
    }
    
}
    