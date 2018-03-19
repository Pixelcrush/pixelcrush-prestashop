<?php
/**
 * Pixelcrush CDN Prestashop Module
 *
 * Copyright 2017 Imagenii, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author   Pixelcrush
 * @copyright Copyright (c) 2017 Imagenii Inc. All rights reserved
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2
 *
 */

class PixelcrushImageManagerModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        if (!in_array(Tools::getValue('action'), ['remove', 'regenerate'])) {
            return;
        }

        parent::initContent();

        if (Tools::getValue('token') === Tools::encrypt('pixelcrush-imagemanager'.Tools::getValue('id_employee'))) {
            Configuration::updateValue('PIXELCRUSH_ACTIVE_PROCESS', Tools::getValue('action'));
            if ($this->_regenerateThumbnails(Tools::getValue('type'), Tools::getValue('action') === 'remove')) {
                Configuration::updateValue('PIXELCRUSH_ACTIVE_PROCESS', null);
            }
        }
    }

    private function _regenerateThumbnails($type = 'all', $deleteOldImages = false)
    {
        // TODO: Make $type -- $types so we can filter by some, not only one or all
        $max_execution_time = 0;
        $this->start_time = time();
        ini_set('max_execution_time', $max_execution_time); // ini_set may be disabled, we need the real value
        $max_execution_time = (int)ini_get('max_execution_time');
        //$languages = Language::getLanguages(false);

        $process = array(
            array('type' => 'products', 'dir' => _PS_PROD_IMG_DIR_),
            array('type' => 'categories', 'dir' => _PS_CAT_IMG_DIR_),
            //array('type' => 'manufacturers', 'dir' => _PS_MANU_IMG_DIR_),
            //array('type' => 'suppliers', 'dir' => _PS_SUPP_IMG_DIR_),
            //array('type' => 'scenes', 'dir' => _PS_SCENE_IMG_DIR_),
            //array('type' => 'stores', 'dir' => _PS_STORE_IMG_DIR_)
        );

        // Launching generation process
        foreach ($process as $proc) {
            if ($type !== 'all' && $type !== $proc['type']) {
                continue;
            }

            // Getting format generation
            $formats = ImageType::getImagesTypes($proc['type']);
            if ($type !== 'all') {
                $format = (string)(Tools::getValue('format_'.$type));
                if ($format !== 'all') {
                    foreach ($formats as $k => $form) {
                        if ($form['id_image_type'] != $format) {
                            unset($formats[$k]);
                        }
                    }
                }
            }

            if ($deleteOldImages) {
                $this->_deleteOldImages($proc['dir'], $formats, ($proc['type'] == 'products' ? true : false));
            }
            if (($return = $this->_regenerateNewImages($proc['dir'], $formats, ($proc['type'] == 'products' ? true : false))) === true) {
                if (!count($this->errors)) {
                    $this->errors[] = sprintf(Tools::displayError('Cannot write images for this type: %s. Please check the %s folder\'s writing permissions.'), $proc['type'], $proc['dir']);
                }
            } elseif ($return == 'timeout') {
                $this->errors[] = Tools::displayError('Only part of the images have been regenerated. The server timed out before finishing.');
            } else {
                if ($proc['type'] == 'products') {
                    if ($this->_regenerateWatermark($proc['dir'], $formats) === 'timeout') {
                        $this->errors[] = Tools::displayError('Server timed out. The watermark may not have been applied to all images.');
                    }
                }
                /*
                if (!count($this->errors)) {
                    if ($this->_regenerateNoPictureImages($proc['dir'], $formats, $languages)) {
                        $this->errors[] = sprintf(Tools::displayError('Cannot write "No picture" image to (%s) images folder. Please check the folder\'s writing permissions.'), $proc['type']);
                    }
                }
                */
            }
        }

        return (count($this->errors) > 0 ? false : true);
    }

    private function _deleteOldImages($dir, $type, $product = false)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $toDel = scandir($dir, 1);

        foreach ($toDel as $d) {
            foreach ($type as $imageType) {
                if (preg_match('/^[0-9]+\-'.($product ? '[0-9]+\-' : '').$imageType['name'].'\.jpg$/', $d)
                    || (count($type) > 1 && preg_match('/^[0-9]+\-[_a-zA-Z0-9-]*\.jpg$/', $d))
                    || preg_match('/^([[:lower:]]{2})\-default\-'.$imageType['name'].'\.jpg$/', $d)) {
                    if (file_exists($dir.$d)) {
                        unlink($dir.$d);
                    }
                }
            }
        }

        // delete product images using new filesystem.
        if ($product) {
            $productsImages = Image::getAllImages();
            foreach ($productsImages as $image) {
                $imageObj = new Image($image['id_image']);
                $imageObj->id_product = $image['id_product'];
                if (file_exists($dir.$imageObj->getImgFolder())) {
                    $toDel = scandir($dir.$imageObj->getImgFolder(), 1);
                    foreach ($toDel as $d) {
                        foreach ($type as $imageType) {
                            if (preg_match('/^[0-9]+\-'.$imageType['name'].'\.jpg$/', $d) || (count($type) > 1 && preg_match('/^[0-9]+\-[_a-zA-Z0-9-]*\.jpg$/', $d))) {
                                if (file_exists($dir.$imageObj->getImgFolder().$d)) {
                                    unlink($dir.$imageObj->getImgFolder().$d);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /* Hook watermark optimization */
    private function _regenerateWatermark($dir, $type = null)
    {
        $result = Db::getInstance()->executeS('
        SELECT m.`name` FROM `'._DB_PREFIX_.'module` m
        LEFT JOIN `'._DB_PREFIX_.'hook_module` hm ON hm.`id_module` = m.`id_module`
        LEFT JOIN `'._DB_PREFIX_.'hook` h ON hm.`id_hook` = h.`id_hook`
        WHERE h.`name` = \'actionWatermark\' AND m.`active` = 1');

        if ($result && count($result)) {
            $productsImages = Image::getAllImages();
            foreach ($productsImages as $image) {
                $imageObj = new Image($image['id_image']);
                if (file_exists($dir.$imageObj->getExistingImgPath().'.jpg')) {
                    foreach ($result as $module) {
                        $moduleInstance = Module::getInstanceByName($module['name']);
                        if ($moduleInstance && is_callable(array($moduleInstance, 'hookActionWatermark'))) {
                            call_user_func(array($moduleInstance, 'hookActionWatermark'), array('id_image' => $imageObj->id, 'id_product' => $imageObj->id_product, 'image_type' => $type));
                        }

                        //if (time() - $this->start_time > $this->max_execution_time - 4) { // stop 4 seconds before the tiemout, just enough time to process the end of the page on a slow server
                        //    return 'timeout';
                        //}
                    }
                }
            }
        }
    }

    private function _regenerateNoPictureImages($dir, $type, $languages)
    {
        $errors = false;
        $generate_hight_dpi_images = (bool)Configuration::get('PS_HIGHT_DPI');

        foreach ($type as $image_type) {
            foreach ($languages as $language) {
                $file = $dir.$language['iso_code'].'.jpg';
                if (!file_exists($file)) {
                    $file = _PS_PROD_IMG_DIR_.Language::getIsoById((int)Configuration::get('PS_LANG_DEFAULT')).'.jpg';
                }
                if (!file_exists($dir.$language['iso_code'].'-default-'.stripslashes($image_type['name']).'.jpg')) {
                    if (!$this->_resize($file, $dir.$language['iso_code'].'-default-'.stripslashes($image_type['name']).'.jpg', (int)$image_type['width'], (int)$image_type['height'])) {
                        $errors = true;
                    }

                    if ($generate_hight_dpi_images) {
                        if (!$this->_resize($file, $dir.$language['iso_code'].'-default-'.stripslashes($image_type['name']).'2x.jpg', (int)$image_type['width']*2, (int)$image_type['height']*2)) {
                            $errors = true;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private function _regenerateNewImages($dir, $type, $productsImages = false)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $generate_hight_dpi_images = (bool)Configuration::get('PS_HIGHT_DPI');

        if (!$productsImages) {
            $formated_thumb_scene = ImageType::getFormatedName('thumb_scene');
            $formated_medium = ImageType::getFormatedName('medium');
            foreach (scandir($dir, 1) as $image) {
                if (preg_match('/^[0-9]*\.jpg$/', $image)) {
                    foreach ($type as $k => $imageType) {
                        // Customizable writing dir
                        $newDir = $dir;
                        if ($imageType['name'] == $formated_thumb_scene) {
                            $newDir .= 'thumbs/';
                        }
                        if (!file_exists($newDir)) {
                            continue;
                        }

                        if (($dir == _PS_CAT_IMG_DIR_) && ($imageType['name'] == $formated_medium) && is_file(_PS_CAT_IMG_DIR_.str_replace('.', '_thumb.', $image))) {
                            $image = str_replace('.', '_thumb.', $image);
                        }

                        if (!file_exists($newDir.substr($image, 0, -4).'-'.stripslashes($imageType['name']).'.jpg')) {
                            if (!file_exists($dir.$image) || !filesize($dir.$image)) {
                                $this->errors[] = sprintf(Tools::displayError('Source file does not exist or is empty (%s)'), $dir.$image);
                            } elseif (!$this->_resize($dir.$image, $newDir.substr(str_replace('_thumb.', '.', $image), 0, -4).'-'.stripslashes($imageType['name']).'.jpg', (int)$imageType['width'], (int)$imageType['height'])) {
                                    $this->errors[] = sprintf(Tools::displayError('Failed to resize image file (%s)'), $dir.$image);
                            }

                            if ($generate_hight_dpi_images) {
                                if (!$this->_resize($dir.$image, $newDir.substr($image, 0, -4).'-'.stripslashes($imageType['name']).'2x.jpg', (int)$imageType['width']*2, (int)$imageType['height']*2)) {
                                    $this->errors[] = sprintf(Tools::displayError('Failed to resize image file to high resolution (%s)'), $dir.$image);
                                }
                            }
                        }
                        // stop 4 seconds before the timeout, just enough time to process the end of the page on a slow server
                        //if (time() - $this->start_time > $this->max_execution_time - 4) {
                        //    return 'timeout';
                        //}
                    }
                }
            }
        } else {
            foreach (Image::getAllImages() as $image) {
                $imageObj = new Image($image['id_image']);
                $existing_img = $dir.$imageObj->getExistingImgPath().'.jpg';
                if (file_exists($existing_img) && filesize($existing_img)) {
                    foreach ($type as $imageType) {
                        if (!$this->_resize($existing_img, $dir.$imageObj->getExistingImgPath().'-'.stripslashes($imageType['name']).'.jpg', (int)$imageType['width'], (int)$imageType['height'])) {
                            $this->errors[] = sprintf(Tools::displayError('Original image is corrupt (%s) for product ID %2$d or bad permission on folder'), $existing_img, (int)$imageObj->id_product);
                        }

                        if ($generate_hight_dpi_images) {
                            if (!$this->_resize($existing_img, $dir.$imageObj->getExistingImgPath().'-'.stripslashes($imageType['name']).'2x.jpg', (int)$imageType['width']*2, (int)$imageType['height']*2)) {
                                $this->errors[] = sprintf(Tools::displayError('Original image is corrupt (%s) for product ID %2$d or bad permission on folder'), $existing_img, (int)$imageObj->id_product);
                            }
                        }
                    }
                } else {
                    $this->errors[] = sprintf(Tools::displayError('Original image is missing or empty (%1$s) for product ID %2$d'), $existing_img, (int)$imageObj->id_product);
                }
                //if (time() - $this->start_time > $this->max_execution_time - 4) { // stop 4 seconds before the tiemout, just enough time to process the end of the page on a slow server
                //    return 'timeout';
                //}
            }
        }

        return (bool)count($this->errors);
    }

    private function _resize($src_file, $dst_file, $dst_width = null, $dst_height = null, $file_type = 'jpg',
                                $force_type = false, &$error = 0, &$tgt_width = null, &$tgt_height = null, $quality = 5,
                                &$src_width = null, &$src_height = null)
    {
        if (PHP_VERSION_ID < 50300) {
            clearstatcache();
        } else {
            clearstatcache(true, $src_file);
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
            || ((Configuration::get('PS_IMAGE_QUALITY') === 'png' && $type === IMAGETYPE_PNG) && !$force_type)) {
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
