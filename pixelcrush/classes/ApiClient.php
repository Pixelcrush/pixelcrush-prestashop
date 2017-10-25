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
 
namespace pixelcrush;

class ApiClient
{
    private $id;
    private $api_secret_key;
    
    public function __construct($id, $api_secret_key)
    {
        if (empty($id)) {
            throw new \Exception("id can not be empty");
        }
        
        if (empty($api_secret_key)) {
            throw new \Exception("secret key can not be empty");
        }
                
        $this->id               = $id;
        $this->api_secret_key   = $api_secret_key;
    }
    
    public function userCloud()
    {
        $url        = $this->domain() . '/user/cloud';
        $response   = $this->rest($url, 'GET', null, $this->auth('GET', $url, null, time()));
        
        \Configuration::updateValue('PIXELCRUSH_API_CLOUD_TTL', date('Y-m-d H:i:s', strtotime('+5 minutes')));
        
        return $response->result;
    }

    public function userCdnFilterUpsert($filter)
    {
        $url        = $this->domain() . '/user/cdn/filter';
        $response   = $this->rest($url, 'POST', $filter, $this->auth('POST', $url, json_encode($filter), time()));
        
        return $response->result;
    }

    public function userCdnFilterDelete($filter_name)
    {
        $url        = $this->domain() . "/user/cdn/filter/$filter_name";
        $response   = $this->rest($url, 'DELETE', null, $this->auth('DELETE', $url, null, time()));
        
        return $response->result;
    }
    
    public function userInitStore($unit, $image, $id = null)
    {
        if (!is_string($image)) {
            $image = json_encode($image);
        }
            
        $url        = $this->domain() . "/st/$unit/$id";
        $response   = $this->rest($url, 'POST', $image, $this->auth('POST', $url, $image, time()));
        
        return $response->result;
    }
    
    public function imgProxiedUrl($url, $params = null, $filter = null, $domains = null, $url_protocol = false)
    {
        // Add protocol to proxied url?
        $url = preg_replace('#^https?://#', '', $url);
        if (!empty($url_protocol)) {
            $url = $url_protocol . $url;
        }
        
        $proxy_url = (string)$this->domain($domains, $url);
        
        if (isset($filter) && !empty($filter->name)) {
            // cloud filter
            $proxy_url .= '/'. $filter->name .'/' . $url;
        } elseif (count($params)) {
            // url params
            $proxy_url .= '/'. $url . '?' . http_build_query($params);
        } else {
            // just acting as proxy on the original image
            $proxy_url .= '/'. $url;
        }
        
        return urldecode($proxy_url);
    }
    
    public function domain($domains = null, $resource = null)
    {
        $domain = $this->id . ".pixelcrush.io";
        
        if (is_array($domains)) {
            $domains_len = count($domains);
            
            if ($domains_len) {
                $index  = empty($resource) ? $domains[0] : abs($this->strHashCode($resource) % $domains_len);
                $domain = $domains[$index]->name;
            }
        }
        
        // Use same site protocol for pixelcrush domain
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . '://' . $domain;
    }

    public function rest($endpoint, $method = 'GET', $data = null, $get = null, $headers = null)
    {
        $response   = $this->restCall($endpoint, $method, $data, $get, $headers);
        $code       = $response->code;
        
        if (empty($code) || !in_array($code, array(200, 201))) {
            error_log("Pixelcrush ApiClient Exception [$code]: ".$response->content->error);
        }
        
        return $response->content;
    }
    
    public function restCall($endpoint, $method = 'GET', $data = null, $get = null, $headers = null)
    {
        $json_data = is_string($data) ? $data : json_encode($data);

        if ($get != null) {
            $endpoint .= '?'. (is_string($get) ? $get : http_build_query($get));
        }

        $headers = array
        (
            'Accept: application/json',
            'Content-Type: application/json',
        );

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $endpoint);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        switch (\Tools::strtoupper($method)) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $json_data);
                break;
            case 'PUT':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($handle, CURLOPT_POSTFIELDS, $json_data);
                break;
            case 'DELETE':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($handle, CURLOPT_POSTFIELDS, $json_data);
                break;
        }

        $content    = curl_exec($handle);
        $code       = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        return (object)array('code'=>$code, 'content'=> json_decode($content));
    }
    
    public function auth($method, $op, $body, $time = false)
    {
        $params = array('auth' => $this->customHmac('sha1', $method . $op . $body . $time, $this->api_secret_key));
            
        if (!is_null($time)) {
            $params['authttl'] = time();
        }
        
        return $params;
    }
    
    public function strHashCode($str)
    {
        $h = 0;

        for ($i=0; $i < \Tools::strlen($str); $i++) {
            $h = (int)(31 * $h + ord($str[$i])) & 0xffffffff;
        }

        return $h;
    }
    
    public function customHmac($algo, $data, $key, $raw_output = false)
    {
        $algo = \Tools::strtolower($algo);
        $pack = 'H'.\Tools::strlen($algo('test'));
        $size = 64;
        $opad = str_repeat(chr(0x5C), $size);
        $ipad = str_repeat(chr(0x36), $size);
    
        if (\Tools::strlen($key) > $size) {
            $key = str_pad(pack($pack, $algo($key)), $size, chr(0x00));
        } else {
            $key = str_pad($key, $size, chr(0x00));
        }
    
        for ($i = 0; $i < \Tools::strlen($key) - 1; $i++) {
            $opad[$i] = $opad[$i] ^ $key[$i];
            $ipad[$i] = $ipad[$i] ^ $key[$i];
        }
    
        $output = $algo($opad.pack($pack, $algo($ipad.$data)));
    
        return ($raw_output) ? pack($pack, $output) : $output;
    }
}
