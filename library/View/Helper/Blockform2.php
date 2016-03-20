<?php

/**
 * View helper for rendering Blockform element blocks.
 *
 * @author xemlock
 * @version 2014-01-21 / 2014-01-16
 */
class ManipleBlockform_View_Helper_Blockform2 extends Zend_View_Helper_HtmlElement
{
    protected static $_cache;

    /**
     * @param  Zend_Cache_Core $cache
     * @return void
     */
    public static function setCache(Zend_Cache_Core $cache) // {{{
    {
        self::$_cache = $cache;
    } // }}}

    /**
     * @return Zend_Cache_Core
     */
    public static function getCache() // {{{
    {
        if (null === self::$_cache) {
            switch (true) {
                case Zend_Registry::isRegistered('Zend_Cache'):
                    $cache = Zend_Registry::get('Zend_Cache');
                    break;

                case Zend_Registry::isRegistered('Cache'):
                    $cache = Zend_Registry::get('Cache');
                    break;

                default:
                    $cache = null;
                    break;
            }
            
            self::$_cache = $cache instanceof Zend_Cache_Core ? $cache : false;
        }

        return self::$_cache;
    } // }}}

    /**
     * @param ManipleBlockform_Form_Blockform $form
     * @param string $viewScript OPTIONAL
     * @param array $options OPTIONAL
     * @return mixed
     */
    public function blockform2(ManipleBlockform_Form_Blockform $form = null, $viewScript = null, array $options = null) // {{{
    {
        if (null === $form) {
            return $this;
        }

        return $this->render($form, $viewScript, $options);
    } // }}}

    public function renderBlockHtml($viewScript, ManipleBlockform_Form_Blockform $form, $id, array $vars = null) // {{{
    {
        $elements = $form->getBlockElements($id);

        $vars = array_merge(
            $elements,
            array(
                'block' => array_merge(
                    array_merge(array(
                        'index'    => null,
                        'index0'   => null,
                        'first'    => null,
                        'last'     => null,
                    ), (array) $vars),
                    array(
                        'id'       => $id,
                        'form'     => $form,
                        'elements' => $elements,
                    )
                ),
            )
        );

        return $this->view->renderScript($viewScript, $vars);
    } // }}}

    /**
     * Options:
     *     'idPlaceholder'  => string, default '{{ id }}'
     *     'templateId'     => string
     *     'noCache'        => bool, default FALSE
     *     'noScriptTag'    => bool, default FALSE
     *
     * @param  ManipleBlockform_Form_Blockform $form
     * @param  string $viewScript
     * @param  array $options OPTIONAL
     * @return string
     */
    public function renderBlockTemplate(ManipleBlockform_Form_Blockform $form, $viewScript, array $options = null) // {{{
    {
        $cache = null;

        if (0&&empty($options['noCache'])) {
            $cache = self::getCache();
            $cacheKey = 'Blockform_' . md5(
                sprintf('%s.%d.%s.%s.%s',
                    get_class($form),
                    $form->isArray(),
                    $form->getElementsBelongTo(),
                    $viewScript,
                    serialize($options)
                ));
        }

        if ($cache && ($result = $cache->load($cacheKey))) {
            return $result;
        }

        $formCopy = clone $form;
        $formCopy->clearElements();

        $id = (int) str_replace('0.', '', microtime());
        $formCopy->createBlock($id);

        if (null === $viewScript) {
            $blockHtml = trim($formCopy->renderFormElements());
        } else {
            $blockHtml = $this->renderBlockHtml($viewScript, $formCopy, $id);
        }

        // replace id with placeholder
        $idPlaceholder = empty($options['idPlaceholder'])
            ? '{{ id }}'
            : $options['idPlaceholder'];

        $blockHtml = $this->_finalizeBlock($blockHtml, $id, $options);
        $blockHtml = str_replace($id, $idPlaceholder, $blockHtml);

        if (empty($options['noScriptTag'])) {
            $attribs = array();

            if (isset($options['templateId'])) {
                $attribs['id'] = $options['templateId'];
            }

            $blockHtml = '<script type="text/html" data-blockform-role="blockTemplate"'
                . $this->_htmlAttribs($attribs)
                . '>'
                . $blockHtml
                . '</script>';
        }

        if ($cache) {
            $cache->save($blockHtml, $cacheKey);
        }

        return $blockHtml;
    } // }}}

