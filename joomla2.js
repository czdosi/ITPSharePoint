window.addEvent( "domready" ,  function() {

    var size = window.getSize();

    if (size.x < itpSharePointMinWidth) {
        document.id("itp-sharepoint").set("class", "itp-sharepoint-left");
    }

    window.addEvent("resize", function(){

        var size = window.getSize();

        if (size.x < itpSharePointMinWidth) {
            document.id("itp-fshare").set("class", "itp-sharepoint itp-sharepoint-left");
        } else {
            document.id("itp-fshare").set("class", "itp-sharepoint itp-sharepoint-floating itp-sharepoint-fstyle");
        }

    });

});