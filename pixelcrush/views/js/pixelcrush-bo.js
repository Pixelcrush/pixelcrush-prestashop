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

$(document).ready(function() {
    if ($('#pixelcrush-logo-hd').length == 0) {
        var img = '<div class="margin-form">' +
            '<img id="pixelcrush-logo-hd" src="../modules/pixelcrush/views/img/pixelcrush-logo.png" />' +
            '</div><div class="clear"></div>';
        $('FORM#configuration_form FIELDSET LEGEND').after(img);
    }
});
