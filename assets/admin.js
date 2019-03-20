jQuery(function () {
    "use strict";

    if (HideMyPlugins == undefined || HideMyPlugins.totalsFixedText == undefined) {
        return;
    }

    jQuery('.displaying-num').text(HideMyPlugins.totalsFixedText);
});
