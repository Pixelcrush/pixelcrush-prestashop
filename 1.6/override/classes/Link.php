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
 
class Link extends LinkCore
{
    public function getImageLink($name, $ids, $type = null)
    {
        if (Module::IsInstalled('pixelcrush') && Module::isEnabled('pixelcrush')) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_images) {
                return $pixelcrush->pixelcrushProxy(parent::getImageLink($name, $ids), 'products', $type);
            }
        }
        
        return parent::getImageLink($name, $ids, $type);
    }
    
    public function getSupplierImageLink($id_supplier, $type = null)
    {
        if (Module::IsInstalled('pixelcrush') && Module::isEnabled('pixelcrush')) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_images) {
                return $pixelcrush->pixelcrushProxy(parent::getSupplierImageLink($id_supplier, $type), 'suppliers', null);
            }
        }
        
        return parent::getSupplierImageLink($id_supplier, $type);
    }
    
    public function getCatImageLink($name, $id_category, $type = null)
    {
        if (Module::IsInstalled('pixelcrush') && Module::isEnabled('pixelcrush')) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_images) {
                return $pixelcrush->pixelcrushProxy(parent::getCatImageLink($name, $id_category), 'categories', $type);
            }
        }
        
        return parent::getCatImageLink($name, $id_category, $type);
    }
    
    public function getManufacturerImageLink($id_manufacturer, $type = null)
    {
        if (Module::IsInstalled('pixelcrush') && Module::isEnabled('pixelcrush')) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_images) {
                return $pixelcrush->pixelcrushProxy(parent::getManufacturerImageLink($id_manufacturer, $type), 'manufacturers', $type);
            }
        }
            
        return parent::getManufacturerImageLink($id_manufacturer, $type);
    }
}
