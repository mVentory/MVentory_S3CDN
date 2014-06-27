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
 * Catalog image helper
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_CDN_Helper_Mage_Catalog_Image extends Mage_Catalog_Helper_Image {

  private $_productHelper = null;

  function __construct () {

    //Load product helper from MVentory_API if it's installed and is activated
    //The helper is used to get correct website for the product
    //when MVentory_API extension is used
    if ($this->isModuleEnabled('MVentory_API'))
      $this->_productHelper = Mage::helper('mventory/product');
  }

  /**
   * Initialize Helper to work with Image
   *
   * @param Mage_Catalog_Model_Product $product
   * @param string $attributeName
   * @param mixed $imageFile
   * @return Mage_Catalog_Helper_Image
  */
  public function init (Mage_Catalog_Model_Product $product, $attributeName,
                        $imageFile = null) {

    $this->_reset();

    $this->_setModel(new Varien_Object());

    $this->_getModel()->setDestinationSubdir($attributeName);
    $this->setProduct($product);

    $path = 'design/watermark/' . $attributeName . '_';

    $this->watermark(
      Mage::getStoreConfig($path . 'image'),
      Mage::getStoreConfig($path . 'position'),
      Mage::getStoreConfig($path . 'size'),
      Mage::getStoreConfig($path . 'imageOpacity')
    );

    if ($imageFile)
      $this->setImageFile($imageFile);

    return $this;
  }

  /**
   * Retrieve original image height
   *
   * @return int|null
   */
  public function getOriginalHeight () {
    return null;
  }

  /**
   * Retrieve original image width
   *
   * @return int|null
   */
  public function getOriginalWidth () {
    return null;
  }

  public function __toString() {
    $model = $this->_getModel();
    $product = $this->getProduct();

    $destSubdir = $model->getDestinationSubdir();

    if (!$imageFileName = $this->getImageFile())
      $imageFileName = $product->getData($destSubdir);

    if ($imageFileName == 'no_selection') {
      if (($bkThumbnail = $product->getData('bk_thumbnail_'))
          && ($destSubdir == 'image' || $destSubdir == 'small_image'))
        return $bkThumbnail . '&zoom=' . ($destSubdir == 'image' ? 1 : 5);

      $placeholder = Mage::getModel('catalog/product_image')
                       ->setDestinationSubdir($destSubdir)
                       ->setBaseFile(null)
                       ->getBaseFile();

      $imageFileName = '/' . basename($placeholder);
    }

    $width = $model->getWidth();
    $height = $model->getHeight();

    //!!!TODO: remove hack for 75x75 images
    if ($width == $height && $width != 75)
      $height = null;

    if (($dimensions = $width . 'x' . $height) == 'x')
      $dimensions = 'full';

    $store = $this->_productHelper
               ? $this->_productHelper->getWebsite($product)->getDefaultStore()
                 : Mage::app()->getStore();

    return $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
           . $store->getConfig(MVentory_CDN_Model_Config::PREFIX)
           . '/'
           . $dimensions
           . $imageFileName;
  }
}
