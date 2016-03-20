<?php

class ManipleBlockform_Bootstrap extends Zefram_Application_Module_Bootstrap
{
    protected function _initAutoloader()
    {
        Zend_Loader_AutoloaderFactory::factory(array(
            'Zend_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'ManipleBlockform_' => dirname(__FILE__) . '/library/'
                ),
            ),
        ));
    }
    protected function _initView()
    {
        $bootstrap = $this->getApplication();
        $bootstrap->bootstrap('View');

        /** @var Zend_View $view */
        $view = $bootstrap->getResource('View');
        $view->addScriptPath(dirname(__FILE__) . '/views/scripts');
        $view->addHelperPath(dirname(__FILE__) . '/library/View/Helper/', 'ManipleBlockform_View_Helper_');
    }
}
