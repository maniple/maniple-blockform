<?php

class ManipleBlockform_Bootstrap extends Zefram_Application_Module_Bootstrap
{
    protected function _initView()
    {
        $bootstrap = $this->getApplication();
        $bootstrap->bootstrap('View');

        /** @var Zend_View $view */
        $view = $bootstrap->getResource('View');
        $view->addHelperPath(__DIR__ . '/library/View/Helper/', 'ManipleBlockform_View_Helper_');
    }
}
