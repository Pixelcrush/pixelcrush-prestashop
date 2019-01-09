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

require_once(dirname(__FILE__) . '/classes/PSCache.php');
require_once(dirname(__FILE__) . '/classes/ApiClient.php');

class Pixelcrush extends Module
{
    public $config;

    /* @var \pixelcrush\ApiClient*/
    public $client;

    /* @var \pixelcrush\PSCache*/
    public $cache;

    public $user_cloud;
    public $cloud_filters_hash;
    public $images_types_hash;
    public $bootstrap;

    public function __construct()
    {
        $this->name           = 'pixelcrush';
        $this->tab            = 'administration';
        $this->version        = '1.3.1';
        $this->author         = 'pixelcrush.io';
        $this->bootstrap      = true;
        $this->need_instance  = 1;
        $this->module_key     = 'f06ff8e65629b4d85e63752cfbf1d457';
        $this->displayName    = $this->l('Pixelcrush CDN');
        $this->description    = $this->l('Make your shop extremely faster and forget managing images.');
        $this->client         = $this->getClient();
        $this->cache          = new \pixelcrush\PSCache();

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '1.7.9.9');

        parent::__construct();
    }

    /**
     * @return bool
     * @throws \RuntimeException
     * @throws PrestaShopException
     */
    public function install()
    {
        // Assets directory is not available in PS 1.7 for example
        if (version_compare(_PS_VERSION_, '1.7.0', '>=')) {
            $this->checkOverrideDirectory('assets');
        }

        return parent::install() &&
               $this->registerHook('actionObjectImageTypeAddAfter') &&
               $this->registerHook('actionObjectImageTypeUpdateAfter') &&
               $this->registerHook('actionObjectImageTypeDeleteAfter') &&
               $this->registerHook('displayBackOfficeHeader');
    }

    /**
     * @param $dirname
     * @throws \RuntimeException
     */
    public function checkOverrideDirectory($dirname)
    {
        $full_path = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'override'
            . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $dirname;

        // check if dir exists first, then try to create AND check if creation succeeded
        if (!is_dir($full_path) && !mkdir($full_path) && !is_dir($full_path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $full_path));
        }

        umask(0000);
        if (!file_exists($full_path.'/index.php') && is_writable($full_path)) {
            copy(dirname(__FILE__).'/index.php', $full_path.'/index.php');
        }
    }

    public function uninstall()
    {
        return parent::uninstall() &&
               Configuration::deleteByName('PIXELCRUSH_ENABLE_IMAGES') &&
               Configuration::deleteByName('PIXELCRUSH_ENABLE_STATICS') &&
               Configuration::deleteByName('PIXELCRUSH_USER_ACCOUNT') &&
               Configuration::deleteByName('PIXELCRUSH_API_SECRET') &&
               Configuration::deleteByName('PIXELCRUSH_FILTERS_PREFIX') &&
               Configuration::deleteByName('PIXELCRUSH_FILL_BACKGROUND') &&
               Configuration::deleteByName('PIXELCRUSH_URL_PROTOCOL');
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit'.$this->name) &&
            Tools::getIsset('PIXELCRUSH_ENABLE_IMAGES') && Tools::getIsset('PIXELCRUSH_ENABLE_STATICS')
        ) {
            $submit = (object)array(
                'enable_images'   => Tools::getValue('PIXELCRUSH_ENABLE_IMAGES'),
                'enable_statics'  => Tools::getValue('PIXELCRUSH_ENABLE_STATICS'),
                'user_account'    => Tools::getValue('PIXELCRUSH_USER_ACCOUNT'),
                'api_secret'      => Tools::getValue('PIXELCRUSH_API_SECRET'),
                'filters_prefix'  => Tools::getValue('PIXELCRUSH_FILTERS_PREFIX'),
                'fill_background' => Tools::getValue('PIXELCRUSH_FILL_BACKGROUND'),
                'url_protocol'    => Tools::getValue('PIXELCRUSH_URL_PROTOCOL')
            );

            if ($this->validateConfig($submit)) {
                // We need to re-initialize config and errors in case the user has changed its user-apiKey to reAuth
                $this->client      = null;
                $this->config      = null;
                $this->_errors     = array();
                $this->user_cloud  = null;
                $error             = false;

                $this->setConfig($submit);

                // Submit/Reset Existing Filters
                if (!$this->resetCdnFilters((bool)Tools::getValue('reset_filters_checked'))) {
                    $error   = true;
                    $output .= $this->displayError($this->_errors);
                } elseif (!$this->client->domainExists($this->client->domain())) {
                    $error   = true;
                    $output .= $this->displayError('The user-id domain cannot be found, please check the value');
                }

                // If some error was detected, we disable the system, just in case
                if ($error) {
                    Configuration::updateValue('PIXELCRUSH_ENABLE_IMAGES', false);
                    Configuration::updateValue('PIXELCRUSH_ENABLE_STATICS', false);
                }

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            } else {
                $output .= $this->displayError('You need to correctly fill all mandatory fields.');
            }
        }

        return $output.$this->displayForm();
    }

    public function setConfig($config)
    {
        Configuration::updateValue('PIXELCRUSH_ENABLE_IMAGES', $config->enable_images);
        Configuration::updateValue('PIXELCRUSH_ENABLE_STATICS', $config->enable_statics);
        Configuration::updateValue('PIXELCRUSH_USER_ACCOUNT', $config->user_account);
        Configuration::updateValue('PIXELCRUSH_API_SECRET', $config->api_secret);
        Configuration::updateValue('PIXELCRUSH_FILTERS_PREFIX', $config->filters_prefix);
        Configuration::updateValue('PIXELCRUSH_FILL_BACKGROUND', $config->fill_background);
        Configuration::updateValue('PIXELCRUSH_URL_PROTOCOL', $config->url_protocol);
    }

    public function validateConfig($config)
    {
        $UUIDv4_format = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';

        if (!is_object($config) || empty($config)) {
            return false;
        }

        if ($config->fill_background === '') {
            $config->fill_background = '#FFFFFF';
        }

        $is_valid  = true;
        $is_valid &= (int)$config->enable_images === 1  || (int)$config->enable_images === 0;
        $is_valid &= (int)$config->enable_statics === 1 || (int)$config->enable_statics === 0;
        $is_valid &= Tools::strlen($config->user_account) >= 3;
        $is_valid &= Tools::strlen($config->api_secret) === 36 && preg_match($UUIDv4_format, $config->api_secret) === 1;
        $is_valid &= Tools::strlen($config->filters_prefix) > 0;
        $is_valid &= preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $config->fill_background) === 1;
        $is_valid &= $config->url_protocol === ''
                  || $config->url_protocol === 'http://'
                  || $config->url_protocol === 'https://';

        return $is_valid;
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
                    'type'     => version_compare(_PS_VERSION_, '1.6.0', '<') ? 'radio' : 'switch',
                    'label'    => $this->l('Enable Image CDN'),
                    'name'     => 'PIXELCRUSH_ENABLE_IMAGES',
                    'required' => true,
                    'is_bool'  => true,
                    'values'   => array(
                        array(
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'lang' => false,
                ),
                array(
                    'type'     => (version_compare(_PS_VERSION_, '1.6.0', '<') ? 'radio' : 'switch'),
                    'label'    => $this->l('Enable Static CDN (.js / .css / fonts files)'),
                    'name'     => 'PIXELCRUSH_ENABLE_STATICS',
                    'required' => true,
                    'is_bool'  => true,
                    'values'   => array(
                        array(
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'lang' => false
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('User Account'),
                    'name'     => 'PIXELCRUSH_USER_ACCOUNT',
                    'class'    => 'col-sm-30',
                    'size'     => 24,
                    'required' => true,
                    'lang'     => false,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('Api Secret'),
                    'name'     => 'PIXELCRUSH_API_SECRET',
                    'size'     => 36,
                    'class'    => 'col-sm-30',
                    'required' => true,
                    'lang'     => false,
                ),
                array(
                    'type'     => 'text',
                    'label'    => $this->l('Filter Alias Prefix'),
                    'name'     => 'PIXELCRUSH_FILTERS_PREFIX',
                    'class'    => 'col-sm-30',
                    'size'     => 12,
                    'required' => true,
                    'lang'     => false,
                ),
                array(
                    'type'    => 'checkbox',
                    'label'   => $this->l('Reset Existing Filters'),
                    'desc'    => $this->l('If this option is checked, any existing filter on your pixelcrush account
                                            will be deleted before uploading the actual ones.'),
                    'name'   => 'reset_filters',
                    'values' => array(
                        'query' => array(
                            array(
                                'id'   => 'checked',
                                'name' => '',
                                'val'  => '1'
                            ),
                        ),
                        'id'   => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type'  => 'select',
                    'label' => $this->l('Add protocol to proxied url:'),
                    'desc'  => $this->l('Adds protocol to original resource url. If this is not set pixelcrush will
                                        access the resources using always http.'),
                    'name'     => 'PIXELCRUSH_URL_PROTOCOL',
                    'required' => true,
                    'options'  => array(
                        'id'     => 'id_option',
                        'name'   => 'name',
                        'query'  => array(
                            array(
                                'id_option' => '',
                                'name'      => 'Without Protocol'
                            ),
                            array(
                                'id_option' => 'http://',
                                'name'      => 'http://'
                            ),
                            array(
                                'id_option' => 'https://',
                                'name'      => 'https://'
                            ),
                        )
                    )
                ),
                array(
                    'type'     => 'color',
                    'label'    => $this->l('Fill Background'),
                    'name'     => 'PIXELCRUSH_FILL_BACKGROUND',
                    'lang'     => false,
                    'size'     => 15,
                    'desc'     => $this->l('Use color picker for color.'),
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
                'type'         => 'html',
                'name'         => 'PIXELCRUSH_logo',
                'html_content' => '<img id="pixelcrush-logo-hd" '
                    .'src="../modules/pixelcrush/views/img/pixelcrush-logo.png" />'
            );
            array_unshift($fields_form[0]['form']['input'], $img);
        }

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module          = $this;
        $helper->name_controller = $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex    = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language    = $this->context->language->id;
        $helper->allow_employee_form_lang = $this->context->language->id;

        // Title and toolbar
        $helper->title          = $this->displayName;
        $helper->show_toolbar   = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action  = 'submit'.$this->name;
        $helper->toolbar_btn    = array(
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
        $helper->fields_value['PIXELCRUSH_ENABLE_IMAGES']    = Configuration::get('PIXELCRUSH_ENABLE_IMAGES');
        $helper->fields_value['PIXELCRUSH_ENABLE_STATICS']   = Configuration::get('PIXELCRUSH_ENABLE_STATICS');
        $helper->fields_value['PIXELCRUSH_USER_ACCOUNT']     = Configuration::get('PIXELCRUSH_USER_ACCOUNT');
        $helper->fields_value['PIXELCRUSH_API_SECRET']       = Configuration::get('PIXELCRUSH_API_SECRET');
        $helper->fields_value['PIXELCRUSH_FILTERS_PREFIX']   = Configuration::get('PIXELCRUSH_FILTERS_PREFIX');
        $helper->fields_value['PIXELCRUSH_FILL_BACKGROUND']  = Configuration::get('PIXELCRUSH_FILL_BACKGROUND');
        $helper->fields_value['PIXELCRUSH_URL_PROTOCOL']     = Configuration::get('PIXELCRUSH_URL_PROTOCOL');

        return $helper->generateForm($fields_form);
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    public function loadImagesTypeHashes()
    {
        $this->images_types_hash = array();

        foreach (array('products', 'categories', 'manufacturers', 'suppliers') as $type) {
            $type_hash = array();

            foreach (ImageType::getImagesTypes($type) as $image_type) {
                $type_hash[ $image_type['name'] ] = $image_type;
            }

            $this->images_types_hash[ $type ] = $type_hash;
        }
    }

    public function loadCloudFiltersHash(array $cloud_cdn_filters)
    {
        $this->cloud_filters_hash = array();
        foreach ($cloud_cdn_filters as $filter) {
            $this->cloud_filters_hash[ $filter->name ] = $filter;
        }
    }

    public function rezFilter($width, $height)
    {
        $bg     = str_replace('#', '', Configuration::get('PIXELCRUSH_FILL_BACKGROUND'));
        $filter = sprintf('rz(o=f,w=' . $width . ',h=' . $height . ',b=%s)', (string)$bg);
        return $filter;
    }

    public function psFilterMap($entity, $type)
    {
        $filter_prefix = Configuration::get('PIXELCRUSH_FILTERS_PREFIX');

        if (!empty($filter_prefix)) {
            return $filter_prefix . '_' . Tools::substr($entity, 0, 2) . '_' . $type;
        }

        return Tools::substr($entity, 0, 2) . '_' . $type;
    }

    /**
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public function imageTypesAsFilters()
    {
        if ($this->images_types_hash === null) {
            $this->loadImagesTypeHashes();
        }

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

                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * @return mixed|null
     */
    public function getUserCloud()
    {
        // if already loaded in this pageview
        if ($this->user_cloud !== null) {
            return $this->user_cloud;
        }

        // is cached by our class
        $this->user_cloud = $this->cache->get('PIXELCRUSH_USER_CLOUD');
        if ($this->user_cloud !== null) {
            return $this->user_cloud;
        }

        // else, get it from API
        try {
            if ($this->apiIsCallable()) {
                // Cached results not valid anymore, we need to get them from user cloud and cache them again
                $this->user_cloud = $this->client->userCloud();
                $this->cache->set('PIXELCRUSH_USER_CLOUD', $this->user_cloud, 24*3600);
                return $this->user_cloud;
            }
        } catch (Exception $e) {
            $this->logError($e->getMessage());
        }

        return null;
    }

    /**
     * @param $msg
     */
    private function logError($msg)
    {
        if (_PS_MODE_DEV_) {
            \Tools::error_log($msg);
        } elseif (version_compare(_PS_VERSION_, '1.6.0', '>=')) {
            \PrestaShopLogger::addLog($msg);
        } else {
            \Logger::addLog($msg);
        }
    }

    /**
     * @param $url
     * @param $entity
     * @param $type
     * @return string
     * @throws PrestaShopDatabaseException
     */
    public function pixelcrushProxy($url, $entity, $type)
    {
        $params           = array();
        $filter           = null;
        $user_cloud       = $this->getUserCloud();

        if ($user_cloud !== null && $this->cloud_filters_hash === null) {
            $this->loadCloudFiltersHash($this->user_cloud->cdn->filters);
        }

        // Using cloud filters if available
        if (isset($this->cloud_filters_hash[$this->psFilterMap($entity, $type)])) {
            $filter = $this->cloud_filters_hash[$this->psFilterMap($entity, $type)];
        }

        // Ensure we have backup local image types just in case all/any cloud filter fails
        if ($this->images_types_hash === null) {
            $this->loadImagesTypeHashes();
        }

        // Using hashed local image types if cloud is not available or has invalid value
        if ($filter === null && !empty($entity) && !empty($type)) {
            $image_type  = $this->images_types_hash[$entity][$type];
            $params['f'] = $this->rezFilter($image_type['width'], $image_type['height']);
        }

        // Ensure clients exists and build the url with the available data (cloud filter name or local resizing values)
        if ($this->client !== null) {
            return $this->client->imgProxiedUrl($url, $params, $filter, $this->config->url_protocol);
        }

        return $url;
    }

    public function cdnProxy($local_uri, $remote_uri, $newAssetManager = false)
    {
        $cdn_uri = null;

        if ($this->getClient() && @filemtime($local_uri) && @filesize($local_uri)) {
            $pixelcrush_proxy = $this->client->domain().'/cdn/';
            $pixelcrush_ts    = '?ttl='.filemtime($local_uri);

            if ($newAssetManager) {
                // 1.7: AbstractAssetManager
                $url     = preg_replace('(^https?://)', '', ltrim(__PS_BASE_URI__.$remote_uri, '/'));
                $cdn_uri = $pixelcrush_proxy. Configuration::get('PIXELCRUSH_URL_PROTOCOL'). $url. $pixelcrush_ts;
            } else {
                // Legacy 1.7 / 1.6 / 1.5 Media
                $cdn_uri = $pixelcrush_proxy . Configuration::get('PIXELCRUSH_URL_PROTOCOL') . Tools::getShopDomain()
                    . __PS_BASE_URI__ . ltrim($remote_uri, '/') . $pixelcrush_ts;
            }
        }

        return ($cdn_uri ?: $remote_uri);
    }

    public function isConfigured()
    {
        if (!empty($this->config->id) && !empty($this->config->api_secret_key)) {
            return true;
        }

        $this->config = (object)array(
            'enable_images'   => Configuration::get('PIXELCRUSH_ENABLE_IMAGES', false),
            'enable_statics'  => Configuration::get('PIXELCRUSH_ENABLE_STATICS', false),
            'id'              => Configuration::get('PIXELCRUSH_USER_ACCOUNT'),
            'api_secret_key'  => Configuration::get('PIXELCRUSH_API_SECRET'),
            'rz_bg'           => Configuration::get('PIXELCRUSH_FILL_BACKGROUND'),
            'filters_prefix'  => Configuration::get('PIXELCRUSH_FILTERS_PREFIX'),
            'url_protocol'    => Configuration::get('PIXELCRUSH_URL_PROTOCOL'),
        );

        return !empty($this->config->id) && !empty($this->config->api_secret_key);
    }

    public function hookDisplayBackOfficeHeader(array $params)
    {
        // Load js/css styling files strictly only when user is on the configure module page
        if ($this->context->controller->controller_name === 'AdminModules' &&
            Tools::getIsset('configure') && Tools::getValue('configure') === 'pixelcrush'
        ) {
            if (version_compare(_PS_VERSION_, '1.6.0', '<')) {
                $this->context->controller->addJS($this->_path . 'views/js/pixelcrush-bo.js');
            }
            $this->context->controller->addCSS($this->_path . 'views/css/pixelcrush-bo.css');
        }
    }

    public function hookActionObjectImageTypeAddAfter(array $params)
    {
        $this->processImageActionsHook($params);
    }

    public function hookActionObjectImageTypeDeleteAfter(array $params)
    {
        $this->processImageActionsHook($params);
    }

    public function hookActionObjectImageTypeUpdateAfter(array $params)
    {
        $this->processImageActionsHook($params);
    }

    public function processImageActionsHook($params)
    {
        if (isset($params['object'])) {
            if ($params['object'] instanceof ImageType) {
                if (!$this->resetCdnFilters(false)) {
                    // Show (probably auth) error on admin image size form
                    $this->context->controller->errors = $this->getErrors();
                }
            }
        }
    }

    /**
     * @param bool $reset_existing
     * @return bool
     */
    public function resetCdnFilters($reset_existing = true)
    {
        if ($this->apiIsCallable(false)) {
            try {
                // Ignore cached cloud, we need current account data to process
                $user_cloud = $this->client->userCloud();

                if ($reset_existing) {
                    foreach ($user_cloud->cdn->filters as $filter) {
                        if (!empty($filter->name)) {
                            $this->client->userCdnFilterDelete($filter->name);
                        }
                    }
                }

                // Get actual image sizes configuration and upload as filters
                foreach ($this->imageTypesAsFilters() as $filter) {
                    $this->client->userCdnFilterUpsert($filter);
                }

                // User cloud has new data: update cache. TTL has already been updated by apiIsCallable()
                $this->user_cloud = $user_cloud;
                $this->cache->set('PIXELCRUSH_USER_CLOUD', $this->user_cloud, 24*3600);

                return true;
            } catch (Exception $e) {
                $this->_errors[] = $e->getMessage();
            }
        }

        return false;
    }

    public function apiIsCallable($use_cache = true)
    {
        $client      = $this->getClient();
        if (!$client) {
            return false;
        }

        // being PHP 5.3 compatible is hard, we need to trick anonymous functions to be able to use $this vars.
        $errors_array = $this->_errors;
        $auth_api     = function () use ($client, &$errors_array) {
            $ok = false;
            try {
                // Get client auth
                $ok = $client->userAuth();
            } catch (Exception $e) {
                $errors_array[] = $e->getMessage();
            }

            return $ok;
        };

        $cached_value = null;
        if ($use_cache) {
            $cached_value = $this->cache->get('PIXELCRUSH_API_CHECK_VALID');
        }

        $is_callable = $cached_value !== null ? (bool)$cached_value : $auth_api();

        if ($is_callable) {
            $value = '1';
            $ttl   = 24*3600;
        } else {
            $value = '0';
            $ttl   = 300;
        }

        $this->cache->set('PIXELCRUSH_API_CHECK_VALID', $value, $ttl);

        return $is_callable;
    }

    public function getClient()
    {
        if ($this->isConfigured()) {
            if ($this->client === null) {
                try {
                    $this->client = new \pixelcrush\ApiClient($this->config->id, $this->config->api_secret_key);
                } catch (Exception $e) {
                    $this->_errors[] = $e->getMessage();
                }
            }
        }

        return $this->client;
    }
}
