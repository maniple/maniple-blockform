<?php

/**
 * @author xemlock
 * @version 2014-03-07 / 2013-06-24
 */
abstract class ManipleBlockform_Form_Blockform extends Zefram_Form
{
    // nazwy pol formularza odpowiedzialne za logike dodawania
    // dynamicznych elementow
    const ELEMENT_ADD    = 'add';
    const ELEMENT_DELETE = 'delete';
    const ELEMENT_INDEX  = 'index';

    // nazwy pol w blokach maja dolaczony do nazwy nr bloku
    // poprzedzony tym separatorem
    const BLOCK_SEPARATOR = '__';

    protected $_blocks = array();

    protected $_dirtyIndex = true;
    protected $_maxBlocks = 10;
    protected $_minBlocks = 0;

    abstract public function createBlock($id);

    /**
     * Form constructor
     *
     * @param array|object $options
     */
    public function __construct($options = null) // {{{ 
    {
        if ($options !== null) {
            if (is_object($options) && method_exists($options, 'toArray')) {
                $options = $options->toArray();
            }
            $options = (array) $options;
        }

        if (!isset($options['elements']) || !is_array($options['elements'])) {
            $options['elements'] = array();
        }

        $options['elements'][self::ELEMENT_INDEX] = array(
            'type' => 'hidden',
            'options' => array(
                'decorators' => array(
                    'ViewHelper'
                ),
            ),
        );

        $options['elements'][self::ELEMENT_ADD] = array(
            'type' => 'button',
            'options' => array(
                'label' => 'Add new block',
                'attribs' => array( // render as BUTTON not as INPUT
                    'helper' => 'formButton',
                    'type' => 'submit',
                ),
                'decorators' => array(
                    'ViewHelper'
                ),
            ),
        );

        parent::__construct($options);
        $this->_init();
    } // }}}

    /**
     * Initialize form (used by extending classes)
     */
    protected function _init() // {{{
    {} // }}}

    public function setMaxBlocks($maxBlocks) // {{{
    {
        $this->_maxBlocks = max(0, (int) $maxBlocks);
    } // }}}

    public function getMaxBlocks() // {{{
    {
        return $this->_maxBlocks;
    } // }}}

    public function setMinBlocks($minBlocks) // {{{
    {
        $this->_minBlocks = max(0, (int) $minBlocks);
    } // }}}

    public function getMinBlocks() // {{{
    {
        return $this->_minBlocks;
    } // }}}

    /**
     * Returns current number of blocks in this form
     *
     * @return int
     */
    public function countBlocks() // {{{
    {
        return count($this->getBlocks());
    } // }}}

    public function updateIndex() // {{{
    {
        $element = parent::getElement(self::ELEMENT_INDEX);
        if ($element) {
            $element->setValue(implode(',', array_keys($this->_blocks)));
            $this->_dirtyIndex = false;
        }
    } // }}}

    public function getElement($name) // {{{
    {
        // Aktualizuje wartosc pola przechowujacego identyfikatory
        // uzywanych blokow.
        if ($this->_dirtyIndex) {
            $this->updateIndex();
        }

        return parent::getElement($name);
    } // }}}

    /**
     * Identyfikator bloku po odfiltrowaniu szkodliwych znakow.
     */
    protected function _id($id) // {{{
    {
        return preg_replace('/[^0-9a-z]/i', '', trim($id));
    } // }}}

    protected function _detectUsedBlocks($data) // {{{
    {
        $blocks = array();
        $delete = false; // czy kliknieto usuwanie bloku

        if (isset($data[self::ELEMENT_INDEX])) {
            $idx = explode(',', $data[self::ELEMENT_INDEX]);
            foreach ($idx as $id) {
                if ($this->_maxBlocks && count($blocks) == $this->_maxBlocks) {
                    break;
                }

                $id = $this->_id($id);
                if (!strlen($id)) {
                    continue;
                }

                // pomin bloki, dla ktorych przeslano 'delete'
                if (isset($data[self::ELEMENT_DELETE . self::BLOCK_SEPARATOR . $id])) {
                    $delete = true;
                    continue;
                }

                $blocks[$id] = true;
            }
        }

        return array(
            'blocks' => $blocks,
            'delete' => $delete
        );
    } // }}}

