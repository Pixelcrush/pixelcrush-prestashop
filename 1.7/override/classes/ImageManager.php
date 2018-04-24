<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class ImageManager extends ImageManagerCore
{
    public static function resize($src_file, $dst_file, $dst_width = null, $dst_height = null, $file_type = 'jpg',
                                $force_type = false, &$error = 0, &$tgt_width = null, &$tgt_height = null, $quality = 5,
                                &$src_width = null, &$src_height = null)
    {
        // Allow create/modify master image, block thumbnails
        if (preg_match('/^[0-9]+\-[_a-zA-Z0-9-]*\.(jpg|png|jpeg)$/', $dst_file)
            && Configuration::get('PIXELCRUSH_NO_THUMBNAILS')
        ) {
            return true;
        }

        return parent::resize($src_file, $dst_file, $dst_width, $dst_height, $file_type,
            $force_type, $error, $tgt_width, $tgt_height, $quality,
            $src_width, $src_height);
    }
}
