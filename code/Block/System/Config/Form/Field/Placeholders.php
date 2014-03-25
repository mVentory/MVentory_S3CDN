<?php

class MVentory_CDN_Block_System_Config_Form_Field_Placeholders
  extends Mage_Adminhtml_Block_System_Config_Form_Field {

  protected function _getElementHtml (Varien_Data_Form_Element_Abstract $elem) {
    $website = $this
                 ->getRequest()
                 ->getParam('website', '');

    $data = array(
      'label' => $this->__('Upload placeholders to CDN'),
      'onclick' => 'setLocation(\''
                   . $this->getUrl('cdn/placeholders/upload/',
                                   array('website' => $website))
                   . '\')'
    );

    return $this
             ->getLayout()
             ->createBlock('adminhtml/widget_button')
             ->setData($data)
             ->toHtml();
  }
}
