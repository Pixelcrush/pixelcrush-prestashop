<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

class StylesheetManager extends StylesheetManagerCore
{
    private $valid_media = array(
        'all',
        'braille',
        'embossed',
        'handheld',
        'print',
        'projection',
        'screen',
        'speech',
        'tty',
        'tv',
    );
    
    protected function add($id, $fullPath, $media, $priority, $inline, $server)
    {
        $priority = is_int($priority) ? $priority : self::DEFAULT_PRIORITY;
        $media = $this->getSanitizedMedia($media);

        if ('remote' === $server) {
            $uri = $fullPath;
            $type = 'external';
        } else {
            $uri = $this->getFQDN().parent::getUriFromPath($fullPath);
            $type = ($inline) ? 'inline' : 'external';
        }
        
        // Build Pixelcrush Proxied URL
        if (Module::IsEnabled('pixelcrush')) {
            $pixelcrush = Module::getInstanceByName('pixelcrush');
            if ($pixelcrush->isConfigured() && $pixelcrush::$config->enable_statics) {
                $uri = $pixelcrush->cdnProxy(_PS_ROOT_DIR_.$fullPath, $uri, true);
            }
        }

        $this->list[$type][$id] = array(
            'id' => $id,
            'type' => $type,
            'path' => $fullPath,
            'uri' => $uri,
            'media' => $media,
            'priority' => $priority,
            'server' => $server,
        );
    }

    protected function getSanitizedMedia($media)
    {
        // Overrided visibility
        return in_array($media, $this->valid_media, true) ? $media : self::DEFAULT_MEDIA;
    }
}
