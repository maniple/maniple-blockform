<?php

class ManipleBlockform_Form_BlockformTest extends PHPUnit_Framework_TestCase
{
    public function testSetDefaults()
    {
        $form = new F();
        $form->setDefaults(array(
            'x' => array(
                'name' => 'XXX',
            ),
        ));

        $this->assertEquals(array(
            0 => array(
                'name' => 'XXX',
            )
        ), $form->getBlockValues());
    }

    public function testSetDefaultsViaSubForm()
    {
        $form = new Zend_Form();
        $form->addSubForm(new F(array(
            'isArray' => true,
        )), 'autobots');
        $form->setDefaults(array(
            'autobots' => array(
                array('name' => 'Defensor'),
                array('name' => 'Computron'),
                array('name' => 'Omega Supreme'),
            ),
        ));

        $this->assertEquals(
            array(
                array('name' => 'Defensor'),
                array('name' => 'Computron'),
                array('name' => 'Omega Supreme'),
            ),
            $form->getSubForm('autobots')->getBlockValues()
        );

        $form->setDefaults(array(
            'decepticon' => 'Trypticon',
        ));

        $this->assertEquals(
            array(
                array('name' => 'Defensor'),
                array('name' => 'Computron'),
                array('name' => 'Omega Supreme'),
            ),
            $form->getSubForm('autobots')->getBlockValues()
        );
    }

    // testMinBlocks
    // testMaxBlocks
}

class F extends ManipleBlockform_Form_Blockform
{
    public function createBlock($id)
    {
        $this->addBlockElement($id, 'text', 'name', array(
            'type' => 'text',
            'options' => array()
        ));
    }
}
