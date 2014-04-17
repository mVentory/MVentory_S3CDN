<?php

require_once 'Gd2.php';
require_once 'S3.php';

define('MAX_TRIES', 10);

//Settings
$accessKey = '';
$secretKey = '';
$bucket = '';
$website = '';
$dimensions = '';

$redownloadImages = false;
$overwriteThumbs = true;
$recalculateThumbs = false;
$compareThumbsBySize = false;

//Prepare parameters
$dimensions = parseDimensions($dimensions, $website);

//Prepare values
$imgPrefix = $website . '/full/';
$imgPrefixLen = strlen($imgPrefix);
$imgPathLen = $imgPrefixLen + 4;

//Initialise S3 object
$s3 = new S3($accessKey, $secretKey, true);

//Get list of images from the bucket
$images = $s3->getBucket($bucket, $imgPrefix);

$imgNumber = 1;
$totalImgs = count($images);

echo 'Number of images: ', $totalImgs, "\n";

if (!$totalImgs)
  exit('No images in the bucket');

$_reuploadThumbs = $overwriteThumbs || $compareThumbsBySize;

$images = array_keys($images);

foreach ($images as $image) {
  echo $image, ' (', $imgNumber++, ' of ', $totalImgs, ')', "\n";

  if (!file_exists($image) || $redownloadImages) {

    //Create directories for the image
    createDirectory(substr($image, 0, $imgPathLen));

    for ($i = 1; $i <= MAX_TRIES; $i++) {
      if ($s3->getObject($bucket, $image, $image) !== false)
        break;

      if ($i == MAX_TRIES) {
        echo 'Failed to download ', $image, "\n";

        if (file_exists($image))
          unlink($image);

        //Go to next image
        continue 2;
      }
    }
  } else
    echo 'File ', $image, ' was already downloaded', "\n";

  $dispersionPath = substr($image, $imgPrefixLen);

  foreach ($dimensions as $thumbPrefix => $dimension) {
    $thumb = $thumbPrefix . $dispersionPath;

    $thumbInfo = $s3->getObjectInfo($bucket, $thumb, $compareThumbsBySize);

    //Check if thumb exists in the bucket
    if ($thumbInfo && !$_reuploadThumbs) {
      echo 'Thumb ', $thumb, ' exists', "\n";

      continue;
    }

    if (!file_exists($thumb) || $recalculateThumbs) {
      //Create directories for the thumb
      createDirectory(substr($thumb, 0, strlen($thumbPrefix) + 4));

      $adapter = new Varien_Image_Adapter_Gd2();

      //Default settings from Mage
      $adapter->keepAspectRatio(true);
      $adapter->keepFrame(false);
      $adapter->keepTransparency(true);
      $adapter->constrainOnly(true);
      $adapter->backgroundColor(array(255, 255, 255));
      $adapter->quality(100);

      try {
        $adapter->open($image);
        $adapter->resize($dimension['width'], $dimension['height']);
        $adapter->save($thumb);
      } catch (Exception $e) {
        echo 'Can\'t resize ', $image, ' (', $e->getMessage(), ')', "\n";

        continue;
      }
    }

    if (file_exists($thumb)) {
      if ($compareThumbsBySize && $thumbInfo
          && filesize($thumb) == $thumbInfo['size']) {

        echo 'Thumb ', $thumb, ' has same size on S3. Ignoring', "\n";

        continue;
      }

      for ($i = 1; $i <= MAX_TRIES; $i++) {
        //Upload thumb to the bucket
        $result = $s3->putObject($s3->inputFile($thumb),
                                 $bucket,
                                 $thumb,
                                 S3::ACL_PUBLIC_READ);

        if ($result)
          break;

        if ($i == MAX_TRIES)
          echo 'Failed to upload ', $thumb, "\n";
      }
    }
  }
}

function parseDimensions ($dimensions, $website) {
  $_dimensions = explode(',', str_replace(', ', ',', $dimensions));

  $result = array();

  foreach ($_dimensions as $_dimension) {
    $dimension = explode('x', $_dimension);

    $result[$website . '/' . $_dimension . '/'] = array(
      'width' => empty($dimension[0]) ? null : (int) $dimension[0],
      'height' => empty($dimension[1]) ? null : (int) $dimension[1]
    );
  }

  return $result;
}

function createDirectory ($directory) {
  if (!file_exists($directory))
    mkdir($directory, 0755, true);
}