    /**
     * Usuwa z formularza wszystkie elementy nalezace
     * do bloku o podanym identyfikatorze.
     */
    public function removeBlock($id) // {{{
    {
        // do not getBlocks to retrieve block, to not trigger min block enforcer

        if (!isset($this->_blocks[$id])) {
            return false;
        }

        $removed = array();
        foreach ($this->_blocks[$id] as $name => $element) {
            $this->removeElement($element->getName());
            $removed[$name] = $element;
        }
        unset($this->_blocks[$id]);

        $this->_dirtyIndex = true;

        return $removed;
    } // }}}

    /**
     * @return array
     */
    public function getBlocks($id = null) // {{{
    {
        // enforce min blocks limit
        if (count($this->_blocks) < $this->_minBlocks) {
            $freeBlockId = $this->getFreeBlockId();
            while (count($this->_blocks) < $this->_minBlocks) {
                $this->createBlock($freeBlockId++);
            }
            $this->_dirtyIndex = true;
        }

        if (null === $id) {
            return $this->_blocks;
        }

        if (isset($this->_blocks[$id])) {
            return $this->_blocks[$id];
        }

        return array();
    } // }}}

    /**
     * @return array
     */
    public function getBlockIds() // {{{
    {
        return array_keys($this->getBlocks());
    } // }}}

    /**
     * @return Zend_Form_Element[]
     */
    public function getBlockElements($id) // {{{
    {
        return $this->getBlocks($id);
    } // }}}

    public function getIndexElement() // {{{
    {
        return $this->getElement(self::ELEMENT_INDEX);
    } // }}}

    /**
     * @return Zend_Form_Element|null
     */
    public function getBlockElement($id, $name) // {{{
    {
        $block = $this->getBlock($id);

        if (isset($block[$name])) {
            return $block[$name];
        }

        return null;
    } // }}}
    
    /**
     * Dodaje do bloku o podanym identyfikatorze nowy element.
     *
     * @param int $id                           id bloku
     * @param string|Zend_Form_Element $type    typ elementu lub element
     * @param string $name                      bazowa nazwa elementu
     * @param array $options                    dodatkowe opcje
     * @return Zend_Form_Element                dodany element
     */
    public function addBlockElement($id, $type, $name, $options = null) // {{{
    {
        $id = $this->_id($id);
        if (!strlen($id)) {
            throw new Exception('Invalid block identifier provided');
        }
        $key = $name . self::BLOCK_SEPARATOR . $id;
        if ($type instanceof Zend_Form_Element) {
            $type->setName($key);
        }
        $this->addElement($type, $key, $options);
        $element = parent::getElement($key);

        $this->_blocks[$id][$name] = $element;

        // zwroc element, bo dostac sie do niego z zewnatrz nie jest proste
        // (bo nie znamy po dodaniu jego id)

        $this->_dirtyIndex = true;

        return $element;
    } // }}}

    public function addBlockRemover($id) // {{{
    {
        $id = $this->_id($id);
        if (!strlen($id)) {
            throw new Exception('Invalid block identifier provided');
        }

        if (!isset($this->_blocks[$id][self::ELEMENT_DELETE])) {
            $element = $this->addBlockElement($id, 'button', self::ELEMENT_DELETE, array('label' => null, 'type' => 'submit', 'decorators' => 'ViewHelper'));
            $this->_blocks[$id][self::ELEMENT_DELETE] = $element;
            $this->_dirtyIndex = true;
        }

        return $this->_blocks[$id][self::ELEMENT_DELETE];
    } // }}}

    /**
     * Filtruje wartosci w bloku, wygodniejsze gdy nie ma potrzeby
     * tworzenia dodatkowych filtrow.
     */
    protected function _filterBlockValues(array &$values) // {{{
    {} // }}}

