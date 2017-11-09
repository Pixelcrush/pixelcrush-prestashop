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

require_once(dirname(__FILE__).'/classes/ApiClient.php');

class Pixelcrush extends Module
{
    public $config;
    public $domains;
    
    public $client;
    public $user_cloud;
    public $cloud_filters_hash;
    public $images_types_hash;
    
    public function __construct()
    {
        $this->name         = 'pixelcrush';
        $this->tab          = 'administration';
        $this->version      = '1.1.0';
        $this->author       = 'pixelcrush.io';
        $this->bootstrap    = true;
        
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '1.7.9.9');
        $this->need_instance = 1;
        $this->module_key = 'f06ff8e65629b4d85e63752cfbf1d457';
        
        $this->displayName  = $this->l('Pixelcrush CDN');
        $this->description  = $this->l('Make your shop extremely faster and forget managing images.');
        
        parent::__construct();
    }
    
    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('hookActionObjectAddAfter') ||
            !$this->registerHook('hookActionObjectUpdateAfter') ||
            !$this->registerHook('hookActionObjectDeleteAfter')
        ) {
            return false;
        }
        
        return true;
    }
    
    public function uninstall()
    {
        return parent::uninstall();
    }
    
    public function getContent()
    {
        // Ensure design and ux is the same on all prestashop versions although changes on HelperForm integrations
        
        // Specific 1.5 CSS + placing pixelcrush logo on top of configuration form
        if (version_compare(_PS_VERSION_, '1.6.0', '<')) {
            $output = "<style>
                            .col-sm-30 {
                                min-width: 256px;
                            }
                            .disabled {
                                pointer-events: none;
                                tab-index: -1;
                            }
                            #pixelcrush-logo-hd {
                                margin-bottom: 12px;
                            }
                            label[for=active_on], label[for=active_off] {
                                width: 24px;
                            }
                            #active_on, #active_off {
                                float: left;
                                margin-top: 4px;
                                margin-bottom: 12px;
                            }
                            .margin-form {
                                margin-bottom: 8px;
                            }
                        </style>
                        
                        <script>
                            $(document).ready(function() {
                                if ($('#pixelcrush-logo-hd').length == 0)
                                    $('FORM#configuration_form FIELDSET').prepend('<img id=\'pixelcrush-logo-hd\' style=\'margin-left:256px\' src=\'../modules/pixelcrush/views/img/pixelcrush-logo.png\' />');
                            });
                        </script>";
        } else {
            // Specific 1.6+ CSS
            $output = ' <style>
                            @media only screen and (min-width: 768px) {
                                .col-sm-30 {
                                    width:30%!important;
                                }
                            }
                            .disabled {
                                pointer-events: none;
                                tab-index: -1;
                            }
                            #pixelcrush-logo-hd {
                                margin-bottom: 4px;
                            }
                        </style>';
        }
        
        if (Tools::isSubmit('submit'.$this->name) && Tools::getIsset('PIXELCRUSH_ENABLE_IMAGES') && Tools::getIsset('PIXELCRUSH_ENABLE_STATICS')) {
            if (Tools::getValue('PIXELCRUSH_USER_ACCOUNT') && Tools::getValue('PIXELCRUSH_API_SECRET') &&
                Tools::getValue('PIXELCRUSH_FILTERS_PREFIX')
            ) {
                // We need to re-initalize stored config and errors in case the user has changed its user-apiKey to reAuth
                $this->client = null;
                $this->config = null;
                $this->_errors = array();
                
                Configuration::updateValue('PIXELCRUSH_ENABLE_IMAGES', Tools::getValue('PIXELCRUSH_ENABLE_IMAGES'));
                Configuration::updateValue('PIXELCRUSH_ENABLE_STATICS', Tools::getValue('PIXELCRUSH_ENABLE_STATICS'));
                Configuration::updateValue('PIXELCRUSH_USER_ACCOUNT', Tools::getValue('PIXELCRUSH_USER_ACCOUNT'));
                Configuration::updateValue('PIXELCRUSH_API_SECRET', Tools::getValue('PIXELCRUSH_API_SECRET'));
                Configuration::updateValue('PIXELCRUSH_FILTERS_PREFIX', Tools::getValue('PIXELCRUSH_FILTERS_PREFIX'));
                Configuration::updateValue('PIXELCRUSH_FILL_BACKGROUND', (Tools::getValue('PIXELCRUSH_FILL_BACKGROUND') ?: '#FFFFFF'));
                Configuration::updateValue('PIXELCRUSH_URL_PROTOCOL', Tools::getValue('PIXELCRUSH_URL_PROTOCOL'));
                
                // Reset Existing Filters
                if ($this->resetCdnFilters((bool)Tools::getValue('reset_filters_checked'))) {
                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                } else {
                    // Functionality cant be activated without user validation. Revert back this settings
                    if (strpos($this->_errors[0], 'ApiClient') !== false) {
                        Configuration::updateValue('PIXELCRUSH_ENABLE_IMAGES', false);
                        Configuration::updateValue('PIXELCRUSH_ENABLE_STATICS', false);
                    }
                    
                    $output .= $this->displayError($this->_errors);
                }
            } else {
                $output .= $this->displayError('You need to fill all mandatory fields.');
            }
        }
        
        return $output.$this->displayForm();
    }
    
    public function displayForm()
    {
        $fields_form = array();
        
        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Pixelcrush Settings'),
            ),
            'input' => array(
                array(
                    'type' => (version_compare(_PS_VERSION_, '1.6.0', '<') ? 'radio' : 'switch'),
                    'label' => $this->l('Enable Image CDN'),
                    'name' => 'PIXELCRUSH_ENABLE_IMAGES',
                    'required' => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'lang' => false,
                ),
                array(
                    'type' => (version_compare(_PS_VERSION_, '1.6.0', '<') ? 'radio' : 'switch'),
                    'label' => $this->l('Enable Static CDN (.js / .css files)'),
                    'name' => 'PIXELCRUSH_ENABLE_STATICS',
                    'required' => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'lang' => false,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('User Account'),
                    'name' => 'PIXELCRUSH_USER_ACCOUNT',
                    'class' => 'col-sm-30',
                    'size' => 24,
                    'required' => true,
                    'lang' => false,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Api Secret'),
                    'name' => 'PIXELCRUSH_API_SECRET',
                    'size' => 36,
                    'class' => 'col-sm-30',
                    'required' => true,
                    'lang' => false,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Filter Alias'),
                    'name' => 'PIXELCRUSH_FILTERS_PREFIX',
                    'class' => 'col-sm-30',
                    'size' => 12,
                    'required' => true,
                    'lang' => false,
                ),
                array(
                    'type' => 'checkbox',
                    'label'   => $this->l('Reset Existing Filters'),
                    'desc'    => $this->l('If this option is checked, any existing filter on your pixelcrush account
                                            will be deleted before uploading the actual ones.'),
                    'name' => 'reset_filters',
                    'values' => array(
                        'query' => array(
                            array(
                                'id' => 'checked',
                                'name' => '',
                                'val' => '1'
                            ),
                        ),
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Add protocol to proxied url:'),
                    'desc' => $this->l('Adds protocol to original resource url. If this is not set pixelcrush will
                                        access the resources using always http.'),
                    'name' => 'PIXELCRUSH_URL_PROTOCOL',
                    'required' => true,
                    'options' => array(
                        'id' => 'id_option',
                        'name' => 'name',
                        'query' => array(
                            array(
                              'id_option' => '',
                              'name' => 'Without Protocol'
                            ),
                            array(
                              'id_option' => 'http://',
                              'name' => 'http://'
                            ),
                            array(
                              'id_option' => 'https://',
                              'name' => 'https://'
                            ),
                        )
                    )
                ),
                array(
                    'type' => 'color',
                    'label' => $this->l('Fill Background'),
                    'name' => 'PIXELCRUSH_FILL_BACKGROUND',
                    'lang' => false,
                    'size' => 15,
                    'desc' => $this->l('Use color picker for color.'),
                    'required' => false
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );
        
        // Adds logo on top of the configuration form for PS 1.6+
        if (version_compare(_PS_VERSION_, '1.6.0', '>=')) {
            $img = array(
                'type' => 'html',
                'name' => 'PIXELCRUSH_logo',
                'html_content' => '<img id="pixelcrush-logo-hd" src="../modules/pixelcrush/views/img/pixelcrush-logo.png" />'
            );
            array_unshift($fields_form[0]['form']['input'], $img);
        }
        
        $helper = new HelperForm();
        
        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
         
        // Language
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = $this->context->language->id;
         
        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );
        
        // Load current values
        $helper->fields_value['PIXELCRUSH_ENABLE_IMAGES'] = Configuration::get('PIXELCRUSH_ENABLE_IMAGES');
        $helper->fields_value['PIXELCRUSH_ENABLE_STATICS'] = Configuration::get('PIXELCRUSH_ENABLE_STATICS');
        $helper->fields_value['PIXELCRUSH_USER_ACCOUNT'] = Configuration::get('PIXELCRUSH_USER_ACCOUNT');
        $helper->fields_value['PIXELCRUSH_API_SECRET'] = Configuration::get('PIXELCRUSH_API_SECRET');
        $helper->fields_value['PIXELCRUSH_FILTERS_PREFIX'] = Configuration::get('PIXELCRUSH_FILTERS_PREFIX');
        $helper->fields_value['PIXELCRUSH_FILL_BACKGROUND'] = Configuration::get('PIXELCRUSH_FILL_BACKGROUND');
        $helper->fields_value['PIXELCRUSH_URL_PROTOCOL'] = Configuration::get('PIXELCRUSH_URL_PROTOCOL');
        
        return $helper->generateForm($fields_form);
    }
    
    public function loadImagesTypeHashes()
    {
        if (!isset($this->images_types_hash) || empty($this->images_types_hash)
            || !is_array($this->images_types_hash)
        ) {
            $this->images_types_hash = array();
            
            foreach (array('products', 'categories', 'manufacturers', 'suppliers') as $type) {
                $type_hash = array();
                
                foreach (ImageType::getImagesTypes($type) as $image_type) {
                    $type_hash[ $image_type['name'] ] = $image_type;
                }
                    
                $this->images_types_hash[ $type ] = $type_hash;
            }
        }
    }
    
    public function loadCloudFiltersHash($cloud_cdn_filters)
    {
        foreach ($cloud_cdn_filters as $filter) {
            $this->cloud_filters_hash[ $filter->name ] = $filter;
        }
    }
    
    public function rezFilter($width, $height)
    {
        $bg = str_replace('#', '', Configuration::get('PIXELCRUSH_FILL_BACKGROUND'));
        $filter = sprintf('rz(o=f,w=' . $width . ',h=' . $height . ',b=%s)', (string)$bg);
        return $filter;
    }
    
    public function psFilterMap($entity, $type)
    {
        $filter_prefix = Configuration::get('PIXELCRUSH_FILTERS_PREFIX');
        
        if (!empty($filter_prefix)) {
            return $filter_prefix . '_' . Tools::substr($entity, 0, 2) . '_' . $type;
        } else {
            return Tools::substr($entity, 0, 2) . '_' . $type;
        }
    }
    
    public function imageTypesAsFilters()
    {
        $this->loadImagesTypeHashes();
        
        $filters = array();
        
        foreach (array('products', 'categories', 'manufacturers', 'suppliers') as $entity) {
            foreach ($this->images_types_hash[$entity] as $image_type) {
                $filter = (object)array
                (
                    'name'      => $this->psFilterMap($entity, $image_type['name']),
                    'filter'    => $this->rezFilter($image_type['width'], $image_type['height']),
                    'nf'        => null,
                    'ttl'       => null,
                );
                
                array_push($filters, $filter);
            }
        }
        
        return $filters;
    }
    
    public function pixelcrushProxy($url, $entity, $type)
    {
        $params         = array();
        $filter         = null;
        
        if ($this->apiIsCallable(false)) {
            if (!is_object($this->user_cloud)) {
                try {
                    $this->user_cloud = $this->client->userCloud();
                    Configuration::updateValue('PIXELCRUSH_USER_CLOUD', serialize($this->user_cloud));
                } catch (Exception $e) {
                }
            } elseif (empty($this->user_cloud)) {
                $this->user_cloud = unserialize(Configuration::get('PIXELCRUSH_USER_CLOUD'));
            }
            
            if (!is_array($this->cloud_filters_hash) && is_object($this->user_cloud)) {
                $this->loadCloudFiltersHash($this->user_cloud->cdn->filters);
            }
            
            // Using cloud filters if available
            if (isset($this->cloud_filters_hash[$this->psFilterMap($entity, $type)])) {
                $filter = $this->cloud_filters_hash[$this->psFilterMap($entity, $type)];
            }
        }
        
        // Ensure we have backup local image types just in case all/any cloud filter fails (saved statically)
        $this->loadImagesTypeHashes();
        
        // Using hashed local image types if cloud is not available or has invalid value
        if (empty($filter)) {
            if (!empty($entity) && !empty($type)) {
                $image_type = $this->images_types_hash[$entity][$type];
                $params['f'] = $this->rezFilter($image_type['width'], $image_type['height']);
            }
        }
        
        // Build the url with the available data (cloud filter name or local resizing values)
        return $this->client->imgProxiedUrl($url, $params, $filter, $this->domains, $this->config->url_protocol);
    }
    
    public function cdnProxy($local_uri, $remote_uri, $newAssetManager = false)
    {
        $cdn_uri = null;
        if ($this->apiIsCallable(false)) {
            if (@filemtime($local_uri) && @filesize($local_uri)) {
                $pixelcrush_proxy = $this->client->domain().'/cdn/';
                $pixelcrush_ts = '?ttl='.filemtime($local_uri);
                
                if ($newAssetManager) {
                    // 1.7: AbstractAssetManager
                    $url = preg_replace("(^https?://)", "", ltrim(__PS_BASE_URI__.$remote_uri, "/"));
                    $cdn_uri = $pixelcrush_proxy . Configuration::get('PIXELCRUSH_URL_PROTOCOL').$url . $pixelcrush_ts;
                } else {
                    // Legacy 1.7 / 1.6 / 1.5 Media
                    $cdn_uri = $pixelcrush_proxy . Tools::getHttpHost((bool)Configuration::get('PIXELCRUSH_URL_PROTOCOL'))
                            . __PS_BASE_URI__ . ltrim($remote_uri, "/") . $pixelcrush_ts;
                }
            }
        }
        
        return ($cdn_uri ?: $remote_uri);
    }
    
    public function isConfigured()
    {
        if (!empty($this->config->id) && !empty($this->config->api_secret_key)) {
            return true;
        } else {
            $this->config = (object)array(
                'enable_images' => Configuration::get('PIXELCRUSH_ENABLE_IMAGES', false),
                'enable_statics' => Configuration::get('PIXELCRUSH_ENABLE_STATICS', false),
                'id' => Configuration::get('PIXELCRUSH_USER_ACCOUNT'),
                'api_secret_key' => Configuration::get('PIXELCRUSH_API_SECRET'),
                'rz_bg' => Configuration::get('PIXELCRUSH_FILL_BACKGROUND'),
                'filters_prefix' => Configuration::get('PIXELCRUSH_FILTERS_PREFIX'),
                'api_cloud_ttl' => Configuration::get('PIXELCRUSH_API_CLOUD_TTL'),
                'url_protocol' => Configuration::get('PIXELCRUSH_URL_PROTOCOL'),
            );
        }
        
        return !empty($this->config->id) && !empty($this->config->api_secret_key);
    }
    
    public function hookActionObjectAddAfter(array $params)
    {
        if (isset($params['object'])) {
            if ($params['object'] instanceof ImageType) {
                $this->resetCdnFilters();
            }
        }
    }
    
    public function hookActionObjectDeleteAfter(array $params)
    {
        if (isset($params['object'])) {
            if ($params['object'] instanceof ImageType) {
                $this->resetCdnFilters();
            }
        }
    }
    
    public function hookActionObjectUpdateAfter(array $params)
    {
        if (isset($params['object'])) {
            if ($params['object'] instanceof ImageType) {
                $this->resetCdnFilters();
            }
        }
    }
    
    public function resetCdnFilters($reset_existing = true)
    {
        if ($this->apiIsCallable()) {
            if ($reset_existing) {
                try {
                    $this->user_cloud = $this->client->userCloud();
                } catch (Exception $e) {
                    return $e->getMessage();
                }
                
                foreach ($this->user_cloud->cdn->filters as $filter) {
                    if (!empty($filter->name)) {
                        try {
                            $this->client->userCdnFilterDelete($filter->name);
                        } catch (Exception $e) {
                            return $e->getMessage();
                        }
                    }
                }
            }
            
            if (is_object($this->client)) {
                foreach ($this->imageTypesAsFilters() as $filter) {
                    try {
                        $this->client->userCdnFilterUpsert($filter);
                    } catch (Exception $e) {
                        return $e->getMessage();
                    }
                }
            }
            
            Configuration::updateValue('PIXELCRUSH_USER_CLOUD', '');
            Configuration::updateValue('PIXELCRUSH_API_CLOUD_TTL', '');
            return true;
        } else {
            return false;
        }
    }
    
    public function apiIsCallable($auth = true)
    {
        if ($this->isConfigured()) {
            try {
                $this->setClient($auth);
            } catch (Exception $e) {
                $new_ttl = date('Y-m-d H:i:s', strtotime('+5 seconds'));
                \Configuration::updateValue('PIXELCRUSH_API_CLOUD_TTL', $new_ttl);
                $this->config->api_cloud_ttl = $new_ttl;
                $this->_errors[] = $e->getMessage();
                return false;
            }
        } else {
            return false;
        }
        
        if (!$auth) {
            return true;
        } else {
            return $this->client->auth_valid;
        }
    }
    
    public function setClient($auth = true)
    {
        if (!is_object($this->client)) {
            $this->client = new \pixelcrush\ApiClient($this->config->id, $this->config->api_secret_key);
        }
        
        if (!$auth) {
            return true;
        } else {
            return $this->client->userAuth();
        }
    }
}
