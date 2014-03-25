<?php

class MVentory_CDN_Helper_Data extends Mage_Core_Helper_Abstract {

  private $_prefix = null;

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

    return $s3->getObjectStream($object, $path) ? $path : false;
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

    //!!!TODO: we can't use current website because some product can be created
    //throw another website and all of its images is uploaded to that website
    //scope. So we depend on MVentory logic.
    $store = $website === null
               ? Mage::helper('mventory_tm/product')
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
