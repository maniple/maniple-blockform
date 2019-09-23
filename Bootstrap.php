<?php

class ManipleBlockform_Bootstrap extends Maniple_Application_Module_Bootstrap
{
    public function getViewConfig()
    {
        return array(
            'helperPaths' => array(
                'ManipleBlockform_View_Helper_' => __DIR__ . '/library/ManipleBlockform/View/Helper/',
            ),
        );
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'ManipleBlockform_' => __DIR__ . '/library/ManipleBlockform/',
                ),
            ),
        );
    }
}
