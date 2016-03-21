<?php

class ManipleBlockform_Form_BlockformTest extends PHPUnit_Framework_TestCase
{
    public function testSetDefaults()
    {
        $form = new F();
        $form->setDefaults(array(
            'x' => array(
                'x' => 'XXX',
            ),
        ));

        $this->assertEquals(array(
            'x' => array(
                'x' => 'XXX',
            )
        ), $form->getBlocks());
    }
}

class F extends ManipleBlockform_Form_Blockform
{
    public function createBlock($id)
    {
        $this->addBlockElement($id, 'text', 'x', array(
            'type' => 'text',
            'options' => array()
        ));
    }
}