    protected function _getBlockValues($id) // {{{
    {
        $values = array();
        $block = $this->getBlockElements($id);

        if ($block) {
            foreach ($block as $name => $element) {
                if ($name == self::ELEMENT_DELETE) continue;
                $values[$name] = $element->getValue();
            }
            $this->_filterBlockValues($values);
        }

        return $values;
    } // }}}

    /**
     * Zwraca tablice wartosci elementow z podanego bloku, lub
     * wartosci ze wszystkich blokow.
     */
    public function getBlockValues($id = null) // {{{
    {
        if (null === $id) {
            $values = array();
            $blocks = $this->getBlocks();
            foreach ($blocks as $id => $block) {
                $values[$id] = $this->_getBlockValues($id);
            }
            return $values;
        }
        return $this->_getBlockValues($id);
    } // }}}

    /**
     * @param bool $suppressArrayNotation   Not used, required by strict standards
     * @return array
     */
    public function getValues($suppressArrayNotation = false) // {{{
    {
        $elementsBelongTo = null;
        if ($this->isArray()) {
            $elementsBelongTo = $this->getElementsBelongTo();
        }

        $values = $this->getBlockValues();
        if ($elementsBelongTo) {
            $values = array($elementsBelongTo => $values);
        }

        return $values;
    } // }}}

    /**
     * Ustawia wartosci elementow w podanym bloku.
     */
    public function setBlockValues($id, $values) // {{{
    {
        if (!isset($this->_blocks[$id])) {
            return;
        }
        foreach ((array) $values as $name => $value) {
            if ($name == self::ELEMENT_DELETE) continue;
            if (isset($this->_blocks[$id][$name])) {
                $this->_blocks[$id][$name]->setValue($value);
            }
        }
    } // }}}

    public function addBlock(array $values = null)
    {
        $id = $this->getFreeBlockId();
        $this->createBlock($id);
        $this->setBlockValues($id, $values);
        return $this;
    }

    public function addBlocks(array $blocks) // {{{
    {
        foreach ($blocks as $values) {
            $this->addBlock((array) $values);
        }
        return $this;
    } // }}}

    public function setBlocks(array $blocks) // {{{
    {
        $this->clearBlocks();
        $this->addBlocks($blocks);

        return $this;
    } // }}}

