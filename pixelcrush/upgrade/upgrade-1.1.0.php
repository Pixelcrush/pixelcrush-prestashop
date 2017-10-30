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

if (!defined('_PS_VERSION_')) {
    exit;
}
    
function upgrade_module_1_1_0($module)
{
    $module->uninstallOverrides();
        
    // Fix prestashop bug when overrided class subdirectory does not exists does not creates it automatically, so we do
    if (version_compare(_PS_VERSION_, '1.7.0', '>=')) {
        $assets_override_dir = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'override'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'assets';
        if (!is_dir($assets_override_dir)) {
            $oldumask = umask(0000);
            @mkdir($assets_override_dir, 0777);
            umask($oldumask);
            if (is_writable($assets_override_dir)) {
                copy(dirname(__FILE__).'/index.php', $assets_override_dir.'/index.php');
            }
        }
    }
    
    $module->installOverrides();
    
    return true;
}
