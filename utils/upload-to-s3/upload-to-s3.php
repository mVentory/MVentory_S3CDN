<?php

error_reporting(E_ALL | E_STRICT);

define('MAGENTO_ROOT', getcwd());

//Initialize Magento
require_once MAGENTO_ROOT . '/app/Mage.php';

Mage::setIsDeveloperMode(true);

ini_set('display_errors', 1);

umask(0);

$website = 'base';

if (isset($_SERVER['SERVER_NAME']))
  switch($_SERVER['SERVER_NAME']) {
    case 'website.address.com':
      $website = 'website_code';
      break;
  }

$mageRunType = 'website';

Mage::init($website, $mageRunType);

try {

$code = 'media_gallery';

$attribute = Mage::getSingleton('eav/config')
               ->getAttribute('catalog_product', $code);

$table
  = Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE;

$connection = Mage::getSingleton('core/resource')
                ->getConnection('default_read');

$select = $connection
            ->select()
            ->from($attribute->getResource()->getTable($table), 'value')
            ->where('attribute_id=?', $attribute->getId())
            ->distinct();

$images = $connection->fetchCol($select);

unset($attribute);
unset($connection);
unset($select);

Mage::log($website, null, 's3.log');

$totalImages = count($images);

Mage::log($totalImages . ' images to process', null, 's3.log');

//Get settings for S3
$accessKey = Mage::getStoreConfig(MVentory_CDN_Model_Config::ACCESS_KEY);
$secretKey = Mage::getStoreConfig(MVentory_CDN_Model_Config::SECRET_KEY);
$bucket = Mage::getStoreConfig(MVentory_CDN_Model_Config::BUCKET);
$prefix = Mage::getStoreConfig(MVentory_CDN_Model_Config::PREFIX);
$dimensions = Mage::getStoreConfig(MVentory_CDN_Model_Config::DIMENSIONS);

if (!($accessKey && $secretKey && $bucket && $prefix))
  return;

$cdnPrefix = $bucket . '/' . $prefix . '/';

$dimensions = str_replace(', ', ',', $dimensions);
$dimensions = explode(',', $dimensions);

$meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

$config = Mage::getSingleton('catalog/product_media_config');

$destSubdirs = array('image', 'small_image', 'thumbnail');

foreach ($destSubdirs as $destSubdir) {
  $placeholder = Mage::getModel('catalog/product_image')
                   ->setDestinationSubdir($destSubdir)
                   ->setBaseFile(null)
                   ->getBaseFile();

  $result = copy($placeholder, $config->getMediaPath(basename($placeholder)));

  if ($result === true)
    $images[] = '/' . basename($placeholder);
  else
    Mage::log('Error on copy ' . $placeholder . ' to media folder',
              null, 's3.log');
}

$s3 = new Zend_Service_Amazon_S3($accessKey, $secretKey);

$imageNumber = 1;

foreach ($images as $fileName) {
  Mage::log('Processing image ' . $imageNumber++ . ' of '
            . $totalImages, null, 's3.log');

  $cdnPath = $cdnPrefix . 'full' . $fileName;

  $file = $config->getMediaPath($fileName);

  if (!$s3->isObjectAvailable($cdnPath)) {
    Mage::log('Trying to upload original file ' . $file . ' as ' . $cdnPath,
              null, 's3.log');

    try {
      $s3->putFile($file, $cdnPath, $meta);
    } catch (Exception $e) {
      Mage::log($e->getMessage(), null, 's3.log');

      continue;
    }
  } else
    Mage::log('File ' . $file . ' has been already uploaded', null, 's3.log');

  if (!count($dimensions))
    continue;

  foreach ($dimensions as $dimension) {
    $newCdnPath = $cdnPrefix . $dimension . $fileName;

    if ($s3->isObjectAvailable($newCdnPath)) {
      Mage::log('Resized ('. $dimension .') file ' . $file
                . ' has been already uploaded', null, 's3.log');

      continue;
    }

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
        Mage::log($e->getMessage() . ' (' . $fileName . ')', null, 's3.log');
      }
    else
      Mage::log('Use existing resized ('. $dimension .') file ' . $newFile,
                null, 's3.log');

    Mage::log('Trying to upload resized ('. $dimension .') file ' . $newFile
              . ' as ' . $newCdnPath, null, 's3.log');

    try {
      $s3->putFile($newFile, $newCdnPath, $meta);
    } catch (Exception $e) {
      Mage::log($e->getMessage(), null, 's3.log');
    }
  }
}

} catch (Exception $e) {
  Mage::printException($e);
}
