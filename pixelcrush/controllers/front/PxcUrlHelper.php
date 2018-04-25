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

class PixelcrushPxcUrlHelperModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $lang       = Configuration::get('PS_LANG_DEFAULT');
        $type       = Tools::getValue('type');
        $cover      = Tools::getValue('cover');
        $id_object  = Tools::getValue('id_object');
        $image_type = Tools::getValue('image_type');
        $id_image   = Tools::getValue('id_image');
        $width      = Tools::getValue('width');
        $images     = [];

        if (!$type || !$id_object) {
            return;
        }

        parent::initContent();

        if ($type === 'product') {
            $langs_rewrites = Product::getUrlRewriteInformations($id_object);
            foreach ($langs_rewrites as $rewrite) {
                if ($rewrite['id_lang'] === $lang) {
                    $product_rewrite = $rewrite['link_rewrite'];
                }
            }

            if ($id_image) {
                if ($image_type) {
                    $images[] = $this->context->link->getImageLink($product_rewrite, $id_image, $image_type);
                } elseif ($width) {
                    $images[] = $this->getAbsoluteImagePath($id_image);
                }
            } else {
                $product = new Product($id_object);
                $product_images = $product->getImages($lang, $this->context);
                $images = [];

                if ($cover) {
                    foreach ($product_images as $key => $image) {
                        if (!$image['cover']) {
                            unset($product_images[$key]);
                        }
                    }
                }

                foreach ($product_images as $image) {
                    if ($image_type) {
                        $images[] = $this->context->link->getImageLink($product_rewrite, $image['id_image'], $image_type);
                    } else {
                        $images[] = $this->getAbsoluteImagePath($image['id_image']);
                    }
                }

            }

            die(json_encode(count($images) > 1 ? $images : $images[0], JSON_UNESCAPED_SLASHES));
        }
    }

    private function getAbsoluteImagePath($id_image)
    {
        return 'http' . (isset($_SERVER['HTTPS']) ? 's' : '').'://'.Tools::getShopDomain()
            .'/img/p/'.Image::getImgFolderStatic($id_image).$id_image.'.jpg';
    }
}
