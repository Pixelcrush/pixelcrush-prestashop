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
        // TODO: WORK IN PROGRESS - Split functionality where possible
        $lang = Configuration::get('PS_LANG_DEFAULT');
        $type = Tools::getValue('type');
        $cover = Tools::getValue('cover');
        $id_object = Tools::getValue('id_object');
        $image_type = Tools::getValue('image_type');

        if (!$type || !$id_object) {
            return;
        }

        parent::initContent();

        $images_types = ImageType::getImagesTypes($type, true);

        if ($type === 'product') {
            if ($cover) {
                $cover = Product::getCover($id_object)['id_image'];
            } else {
                $product = new Product($id_object);
                $images = $product->getImages($lang, $this->context);
            }

            $langs_rewrites = Product::getUrlRewriteInformations($id_object);
            foreach ($langs_rewrites as $rewrite) {
                if ($rewrite['id_lang'] === $lang) {
                    $product_rewrite = $rewrite['link_rewrite'];
                }
            }

            foreach ($images as $image_id) {
                // if only absolute image url
                $images[] = Image::getImgFolderStatic($image_id);

                // if image url with image_type
                foreach ($images_types as $type) {
                    $images[] = $this->context->link->getImageLink($product_rewrite, $image_id, $type);
                }
            }
        }
    }
}
