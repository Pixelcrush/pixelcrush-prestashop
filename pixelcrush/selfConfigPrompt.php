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

$ps_root = dirname(__FILE__).'/../../';

if (!defined('_PS_ROOT_DIR_')) {
    include $ps_root.'/config/config.inc.php';
    require_once $ps_root.'/init.php';

    $scp = new selfConfigPrompt('cli');
    $scp->run();
}

class SelfConfigPrompt extends stdClass
{
    public $params = array(
        'user_account'    => '',
        'api_secret'      => '',
        'enable_images'   => '0',
        'enable_statics'  => '0',
        'filters_prefix'  => 'ps',
        'fill_background' => '#FFFFFF',
        'url_protocol'    => ''
    );

    public $exec_type;

    public function __construct($exec_type = 'self_conf')
    {
        $this->exec_type = $exec_type;
    }

    public function run()
    {
        $msg = '';

        if ($this->exec_type === 'cli') {
            foreach ($_SERVER['argv'] as $key => $arg) {
                if ($key === 0) {
                    continue;
                }

                list($setting, $value) = explode('=', $arg);

                if (array_key_exists($setting, $this->params)) {
                    $this->params[$setting] = $value;
                }
            }
        } else {
            // Interactive parameters get for 1.7 prestashop:module configure method
            echo 'Please provide Pixelcrush configuration values:'.PHP_EOL;

            foreach (array_keys($this->params) as $key) {
                echo $key.': ';
                $this->params[$key] = $this->getParameter($key, fopen('php://stdin', 'rb'));
            }
        }

        if (Module::isInstalled('pixelcrush') === false) {
            $pxc_module = Module::getInstanceByName('pixelcrush');
            $config     = (object)$this->params;

            if ($pxc_module->validateConfig($config)) {
                echo 'Installing module...';
                $pxc_module->install();
                $pxc_module->setConfig($config);
                echo PHP_EOL;
            } else {
                $msg = 'Configuration values are not valid. Please provide correct parameters.';
            }
        } else {
            $msg = 'Pixelcrush module is already installed. Aborting.';
        }

        if ($msg === '') {
            $msg = 'Module installed and configuration successfully applied. Thank you for using Pixelcrush.';
        }

        echo $msg.PHP_EOL;

        return true;
    }

    public function getParameter($param, $handle)
    {
        $line = trim(fgets($handle));

        if (empty($line) && ($param !== 'fill_background' && $param !== 'url_protocol')) {
            echo 'Parameter not provided. Aborting'.PHP_EOL;
            exit;
        }

        fclose($handle);
        return $line;
    }
}
