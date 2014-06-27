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
 * Data helper
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_CDN_Helper_Data extends Mage_Core_Helper_Abstract {

  private $_prefix = null;
  private $_productHelper = null;

  function __construct () {

    //Load product helper from MVentory_API if it's installed and is activated
    //The helper is used to get correct website for the product
    //when MVentory_API extension is used
    if ($this->isModuleEnabled('MVentory_API'))
      $this->_productHelper = Mage::helper('mventory/product');
  }

  /**
   * Downloads image from S3 by its absolute path on FS.
   *
   * @param string $path Absolute or relative path to image
   * @param int|string|Mage_Core_Model_Website Website for settings
   * @param string $size Image size ('full' size will be used if null)
   *
   * @return string|bool Return absolute path to downloaded file or false
   *                     if error occured
   */
  public function download ($path, $website = null, $size = null) {
    $s3 = $this->_getS3($website);

    $object = $this->_getObject($path, $size);

    if (!file_exists(dirname($path)))
      mkdir(dirname($path), 0777, true);

    if ($s3->getObjectStream($object, $path) === false) {

      //When error happens it saves response from S3 with error message
      //to the specified file. We need to remove that file so Magento code
      //don't think that image was downloaded successfully
      if (file_exists($path))
        unlink($path);

      return false;
    }

    return $path;
  }

  /**
   * Uploads image to S3. Uses absolute path to image for S3 object name
   *
   * @param string $from Absolute path to source image
   * @param string $path Absolute or relative path to create S3 object name
   * @param int|string|Mage_Core_Model_Website Website for settings
   * @param string $size Image size ('full' size will be used if null)
   *
   * @return bool
   */
  public function upload ($from, $path, $website = null, $size = null) {
    //Prepare meta data for uploading. All uploaded images are public
    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    return $this
             ->_getS3($website)
             ->putFileStream($from, $this->_getObject($path, $size), $meta);
  }

  /**
   * Returns configured S3 object.
   * Uses passed website or wesbite which current product is asssigned to
   * or current website to get wesbite's prefix on S3
   *
   * @param int|string|Mage_Core_Model_Website Website for settings
   *
   * @return Zend_Service_Amazon_S3
   */
  protected function _getS3 ($website = null) {
    $store = ($this->_productHelper && $website === null)
               ? $this
                   ->_productHelper
                   ->getWebsite()
                   ->getDefaultStore()
                 : Mage::app()->getWebsite($website)->getDefaultStore();

    $accessKey = $store->getConfig(MVentory_CDN_Model_Config::ACCESS_KEY);
    $secretKey = $store->getConfig(MVentory_CDN_Model_Config::SECRET_KEY);
    $bucket = $store->getConfig(MVentory_CDN_Model_Config::BUCKET);
    $prefix = $store->getConfig(MVentory_CDN_Model_Config::PREFIX);

    $this->_prefix = $bucket . '/' . $prefix . '/';

    return new Zend_Service_Amazon_S3($accessKey, $secretKey);
  }

  /**
   * Build name of S3 object from the absolute path of image
   *
   * @param string $path Absolute or relative path to image. The parameter
   *                     will be updated with absolute path
   *                     if relative was given
   * @param string $size Image size ('full' size will be used if null)
   *
   * $return string Name of S3 object
   */
  protected function _getObject (&$path, $size = null) {
    $config = Mage::getSingleton('catalog/product_media_config');

    $imagePath = str_replace($config->getMediaPath($size), '', $path);

    if (strpos($imagePath, '/') !== 0)
      $imagePath = '/' . $imagePath;

    if ($imagePath == $path)
      $path = $config->getMediaPath($size . $path);

    return $this->_prefix . ($size ? $size : 'full') . $imagePath;
  }
}
