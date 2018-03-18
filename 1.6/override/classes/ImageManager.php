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
        if (PHP_VERSION_ID < 50300) {
            clearstatcache();
        } else {
            clearstatcache(true, $src_file);
        }

        // Allow create/modify master image, block thumbnails
        if ((int)Configuration::get('PIXELCRUSH_NO_THUMBNAILS') && strpos(basename($dst_file), '-') !== false) {
            return true;
        }

        if (!file_exists($src_file) || !filesize($src_file)) {
            return !($error = self::ERROR_FILE_NOT_EXIST);
        }

        list($tmp_width, $tmp_height, $type) = getimagesize($src_file);
        $rotate = 0;
        if (function_exists('exif_read_data') && function_exists('mb_strtolower')) {
            $exif = @exif_read_data($src_file);

            if ($exif && isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $src_width = $tmp_width;
                        $src_height = $tmp_height;
                        $rotate = 180;
                        break;

                    case 6:
                        $src_width = $tmp_height;
                        $src_height = $tmp_width;
                        $rotate = -90;
                        break;

                    case 8:
                        $src_width = $tmp_height;
                        $src_height = $tmp_width;
                        $rotate = 90;
                        break;

                    default:
                        $src_width = $tmp_width;
                        $src_height = $tmp_height;
                }
            } else {
                $src_width = $tmp_width;
                $src_height = $tmp_height;
            }
        } else {
            $src_width = $tmp_width;
            $src_height = $tmp_height;
        }

        // If PS_IMAGE_QUALITY is activated, the generated image will be a PNG with .jpg as a file extension.
        // This allow for higher quality and for transparency. JPG source files will also benefit from a higher quality
        // because JPG reencoding by GD, even with max quality setting, degrades the image.
        if (Configuration::get('PS_IMAGE_QUALITY') == 'png_all'
            || (Configuration::get('PS_IMAGE_QUALITY') == 'png' && $type == IMAGETYPE_PNG) && !$force_type) {
            $file_type = 'png';
        }

        if (!$src_width) {
            return !($error = self::ERROR_FILE_WIDTH);
        }
        if (!$dst_width) {
            $dst_width = $src_width;
        }
        if (!$dst_height) {
            $dst_height = $src_height;
        }

        $width_diff = $dst_width / $src_width;
        $height_diff = $dst_height / $src_height;

        $ps_image_generation_method = Configuration::get('PS_IMAGE_GENERATION_METHOD');
        if ($width_diff > 1 && $height_diff > 1) {
            $next_width = $src_width;
            $next_height = $src_height;
        } else {
            if ($ps_image_generation_method == 2 || (!$ps_image_generation_method && $width_diff > $height_diff)) {
                $next_height = $dst_height;
                $next_width = round(($src_width * $next_height) / $src_height);
                $dst_width = (int)(!$ps_image_generation_method ? $dst_width : $next_width);
            } else {
                $next_width = $dst_width;
                $next_height = round($src_height * $dst_width / $src_width);
                $dst_height = (int)(!$ps_image_generation_method ? $dst_height : $next_height);
            }
        }

        if (!ImageManager::checkImageMemoryLimit($src_file)) {
            return !($error = self::ERROR_MEMORY_LIMIT);
        }

        $tgt_width  = $dst_width;
        $tgt_height = $dst_height;

        $dest_image = imagecreatetruecolor($dst_width, $dst_height);

        // If image is a PNG and the output is PNG, fill with transparency. Else fill with white background.
        if ($file_type == 'png' && $type == IMAGETYPE_PNG) {
            imagealphablending($dest_image, false);
            imagesavealpha($dest_image, true);
            $transparent = imagecolorallocatealpha($dest_image, 255, 255, 255, 127);
            imagefilledrectangle($dest_image, 0, 0, $dst_width, $dst_height, $transparent);
        } else {
            $white = imagecolorallocate($dest_image, 255, 255, 255);
            imagefilledrectangle($dest_image, 0, 0, $dst_width, $dst_height, $white);
        }

        $src_image = ImageManager::create($type, $src_file);
        if ($rotate) {
            $src_image = imagerotate($src_image, $rotate, 0);
        }

        if ($dst_width >= $src_width && $dst_height >= $src_height) {
            imagecopyresized($dest_image, $src_image, (int)(($dst_width - $next_width) / 2), (int)(($dst_height - $next_height) / 2), 0, 0, $next_width, $next_height, $src_width, $src_height);
        } else {
            ImageManager::imagecopyresampled($dest_image, $src_image, (int)(($dst_width - $next_width) / 2), (int)(($dst_height - $next_height) / 2), 0, 0, $next_width, $next_height, $src_width, $src_height, $quality);
        }
        $write_file = ImageManager::write($file_type, $dest_image, $dst_file);
        @imagedestroy($src_image);
        return $write_file;
    }
}
