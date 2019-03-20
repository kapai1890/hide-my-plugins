jQuery(function () {
    if (HideMyPlugins == undefined || HideMyPlugins.totalsFixedText == undefined) {
        return;
    }

    jQuery('.displaying-num').text(HideMyPlugins.totalsFixedText);
});
