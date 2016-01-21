<?php

class MVentory_CDN_Block_Adminhtml_Catalog_Product_Helper_Form_Gallery_Content extends Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Gallery_Content {

    public function getImagesJson()
    {
        $store = Mage::app()->getStore();

        if(is_array($this->getElement()->getValue())) {
            $value = $this->getElement()->getValue();
            if(count($value['images'])>0) {
                foreach ($value['images'] as &$image) {
                    $image['url'] =
                        $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
                        . $store->getConfig(MVentory_CDN_Model_Config::PREFIX)
                        . '/'
                        . '100x100'
                        . $image['file'];
                }
                return Mage::helper('core')->jsonEncode($value['images']);
            }
        }
        return '[]';
    }

} 