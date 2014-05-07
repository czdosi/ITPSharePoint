jQuery(document).ready(function() {

    var size = window.getSize();
    var element = jQuery("#itp-sharepoint");

    if (size.x < itpSharePointMinWidth) {
        jQuery(element).attr("class", "itp-sharepoint itp-sharepoint-left");
    }

    jQuery(window).on("resize", function(){

        var size = window.getSize();

        if (size.x < itpSharePointMinWidth) {
            jQuery(element).attr("class", "itp-sharepoint itp-sharepoint-left");
        } else {
            jQuery(element).attr("class", "itp-sharepoint itp-sharepoint-floating itp-sharepoint-fstyle");
        }

    });

});