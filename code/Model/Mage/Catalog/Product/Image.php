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
 * Catalog product image model
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_CDN_Model_Mage_Catalog_Product_Image
  extends Mage_Catalog_Model_Product_Image {

  /**
   * First check this file on FS or DB. If it doesn't then download it from S3
   *
   * @param string $filename
   *
   * @return bool
   */
  protected function _fileExists ($filename) {
    return parent::_fileExists($filename)
             || Mage::helper('cdn')->download($filename);
  }
}
