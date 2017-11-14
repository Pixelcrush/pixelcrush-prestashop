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

class JavascriptManager extends JavascriptManagerCore
{
    protected function add($id, $fullPath, $position, $priority, $inline, $attribute, $server)
    {
        $priority = is_int($priority) ? $priority : self::DEFAULT_PRIORITY;
        $position = $this->getSanitizedPosition($position);
        $attribute = $this->getSanitizedAttribute($attribute);
        
        if ('remote' === $server) {
            $uri  = $fullPath;
            $type = 'external';
        } else {
            $uri  = $this->getFQDN().parent::getUriFromPath($fullPath);
            $type = $inline ? 'inline' : 'external';
        }
        
        // Build Pixelcrush Proxied URL
        if (Module::IsEnabled('pixelcrush')) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_statics) {
                $uri = $pixelcrush->cdnProxy(_PS_ROOT_DIR_.$fullPath, $uri, true);
            }
        }
        
        $this->list[$position][$type][$id] = array(
            'id'        => $id,
            'type'      => $type,
            'path'      => $fullPath,
            'uri'       => $uri,
            'priority'  => $priority,
            'attribute' => $attribute,
            'server'    => $server,
        );
    }
    
    protected function getSanitizedPosition($position)
    {
        // Overridden visibility
        return in_array($position, $this->valid_position, true) ? $position : self::DEFAULT_JS_POSITION;
    }

    protected function getSanitizedAttribute($attribute)
    {
        // Overridden visibility
        return in_array($attribute, $this->valid_attribute, true) ? $attribute : '';
    }
}