    /**
     * Options:
     *     'blockContainerTag'  => string, default 'div'
     *
     * @param  ManipleBlockform_Form_Blockform $form
     * @param  string $viewScript
     * @param  array $options OPTIONAL
     * @return string
     */
    public function renderBlocks(ManipleBlockform_Form_Blockform $form, $viewScript, array $options = null) // {{{
    {
        if (isset($options['blockContainerTag'])) {
            $blockContainerTag = trim($options['blockContainerTag'], "<> \t\n\t");
        } else {
            // if no blockContainer tag is provided, use DIV as a default tag
            $blockContainerTag = 'div';
        }

        $html = '';

        $ids = $form->getBlockIds();

        for ($i = 0, $n = count($ids); $i < $n; ++$i) {
            $id = $ids[$i];
            $blockHtml = $this->renderBlockHtml($viewScript, $form, $id, array(
                'index'  => $i + 1,
                'index0' => $i,
                'first'  => $i === 0,
                'last'   => $i === $n - 1,
            ));
            $html .= $this->_finalizeBlock($blockHtml, $id, $options);
        }

        if ($blockContainerTag) {
            $html = '<' . $blockContainerTag . ' data-blockform-role="blockContainer">'
                  . $html
                  . '</' . strtok($blockContainerTag, "> \t\n\r") . '>';
        }

        return $html;
    } // }}}

    /**
     * Options:
     *     'blockTag'       => string
     *
     * @param string $blockHtml
     * @param mixed $id
     * @param array $options
     * @return string
     */
    protected function _finalizeBlock($blockHtml, $id, array $options = null) // {{{
    {
        $blockTag = null;

        if (isset($options['blockTag'])) {
            $blockTag = trim($options['blockTag'], "<> \r\t\n");
        }

        // check if data-block-id attribute can be appended to first
        // encountered tag, if not, use default blockTag
        $pos = strpos($blockHtml, '>');

        if (empty($blockTag) && (false === $pos)) {
            $blockTag = 'div';
        }

        $htmlAttribs = $this->_htmlAttribs(array(
            'data-block-id' => $id,
        ));

        if ($blockTag) {
            // block tag can be given as a tag name, or tag name with attributes
            $blockHtml = '<' . $blockTag . $htmlAttribs . '>'
                . $blockHtml
                . '</' . strtok($blockTag, "> \t\n\r") . '>';
        } else {
            $blockHtml = substr_replace($blockHtml, $htmlAttribs . '>', $pos, 1);
        }

        return $blockHtml;
    } // }}}

    /**
     * Renders block form using given view script to render contained
     * form blocks.
     *
     * Options:
     *     'noTemplate'     => bool, default FALSE
     *     'indexId'        => string
     *     'adderId'        => string
     *     'adderClass'     => string
     *     'adderLabel'     => string
     *
     * and options supported by {@see renderBlocks()} and
     * {@see renderBlockTemplate()} methods.
     *
     * @param  ManipleBlockform_Form_Blockform $form
     * @param  string $viewScript
     * @param  array $options OPTIONAL
     * @return string
     */
    public function render(ManipleBlockform_Form_Blockform $form, $viewScript, array $options = null) // {{{
    {
        $indexHtml = null;
        $adderHtml = null;
        $submitHtml = null;

        $attribs = array(
            'class' => 'blockform',
            'data-init' => 'blockform',
            'data-max-blocks' => $form->getMaxBlocks(),
            'data-min-blocks' => $form->getMinBlocks(),
        );

        if ($index = $form->getElement(ManipleBlockform_Form_Blockform::ELEMENT_INDEX)) {
            if (isset($options['indexId'])) {
                $index->setId($options['indexId']);
            }

            // render element to force initialization of id attribute
            $indexHtml = $index->render();
            $attribs['data-block-index'] = $index->getId();
        }

        if (empty($options['noBlockAdder'])) {
            if ($adder = $form->getElement(ManipleBlockform_Form_Blockform::ELEMENT_ADD)) {
                if (isset($options['adderClass'])) {
                    $adder->setAttrib('class', $options['adderClass']);
                }
                if (isset($options['adderLabel'])) {
                    $adder->setLabel($options['adderLabel']);
                }
                if (isset($options['adderId'])) {
                    $adder->setId($options['adderId']);
                }

                $adderHtml = $adder->render();
                $attribs['data-block-adder'] = $adder->getId();
            }
        }

        // render block template by default
        if (empty($options['noTemplate'])) {
            $templateHtml = $this->renderBlockTemplate($form, $viewScript, $options);
        } else {
            $templateHtml = null;
        }

        $blocksHtml = $this->renderBlocks($form, $viewScript, $options);

        $html = '<div' . $this->_htmlAttribs($attribs) . '>'
              . $templateHtml
              . $indexHtml
              . $blocksHtml
              . $adderHtml
              . '</div>';

        return $html;
    } // }}}
}
