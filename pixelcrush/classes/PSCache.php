<?php
/**
 * Pixelcrush CDN Prestashop Module
 *
 * Copyright 2018 Imagenii, Inc.
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

namespace pixelcrush;

if (!defined('_PS_VERSION_')) {
    exit;
}


class PSCache
{
    public $type;
    public $ps_cache;

    const PRESTASHOP_TYPE = 0;
    const DB_TYPE         = 1;

    /**
     * PixelcrushCache constructor.
     */
    public function __construct()
    {
        $this->type = self::DB_TYPE;

        if (_PS_CACHE_ENABLED_ === '1') {
            $ps_cache   = \Cache::getInstance();

            // We try the cache just to be sure everything is working fine
            $ps_cache->set('pixelcrush_test', '1', 10);
            $is_ok      = $ps_cache->get('pixelcrush_test') === '1';

            if ($is_ok) {
                $this->type      = self::PRESTASHOP_TYPE;
                $this->ps_cache  = $ps_cache;
            }
        }
    }

    /**
     * @param string $msg
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
     * @param string $key
     * @return bool
     */
    public function exists($key)
    {
        if ($this->type === self::PRESTASHOP_TYPE) {
            return $this->ps_cache->exists($key);
        }

        //our own internal db-cache type
        $value = \Configuration::get($key);
        if ($value) {
            try {
                $obj = \json_decode($value);
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
                return false;
            }

            if ($obj->expires_at > date('Y-m-d H:i:s')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        if ($this->type === self::PRESTASHOP_TYPE) {
            $value = $this->ps_cache->get($key);
            return $value !== false ? $value : null;
        }

        //our own internal db-cache type
        $value = \Configuration::get($key);
        if ($value) {
            try {
                $obj = \json_decode($value);
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
                return null;
            }

            if ($obj->expires_at > date('Y-m-d H:i:s')) {
                return $obj->data;
            }
        }

        return null;
    }

    /**
     * @param string $key
     * @param \stdClass|string $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl)
    {
        if ($this->type === self::PRESTASHOP_TYPE) {
            return $this->ps_cache->set($key, $value, $ttl);
        }

        $obj = (object)array(
            'expires_at' => date('Y-m-d H:i:s', strtotime("+${ttl} seconds")),
            'data'       => $value
        );

        return \Configuration::updateValue($key, json_encode($obj));
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        if ($this->type === self::PRESTASHOP_TYPE) {
            $deleted_keys = $this->ps_cache->delete($key);
            return !empty($deleted_keys);
        }

        return \Configuration::deleteByName($key);
    }
}
