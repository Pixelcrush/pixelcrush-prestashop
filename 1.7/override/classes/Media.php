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
    private static $first_bo_css_loaded = 0;

    public static function getMediaPath($media_uri, $css_media_type = null)
    {
        if (is_array($media_uri) || $media_uri === null || empty($media_uri)) {
            return false;
        }
        
        $url_data = parse_url($media_uri);
        if (!is_array($url_data)) {
            return false;
        }
        
        if (!array_key_exists('host', $url_data)) {
            $media_uri_host_mode = '/'.ltrim(str_replace(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, _PS_CORE_DIR_), __PS_BASE_URI__, $media_uri), '/\\');
            $media_uri = '/'.ltrim(str_replace(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, _PS_ROOT_DIR_), __PS_BASE_URI__, $media_uri), '/\\');
            $file_uri = _PS_ROOT_DIR_.Tools::str_replace_once(__PS_BASE_URI__, DIRECTORY_SEPARATOR, $media_uri);
            $file_uri_host_mode = _PS_CORE_DIR_.Tools::str_replace_once(__PS_BASE_URI__, DIRECTORY_SEPARATOR, Tools::str_replace_once(_PS_CORE_DIR_, '', $media_uri));

            if (!@filemtime($file_uri) || @filesize($file_uri) === 0) {
                if (!defined('_PS_HOST_MODE_')) {
                    return false;
                } elseif (!@filemtime($file_uri_host_mode) || @filesize($file_uri_host_mode) === 0) {
                    return false;
                } else {
                    $media_uri = $media_uri_host_mode;
                }
            }
                
            $media_uri = str_replace('//', '/', $media_uri);
        }

        if (Module::IsEnabled('pixelcrush') && isset($file_uri)
        ) {
            // We need to bypass module.js document.styleSheets[0].cssRules.lenght checking
            if (isset($_SERVER['PATH_INFO']) &&
                $_SERVER['PATH_INFO'] === '/module/catalog' &&
                self::$first_bo_css_loaded === 0 &&
                Context::getContext()->controller->controller_name === 'AdminModules' &&
                pathinfo($media_uri, PATHINFO_EXTENSION) === 'css'
            ) {
                self::$first_bo_css_loaded = 1;
            } else {
                /* @var \Pixelcrush */
                $pixelcrush = Module::getInstanceByName('pixelcrush');
                if ($pixelcrush->isConfigured() && $pixelcrush->config->enable_statics) {
                    $media_uri = $pixelcrush->cdnProxy($file_uri, $media_uri);
                }
            }
        }
        
        if ($css_media_type) {
            return array($media_uri => $css_media_type);
        }
        
        return $media_uri;
    }
    
    public static function getJSPath($js_uri)
    {
        return self::getMediaPath($js_uri);
    }
    
    public static function getCSSPath($css_uri, $css_media_type = 'all', $need_rtl = true)
    {
        // RTL Ready: search and load rtl css file if it's not originally rtl
        if ($need_rtl && Context::getContext()->language->is_rtl) {
            $css_uri_rtl = preg_replace('/(^[^.].*)(\.css)$/', '$1_rtl.css', $css_uri);
            $rtl_media = self::getMediaPath($css_uri_rtl, $css_media_type);
            if ($rtl_media !== false) {
                return $rtl_media;
            }
        }
        
        return self::getMediaPath($css_uri, $css_media_type);
    }
}
