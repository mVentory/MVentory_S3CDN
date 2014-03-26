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
 * Controller for placeholders uploading
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_CDN_PlaceholdersController
  extends Mage_Adminhtml_Controller_Action {

  const ERROR = Mage_Core_Model_Message::ERROR;
  const WARNING = Mage_Core_Model_Message::WARNING;
  const NOTICE = Mage_Core_Model_Message::NOTICE;
  const SUCCESS = Mage_Core_Model_Message::SUCCESS;

  protected function _construct() {
    $this->setUsedModuleName('MVentory_CDN');
  }

  /**
   * Upload placeholders to CDN
   *
   * @return null
   */
  public function uploadAction () {
    $website = $this
                 ->getRequest()
                 ->getParam('website');

    $website = Mage::app()->getWebsite($website);

    if (!$website->getId())
      return $this->_back('No website parameter', self::ERROR, $website);

    $store = $website->getDefaultStore();

    $accessKey = $store->getConfig(MVentory_CDN_Model_Config::ACCESS_KEY);
    $secretKey = $store->getConfig(MVentory_CDN_Model_Config::SECRET_KEY);
    $bucket = $store->getConfig(MVentory_CDN_Model_Config::BUCKET);
    $prefix = $store->getConfig(MVentory_CDN_Model_Config::PREFIX);
    $dimensions = $store->getConfig(MVentory_CDN_Model_Config::DIMENSIONS);

    Mage::log($dimensions);

    if (!($accessKey && $secretKey && $bucket && $prefix))
      return $this->_back(
        'CDN settings are not specified',
        self::ERROR,
        $website
      );

    unset($path);

    $config = Mage::getSingleton('catalog/product_media_config');

    $destSubdirs = array('image', 'small_image', 'thumbnail');

    $placeholders = array();

    $appEmulation = Mage::getModel('core/app_emulation');

    $env = $appEmulation->startEnvironmentEmulation($store->getId());

    foreach ($destSubdirs as $destSubdir) {
      $placeholder = Mage::getModel('catalog/product_image')
                       ->setDestinationSubdir($destSubdir)
                       ->setBaseFile(null)
                       ->getBaseFile();

      $basename = basename($placeholder);

      $result = copy($placeholder, $config->getMediaPath($basename));

      if ($result !== true)
        return $this
                 ->_back('Error on copy ' . $placeholder . ' to media folder',
                         self::ERROR,
                         $website);

      $placeholders[] = '/' . $basename;
    }

    $appEmulation->stopEnvironmentEmulation($env);

    unset($store);
    unset($appEmulation);

    $s3 = new Zend_Service_Amazon_S3($accessKey, $secretKey);

    $cdnPrefix = $bucket . '/' . $prefix . '/';

    $dimensions = str_replace(', ', ',', $dimensions);
    $dimensions = explode(',', $dimensions);

    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    foreach ($placeholders as $fileName) {
      $cdnPath = $cdnPrefix . 'full' . $fileName;

      $file = $config->getMediaPath($fileName);

      try {
        $s3->putFile($file, $cdnPath, $meta);
      } catch (Exception $e) {
        return $this->_back($e->getMessage(), self::ERROR, $website);
      }

      if (!count($dimensions))
        continue;

      foreach ($dimensions as $dimension) {
        $newCdnPath = $cdnPrefix . $dimension . $fileName;

        $productImage = Mage::getModel('catalog/product_image');

        $destinationSubdir = '';

        foreach ($destSubdirs as $destSubdir) {
          $newFile = $productImage
                       ->setDestinationSubdir($destSubdir)
                       ->setSize($dimension)
                       ->setBaseFile($fileName)
                       ->getNewFile();

          if (file_exists($newFile)) {
            $destinationSubdir = $destSubdir;

            break;
          }
        }

        if ($destinationSubdir == '')
          try {
            $newFile = $productImage
                         ->setDestinationSubdir($destinationSubdir)
                         ->setSize($dimension)
                         ->setBaseFile($fileName)
                         ->resize()
                         ->saveFile()
                         ->getNewFile();
          } catch (Exception $e) {
            return $this->_back($e->getMessage(), self::ERROR, $website);
          }

        try {
          $s3->putFile($newFile, $newCdnPath, $meta);
        } catch (Exception $e) {
          return $this->_back($e->getMessage(), self::ERROR, $website);
        }
      }
    }

    return $this->_back('Successfully uploaded all placeholders',
                        self::SUCCESS,
                        $website);
  }

  protected function _back ($msg, $type, $website) {
    $path = 'adminhtml/system_config/edit';

    $params = array(
      'section' => 'cdn',
      'website' => $website->getCode()
    );

    $msg = $this->__($msg);

    switch (strtolower($type)) {
      case self::ERROR :
        $message = new Mage_Core_Model_Message_Error($msg);
        break;
      case self::WARNING :
        $message = new Mage_Core_Model_Message_Warning($msg);
        break;
      case self::SUCCESS :
        $message = new Mage_Core_Model_Message_Success($msg);
        break;
      default:
        $message = new Mage_Core_Model_Message_Notice($msg);
        break;
    }

    Mage::getSingleton('adminhtml/session')->addMessage($message);

    $this->_redirect($path, $params);
  }
}
