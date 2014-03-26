<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE-OSL.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @package MVentory/CDN
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

/**
 * Button for placeholders uploading
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
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
