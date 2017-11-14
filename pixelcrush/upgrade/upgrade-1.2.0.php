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

function upgrade_module_1_2_0($module)
{
    $module->uninstallOverrides();

    // Assets directory is not available in PS 1.7 for example
    if (version_compare(_PS_VERSION_, '1.7.0', '>=')) {
        $module->checkOverrideDirectory('assets');
    }

    $module->installOverrides();

    // unregister generic object hook types
    $module->unregisterHook('actionObjectAddAfter');
    $module->unregisterHook('actionObjectUpdateAfter');
    $module->unregisterHook('actionObjectDeleteAfter');
    $module->unregisterHook('hookActionObjectAddAfter');
    $module->unregisterHook('hookActionObjectUpdateAfter');
    $module->unregisterHook('hookActionObjectDeleteAfter');

    // Using now object-specific hook type
    $module->registerHook('actionObjectImageTypeAddAfter');
    $module->registerHook('actionObjectImageTypeUpdateAfter');
    $module->registerHook('actionObjectImageTypeDeleteAfter');
    $module->registerHook('displayBackOfficeHeader');

    return true;
}
