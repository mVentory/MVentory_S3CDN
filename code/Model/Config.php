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
 * Config data
 *
 * @package MVentory/Productivity
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
abstract class MVentory_CDN_Model_Config {

  //XML paths for config values
  const ACCESS_KEY = 'cdn/settings/access_key';
  const SECRET_KEY = 'cdn/settings/secret_key';
  const BUCKET = 'cdn/settings/bucket';
  const PREFIX = 'cdn/settings/prefix';
  const DIMENSIONS = 'cdn/settings/resizing_dimensions';
  const CACHE_TIME = 'cdn/settings/cache_time';

  //Amazon's specific header used to set max age parameter for caching
  const AMAZON_CACHE_CONTROL = 'x-amz-meta-Cache-Control';

}