    /**
     * Extract the value by walking the array using given array path.
     *
     * Given an array path such as foo[bar][baz], returns the value of the last
     * element (in this case, 'baz').
     *
     * This implementation overrides a buggy implementation in ZF 1.12.3
     * that yields invalid results when path was not wholly matched.
     *
     * @param  array $value Array to walk
     * @param  string $arrayPath Array notation path of the part to extract
     * @param  bool &$found
     * @return mixed
     */
    protected function _dissolveArrayValue($value, $arrayPath, &$found = null)
    {
        $found = false;
        $arrayPath = explode('[', str_replace(']', '', $arrayPath));

        while ($part = array_shift($arrayPath)) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                break;
            }
            if (empty($arrayPath)) {
                $found = true;
                return $value;
            }
        }

        return null;
    }

    public function setDefaults(array $defaults)
    {
        if ($this->isArray()) {
            $elementsBelongTo = $this->getElementsBelongTo();
            $defaults = $this->_dissolveArrayValue($defaults, $elementsBelongTo, $found);
            if (!$found) {
                // if provided array contains no matching key skip setting defaults,
                // as there were no defaults given - this is important, as
                // array('blocks' => array()) is not the same as array() - in the first
                // example we have empty blocks, in the second, there is no change to apply
                // as no array path was matched.
                return $this;
            }
        }
        return $this->setBlocks((array) $defaults);
    }

    public function clearBlocks() // {{{
    {
        foreach ($this->_blocks as $elements) {
            foreach ($elements as $element) {
                $this->removeElement($element->getName());
            }
        }

        $this->_blocks = array();
        $this->_dirtyIndex = true;

        return $this;
    } // }}}

    protected $_specialSubmit = false;

    public function specialSubmitPressed() // {{{
    {
        return $this->_specialSubmit;
    } // }}}

    public function getFreeBlockId() // {{{
    {      
        return count($this->_blocks) == 0 ? 0 : intval(max(array_keys($this->_blocks)) + 1);
    } // }}}

    /**
     * Kluczowa w dzialaniu formularza metoda. Aktualizuje strukture formularza
     * a nastepnie sprawdza poprawnosc przeslanych danych.
     *
     * @param array $data przeslane dane
     */
    public function isValid($data) // {{{
    {
        if ($this->isArray()) {
            $elementsBelongTo = $this->getElementsBelongTo();
            $data = $this->_dissolveArrayValue($data, $elementsBelongTo);
        }

        $data = (array) $data;

        // zaktualizuj strukture formularza, sprawdz, ktore bloki zostaly
        // przeslane
        $opts = $this->_detectUsedBlocks($data);

        $specialSubmit = $opts['delete'];
        $idx = $opts['blocks'];

        // usun pominiete bloki
        foreach ($this->_blocks as $id => $elements) {
            if (!isset($idx[$id])) {
                // brak bloku w aktualnej wersji formularza
                $this->removeBlock($id);
                unset($idx[$id]);
            }
        }

        // dodaj istniejace bloki, tutaj mamy gwarancje, ze limit
        // blokow jest respektowany
        foreach ($idx as $id => $whatever) {
            $this->createBlock($id);
        }

        // enforce minimum number of blocks
        $freeBlockId = $this->getFreeBlockId();
        while (count($this->_blocks) < $this->_minBlocks) {
            $this->createBlock($freeBlockId++);
        }

        // jezeli byl klikniety add to dodaj nowy blok i zwroc false,
        // ale bez podawania powodu bledu --> po prostu zeby nie isc
        // dalej
        if (isset($data[self::ELEMENT_ADD])) {
            // dodaj nowy blok tylko jesli nie przekroczy limitu
            // (o ile ten jest ustalony)
            if (!$this->_maxBlocks || count($this->_blocks) < $this->_maxBlocks) {
                $freeBlockId = $this->getFreeBlockId();
                $this->createBlock($freeBlockId);
            }

            $specialSubmit = true;
        }

        $this->_dirtyIndex = true;
        $this->_specialSubmit = $specialSubmit;

        // zablokuj przycisk dodawania nowych blokow, jezeli limit
        // zostal osiagniety
        if ($this->_maxBlocks && count($this->_blocks) == $this->_maxBlocks) {
            if ($element = parent::getElement(self::ELEMENT_ADD)) {
                $element->setAttrib('disabled', true);
            }
        }

        if ($specialSubmit) {
            $this->populate($data);
            return false;
        }

        // original isValid implementation requires original data array
        if ($this->isArray()) {
            $parts = explode('[', str_replace(']', '', $elementsBelongTo));
            $newData = $data;
            while ($part = array_pop($parts)) {
                $newData = array($part => $newData);
            }
            $data = $newData;
        }

        return parent::isValid($data);
    } // }}}

    public function setName($name) // {{{
    {
        parent::setName($name);

        // Yep, this is crucial when changing form name when form is
        // array. All children must be notified about change in their
        // belongTo value.
        if ($this->isArray()) {
            $this->setElementsBelongTo($name);
        }

        return $this;
    } // }}}

    public function render(Zend_View_Interface $view = null) // {{{
    {
        // przenies na sam koniec add i submit
        $addElement = parent::getElement(self::ELEMENT_ADD);
        if ($addElement) {
            $this->removeElement(self::ELEMENT_ADD);
            $this->addElement($addElement);
        }

        return parent::render($view);
    } // }}}

    public function clearElements() // {{{
    {
        $this->clearBlocks();
        return parent::clearElements();
    } // }}}
}
