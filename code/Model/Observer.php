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
 * Event handlers
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_CDN_Model_Observer {

  public function upload ($observer) {
    $product = $observer->getEvent()->getProduct();

    //There's nothing to process because we're using images
    //from original product in duplicate
    if ($product->getIsDuplicate()
        || $product->getData('mventory_update_duplicate'))
      return;

    $images = $observer->getEvent()->getImages();

    //Use product helper from MVentory_API if it's installed and is activated
    //The helper is used to get correct store for the product when MVentory_API
    //extension is used
    //Change current store if product's store is different for correct
    //file name of images
    if (Mage::helper('core')->isModuleEnabled('MVentory_API')) {
      $store = Mage::helper('mventory/product')
        ->getWebsite($product)
        ->getDefaultStore();

      $changeStore = $store->getId() != Mage::app()->getStore()->getId();
    } else {
      $store = Mage::app()->getStore();
      $changeStore = false;
    }

    //Get settings for S3
    $accessKey = $store->getConfig(MVentory_CDN_Model_Config::ACCESS_KEY);
    $secretKey = $store->getConfig(MVentory_CDN_Model_Config::SECRET_KEY);
    $bucket = $store->getConfig(MVentory_CDN_Model_Config::BUCKET);
    $prefix = $store->getConfig(MVentory_CDN_Model_Config::PREFIX);
    $dimensions = $store->getConfig(MVentory_CDN_Model_Config::DIMENSIONS);
    $cacheTime = (int) $store->getConfig(MVentory_CDN_Model_Config::CACHE_TIME);

    //Return if S3 settings are empty
    if (!($accessKey && $secretKey && $bucket && $prefix))
      return;

    //Build prefix for all files on S3
    $cdnPrefix = $bucket . '/' . $prefix . '/';

    //Parse dimension. Split string to pairs of width and height
    $dimensions = str_replace(', ', ',', $dimensions);
    $dimensions = explode(',', $dimensions);

    //Prepare meta data for uploading. All uploaded images are public
    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    if ($cacheTime > 0)
      $meta[MVentory_CDN_Model_Config::AMAZON_CACHE_CONTROL]
        = 'max-age=' . $cacheTime;

    if ($changeStore) {
      $emu = Mage::getModel('core/app_emulation');
      $origEnv = $emu->startEnvironmentEmulation($store);
    }

    $config = Mage::getSingleton('catalog/product_media_config');

    $s3 = new Zend_Service_Amazon_S3($accessKey, $secretKey);

    foreach ($images['images'] as &$image) {
      //Process new images only
      if (isset($image['value_id']))
        continue;

      //Get name of the image and create its key on S3
      $fileName = $image['file'];
      $cdnPath = $cdnPrefix . 'full' . $fileName;

      //Full path to uploaded image
      $file = $config->getMediaPath($fileName);

      //Check if object with the key exists
      if ($s3->isObjectAvailable($cdnPath)) {
        $position = strrpos($fileName, '.');

        //Split file name and extension
        $name = substr($fileName, 0, $position);
        $ext = substr($fileName, $position);

        //Search key
        $_key = $prefix .'/full' . $name . '_';

        //Get all objects which is started with the search key
        $keys = $s3->getObjectsByBucket($bucket, array('prefix' => $_key));

        $index = 1;

        //If there're objects which names begin with the search key then...
        if (count($keys)) {
          $extLength = strlen($ext);

          $_keys = array();

          //... store object names without extension as indeces of the array
          //for fast searching
          foreach ($keys as $key)
            $_keys[substr($key, 0, -$extLength)] = true;

          //Find next unused object name
          while(isset($_keys[$_key . $index]))
            ++$index;

          unset($_keys);
        }

        //Build new name and path with selected index
        $fileName = $name . '_' . $index . $ext;
        $cdnPath = $cdnPrefix . 'full' . $fileName;

        //Get new name for uploaded file
        $_file = $config->getMediaPath($fileName);

        //Rename file uploaded to Magento
        rename($file, $_file);

        //Update values of media attribute in the product after renaming
        //uploaded image if the image was marked as 'image', 'small_image'
        //or 'thumbnail' in the product
        foreach ($product->getMediaAttributes() as $mediaAttribute) {
          $code = $mediaAttribute->getAttributeCode();

          if ($product->getData($code) == $image['file'])
            $product->setData($code, $fileName);
        }

        //Save its new name in Magento
        $image['file'] = $fileName;
        $file = $_file;

        unset($_file);
      }

      //Upload original image
      if (!$s3->putFile($file, $cdnPath, $meta)) {
        $msg = 'Can\'t upload original image (' . $file . ') to S3 with '
               . $cdnPath . ' key';

        if ($changeStore)
          $emu->stopEnvironmentEmulation($origEnv);

        throw new Mage_Core_Exception($msg);
      }

      //Go to next newly uploaded image if image dimensions for resizing
      //were not set
      if (!count($dimensions))
        continue;

      //For every dimension...
      foreach ($dimensions as $dimension) {
        //... resize original image and get path to resized image
        $newFile = Mage::getModel('catalog/product_image')
                     ->setDestinationSubdir('image')
                     ->setSize($dimension)
                     ->setKeepFrame(false)
                     ->setConstrainOnly(true)
                     ->setBaseFile($fileName)
                     ->resize()
                     ->saveFile()
                     ->getNewFile();

        //Build S3 path for the resized image
        $newCdnPath = $cdnPrefix . $dimension . $fileName;

        //Upload resized images
        if (!$s3->putFile($newFile, $newCdnPath, $meta)) {
          $msg = 'Can\'t upload resized (' . $dimension . ') image (' . $file
                 . ') to S3 with ' . $cdnPath . ' key';

          if ($changeStore)
            $emu->stopEnvironmentEmulation($origEnv);

          throw new Mage_Core_Exception($msg);
        }
      }
    }

    if ($changeStore)
      $emu->stopEnvironmentEmulation($origEnv);
  }
}
