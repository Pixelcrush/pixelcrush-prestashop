$(document).ready(function() {
    if ($('#pixelcrush-logo-hd').length == 0) {
        var img = '<div class="margin-form">' +
            '<img id="pixelcrush-logo-hd" src="../modules/pixelcrush/views/img/pixelcrush-logo.png" />' +
            '</div><div class="clear"></div>';
        $('FORM#configuration_form FIELDSET LEGEND').after(img);
    }
});
