<?php

/**
 * View helper for rendering Blockform element blocks.
 *
 * @author xemlock
 * @version 2019-09-14 / 2018-04-14 / 2014-01-21 / 2014-01-16
 *
 * Changelog:
 * 2019-09-14  - handle comments when adding attributes to block element
 * 2018-04-14  - added autoInit setting
 *             - added vars setting, custom variables are available via block.*
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */
class ManipleBlockform_View_Helper_Blockform2 extends Zend_View_Helper_HtmlElement
{
    protected static $_cache;

    /**
     * @param  Zend_Cache_Core $cache
     * @return void
     */
    public static function setCache(Zend_Cache_Core $cache)
    {
        self::$_cache = $cache;
    }

    /**
     * @return Zend_Cache_Core
     */
    public static function getCache()
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
    }

    /**
     * @param ManipleBlockform_Form_Blockform $form
     * @param string $viewScript OPTIONAL
     * @param array $options OPTIONAL
     * @return mixed
     */
    public function blockform2(ManipleBlockform_Form_Blockform $form = null, $viewScript = null, array $options = null)
    {
        if (null === $form) {
            return $this;
        }

        return $this->render($form, $viewScript, $options);
    }

    public function renderBlockHtml($viewScript, ManipleBlockform_Form_Blockform $form, $id, array $vars = null)
    {
        $elements = $form->getBlockElements($id);

        if (isset($elements[ManipleBlockform_Form_Blockform::ELEMENT_DELETE])) {
            $elements[ManipleBlockform_Form_Blockform::ELEMENT_DELETE]->setAttrib('data-role', 'blockform.blockRemover');
        }

        $vars = array_merge(
            $elements,
            array(
                'block' => array_merge(
                    array(
                        'index'    => null,
                        'index0'   => null,
                        'first'    => null,
                        'last'     => null,
                    ),
                    (array) $vars,
                    array(
                        'id'       => $id,
                        'form'     => $form,
                        'elements' => $elements,
                    )
                ),
            )
        );

        return $this->view->renderScript($viewScript, $vars);
    }

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
    public function renderBlockTemplate(ManipleBlockform_Form_Blockform $form, $viewScript, array $options = null)
    {
        $cache = null;
        $vars = isset($options['vars']) ? (array) $options['vars'] : array();

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

        if ($vars) {
            // don't use cache if vars were provided
            // TODO Think if maybe we can cache simple variables
            $cache = null;
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
            $blockHtml = $this->renderBlockHtml($viewScript, $formCopy, $id, $vars);
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

            $blockHtml = '<script type="text/html" data-role="blockform.blockTemplate"'
                . $this->_htmlAttribs($attribs)
                . '>'
                . $blockHtml
                . '</script>';
        }

        if ($cache) {
            $cache->save($blockHtml, $cacheKey);
        }

        return $blockHtml;
    }

    /**
     * Options:
     *     'blockContainerTag'  => string, default 'div'
     *     'vars'               => array
     *
     * @param  ManipleBlockform_Form_Blockform $form
     * @param  string $viewScript
     * @param  array $options OPTIONAL
     * @return string
     */
    public function renderBlocks(ManipleBlockform_Form_Blockform $form, $viewScript, array $options = null)
    {
        if (isset($options['blockContainerTag'])) {
            $blockContainerTag = trim($options['blockContainerTag'], "<> \t\n\t");
        } else {
            // if no blockContainer tag is provided, use DIV as a default tag
            $blockContainerTag = 'div';
        }

        $html = '';

        $ids = $form->getBlockIds();
        $vars = isset($options['vars']) ? (array) $options['vars'] : array();

        for ($i = 0, $n = count($ids); $i < $n; ++$i) {
            $id = $ids[$i];
            $blockHtml = $this->renderBlockHtml($viewScript, $form, $id, array_merge($vars, array(
                'index'  => $i + 1,
                'index0' => $i,
                'first'  => $i === 0,
                'last'   => $i === $n - 1,
            )));
            $html .= $this->_finalizeBlock($blockHtml, $id, $options);
        }

        if ($blockContainerTag) {
            $html = $this->_wrap($html, $blockContainerTag, array(
                'data-role' => 'blockform.blockContainer'
            ));
        }

        return $html;
    }

    /**
     * Options:
     *     'blockTag'       => string
     *
     * @param string $blockHtml
     * @param mixed $id
     * @param array $options
     * @return string
     */
    protected function _finalizeBlock($blockHtml, $id, array $options = null)
    {
        $blockTag = null;

        if (isset($options['blockTag'])) {
            $blockTag = trim($options['blockTag'], "<> \r\t\n");
        }

        // Remove comments with placeholders
        $comments = array();
        $commentFormat = '__' . __CLASS__ . '__' . time() . '__%s__';
        $blockHtml = preg_replace_callback('/<!--.*?-->/', function ($match) use (&$comments, $commentFormat) {
            $id = sprintf($commentFormat, count($comments));
            $comments[$id] = $match[0];
            return $id;
        }, $blockHtml);

        // check if data-block-id attribute can be appended to first
        // encountered tag, if not, use default blockTag
        $pos = strpos($blockHtml, '>');

        if (empty($blockTag) && (false === $pos)) {
            $blockTag = 'div';
        }

        $attribs = array(
            'data-block-id' => $id,
        );

        if ($blockTag) {
            // block tag can be given as a tag name, or tag name with attributes
            $blockHtml = $this->_wrap($blockHtml, $blockTag, $attribs);
        } else {
            $blockHtml = substr_replace($blockHtml, $this->_htmlAttribs($attribs) . '>', $pos, 1);
        }

        // Restore comments
        foreach ($comments as $id => $comment) {
            $blockHtml = str_replace($id, $comment, $blockHtml);
        }

        return $blockHtml;
    }

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
     *     'adderWrapper'   => string  tag name OPTIONAL
     *     'autoInit'       => bool, default TRUE
     *
     * and options supported by {@see renderBlocks()} and
     * {@see renderBlockTemplate()} methods.
     *
     * @param  ManipleBlockform_Form_Blockform $form
     * @param  string $viewScript
     * @param  array $options OPTIONAL
     * @return string
     */
    public function render(ManipleBlockform_Form_Blockform $form, $viewScript, array $options = array())
    {
        $indexHtml = null;
        $adderHtml = null;
        $submitHtml = null;

        $attribs = array(
            'class' => 'blockform',
            'data-max-blocks' => $form->getMaxBlocks(),
            'data-min-blocks' => $form->getMinBlocks(),
        );

        if (!isset($options['autoInit']) || $options['autoInit']) {
            $attribs['data-init'] = 'blockform';
        }

        if (isset($options['id'])) {
            $attribs['id'] = $options['id'];
        }

        if ($index = $form->getElement(ManipleBlockform_Form_Blockform::ELEMENT_INDEX)) {
            if (isset($options['indexId'])) {
                $index->setAttrib('id', $options['indexId']);
            }
            $index->setAttrib('data-role', 'blockform.blockIndex');

            // render element to force initialization of id attribute
            $indexHtml = $index->render();
        }

        if (empty($options['noBlockAdder'])) {
            if ($adder = $form->getElement(ManipleBlockform_Form_Blockform::ELEMENT_ADD)) {
                $adderHtml = $this->renderAdder($adder, $options);
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
    }

    public function renderAdder(Zend_Form_Element $adder, array $options = null)
    {
        if (isset($options['adderClass'])) {
            $adder->setAttrib('class', $options['adderClass']);
        }
        if (isset($options['adderLabel'])) {
            $adder->setLabel($options['adderLabel']);
        }

        $escape = isset($options['adderEscape']) ? $options['adderEscape'] : true;
        $adder->setAttrib('escape', (bool) $escape);

        if (isset($options['adderId'])) {
            $adder->setAttrib('id', $options['adderId']);
        }
        $adder->setAttrib('data-role', 'blockform.blockAdder');

        $adderHtml = $adder->render();

        if (isset($options['adderWrapper'])) {
            $adderHtml = $this->_wrap($adderHtml, $options['adderWrapper']);
        }

        return $adderHtml;
    }

    protected function _wrap($content, $wrapperTag, array $attribs = array())
    {
        $wrapperTag = trim($wrapperTag, "<> \r\t\n");

        if ($wrapperTag) {
            return '<' . $wrapperTag . $this->_htmlAttribs($attribs) . '>'
                . $content
                . '</' . strtok($wrapperTag, "> \t\n\r") . '>';
        }

        return $content;
    }

}
