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
 
class Media extends MediaCore
{
    public static function getJSPath($js_uri)
    {
        if (is_array($js_uri) || $js_uri === null || empty($js_uri)) {
            return false;
        }
            
        $url_data = parse_url($js_uri);
        
        if (!array_key_exists('host', $url_data)) {
            $file_uri = _PS_ROOT_DIR_.Tools::str_replace_once(__PS_BASE_URI__, DIRECTORY_SEPARATOR, $url_data['path']);
            // remove PS_BASE_URI on _PS_ROOT_DIR_ for the following
        } else {
            $file_uri = $js_uri;
        }
        
        // check if js files exists
        if (!preg_match('/^http(s?):\/\//i', $file_uri) && !@filemtime($file_uri)) {
            return false;
        }

        if (Context::getContext()->controller->controller_type == 'admin' && !array_key_exists('host', $url_data)) {
            $js_uri = preg_replace('/^'.preg_quote(__PS_BASE_URI__, '/').'/', '/', $js_uri);
            $js_uri = dirname(preg_replace('/\?.+$/', '', $_SERVER['REQUEST_URI']).'a').'/..'.$js_uri;
        }
        
        // Pixelcrush: We will define a timestamp based on modification date of the file to be cached on pixelcrush
        if (Module::IsEnabled('pixelcrush') && isset($file_uri)) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_statics) {
                $js_uri = $pixelcrush->cdnProxy($file_uri, $js_uri);
            }
        }
        
        return $js_uri;
    }
    
    public static function getCSSPath($css_uri, $css_media_type = 'all')
    {
        if (empty($css_uri)) {
            return false;
        }
        
        // remove PS_BASE_URI on _PS_ROOT_DIR_ for the following
        
        $url_data = parse_url($css_uri);
        $file_uri = _PS_ROOT_DIR_.Tools::str_replace_once(__PS_BASE_URI__, DIRECTORY_SEPARATOR, $url_data['path']);
        
        // check if css files exists
        if (!@filemtime($file_uri) && !array_key_exists('host', $url_data)) {
            return false;
        }

        if (Context::getContext()->controller->controller_type == 'admin') {
            $css_uri = preg_replace('/^'.preg_quote(__PS_BASE_URI__, '/').'/', '/', $css_uri);
            $css_uri = dirname(preg_replace('/\?.+$/', '', $_SERVER['REQUEST_URI']).'a').'/..'.$css_uri;
        }
        
        // Pixelcrush: We will define a timestamp based on modification date of the file to be cached on pixelcrush
        if (Module::IsEnabled('pixelcrush') && isset($file_uri)) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_statics) {
                $css_uri = $pixelcrush->cdnProxy($file_uri, $css_uri);
            }
        }
        
        // Fixes url malformating on css's @import(file)b
        if (strpos($css_uri, '/..http') !== false) {
            $css_uri = Tools::substr($css_uri, strpos($css_uri, '/..http')+3);
        }

        return array($css_uri => $css_media_type);
    }
}
