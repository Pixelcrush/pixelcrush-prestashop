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

var postAjaxProcess = function(url) {
    var URL = url.split('?');
    $.post(
        URL[0],
        JSON.parse('{"' + decodeURI(URL[1]).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g,'":"') + '"}')
    );
};

var reloadPage = function() {
    setTimeout(function() {
        location.reload();
    }, 333);
};

$(document).ready(function() {
    if ($('#pixelcrush-logo-hd').length == 0) {
        var img = '<div class="margin-form">' +
            '<img id="pixelcrush-logo-hd" src="../modules/pixelcrush/views/img/pixelcrush-logo.png" />' +
            '</div><div class="clear"></div>';
        $('FORM#configuration_form FIELDSET LEGEND').after(img);
    }

    $('.ajax_action').click(function(e) {
        e.preventDefault();
        postAjaxProcess($(this).attr('href'));
        reloadPage();
    });

    $('.prestashop-switch').has('#PIXELCRUSH_ENABLE_IMAGES_on').click(function(e) {
        if ($('input[name=PIXELCRUSH_ENABLE_IMAGES]:checked').val() && $('#PIXELCRUSH_SAFE_UNINSTALL').val() === '0') {
            e.preventDefault();
            var msg = [
                'You have missing thumbnails on disk.',
                'Image CDN can\'t be disabled until all thumbnails are regenerated on disk.',
                'Click OK to regenerate all thumbnails (process will take some time).'
            ];
            if (confirm(msg.join('\n'))) {
                postAjaxProcess($('#PIXELCRUSH_PROCESS_URL').val()+'&process=regenerate');
                reloadPage();
            }
        };
    });
});
