<?php

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
