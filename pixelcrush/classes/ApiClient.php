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
    private $cached_domain;

    public $auth_valid;

    /**
     * ApiClient constructor.
     * @param $id
     * @param $api_secret_key
     * @throws \InvalidArgumentException
     */
    public function __construct($id, $api_secret_key)
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ApiClient: id can not be empty');
        }

        if (empty($api_secret_key)) {
            throw new \InvalidArgumentException('ApiClient: secret key can not be empty');
        }

        $this->id = $id;
        $this->api_secret_key = $api_secret_key;
    }

    /**
     * @param string $endpoint
     * @return bool
     */
    public function domainExists($endpoint)
    {
        $parsed_url = parse_url($endpoint);
        $host       = $parsed_url['host'];

        return !(checkdnsrr($host, 'CNAME') === false);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function userAuth()
    {
        if ($this->auth_valid !== null) {
            return $this->auth_valid;
        }

        $url = $this->domain() . '/user/auth';

        try {
            $response         = $this->rest($url, 'GET', json_encode($this), $this->auth('GET', $url, null, time()));
            $this->auth_valid = isset($response->status) && $response->status === 200;
        } catch (\Exception $e) {
            // We need to throw the exception back to the base try caller
            $this->auth_valid = false;
            throw $e;
        }

        return $this->auth_valid;
    }

    /**
     * @return mixed
     * @throws \RuntimeException
     */
    public function userCloud()
    {
        $url        = $this->domain() . '/user/cloud';
        $response   = $this->rest($url, 'GET', null, $this->auth('GET', $url, null, time()));

        return $response->result;
    }

    /**
     * @param $filter
     * @return mixed
     * @throws \RuntimeException
     */
    public function userCdnFilterUpsert($filter)
    {
        $url        = $this->domain() . '/user/cdn/filter';
        $response   = $this->rest($url, 'POST', $filter, $this->auth('POST', $url, json_encode($filter), time()));

        return $response->result;
    }

    /**
     * @param $filter_name
     * @return mixed
     * @throws \RuntimeException
     */
    public function userCdnFilterDelete($filter_name)
    {
        $url        = $this->domain() . "/user/cdn/filter/$filter_name";
        $response   = $this->rest($url, 'DELETE', null, $this->auth('DELETE', $url, null, time()));

        return $response->result;
    }


    /**
     * @param $url
     * @param null $params
     * @param null $filter
     * @param bool $url_protocol
     * @return string
     */
    public function imgProxiedUrl($url, $params = null, $filter = null, $url_protocol = false)
    {
        // Add protocol to proxied url?
        $url = preg_replace('#^https?://#', '', $url);
        if (!empty($url_protocol)) {
            $url = $url_protocol . $url;
        }

        $proxy_url = $this->domain();

        if ($filter !== null && !empty($filter->name)) {
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

    /**
     * @param bool $force_ssl
     * @return string
     */
    public function domain($force_ssl = true)
    {
        if ($this->cached_domain !== null) {
            return $this->cached_domain;
        }

        $domain   = $this->id . '.pixelcrush.io';
        $protocol = 'https://';

        if (!$force_ssl) {
            // Use same site protocol for pixelcrush domain
            $proxy_proto  = null; // take load balancer into account
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                $proxy_proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
            } elseif (isset($_SERVER['X-Forwarded-Proto'])) {
                $proxy_proto = $_SERVER['X-Forwarded-Proto'];
            }

            $protocol = $proxy_proto;
            if ($protocol === null) {
                $protocol = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '');
            }
        }

        $this->cached_domain = $protocol . $domain;

        return $this->cached_domain;
    }

    /**
     * @param $endpoint
     * @param string $method
     * @param null $data
     * @param null $get
     * @return mixed
     * @throws \RuntimeException
     */
    public function rest($endpoint, $method = 'GET', $data = null, $get = null)
    {
        $response   = $this->restCall($endpoint, $method, $data, $get);
        $code       = $response->code;

        if (in_array($code, array(200, 201), true)) {
            return $response->content;
        }

        if ($code === 401) {
            throw new \RuntimeException('Pixelcrush Authentication Error', 401);
        }

        throw new \RuntimeException(
            'Pixelcrush ApiClient Exception: '. ( !empty($response->content)
                                                  ? $response->content->error
                                                  : 'Unknown Error' ),
            ($code ?: 500)
        );
    }

    /**
     * @param string $endpoint
     * @param string $method
     * @param mixed $data
     * @param string $get
     * @return \stdClass
     */
    public function restCall($endpoint, $method = 'GET', $data = null, $get = null)
    {
        $json_data = is_string($data) ? $data : json_encode($data);

        if ($get !== null) {
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
        //curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

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

    /**
     * @param $method
     * @param $op
     * @param $body
     * @param null $time
     * @return array
     */
    public function auth($method, $op, $body, $time = null)
    {
        $params = array('auth' => $this->customHmac('sha1', $method . $op . $body . $time, $this->api_secret_key));

        if ($time !== null) {
            $params['authttl'] = time();
        }

        return $params;
    }

    /**
     * @param $algo
     * @param $data
     * @param $key
     * @param bool $raw_output
     * @return string
     */
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

        $output = $algo( $opad.pack($pack, $algo($ipad.$data)) );

        return $raw_output ? pack($pack, $output) : $output;
    }
}
