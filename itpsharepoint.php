<?php
/**
 * @package		 ITPrism Plugins
 * @subpackage	 ITPSharePoint
 * @copyright    Copyright (C) 2010 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * ITPSharePoint is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * ITPSharePoint Plugin
 *
 * @package		ITPrism Plugins
 * @subpackage	ITPSharePoint
 */
class plgSystemITPSharePoint extends JPlugin {
    
    private $locale         = "en_US";
    private $fbLocale       = "en_US";
    private $plusLocale     = "en";
    private $gshareLocale   = "en";
    private $twitterLocale  = "en";
    private $currentView    = "";
    private $currentTask    = "";
    private $currentOption  = "";
    
    private $imgPattern     = '/src="([^"]*)"/i'; 
    
    /**
     * Clen the meta data
     */
    public function onBeforeCompileHead(){
        
        $app = JFactory::getApplication();
        /** @var $app JApplication **/

        if($app->isAdmin()) {
            return;
        }
        
        $doc   = JFactory::getDocument();
        /** @var $doc JDocumentHtml **/
        
        if($this->params->get("loadCss")) {
            $doc->addStyleSheet(JURI::root() . "plugins/system/itpsharepoint/style.css");
        }
        
        // Remove the indicator in the site description.
        // That prevent putting the code into the meta tags
        $desc = $doc->getDescription();
        if(false !== strpos($desc, "{itpsharepoint}")) {
            $desc = str_replace("{itpsharepoint}", "", $desc);
            $doc->setDescription($desc);
        }
        
    }
    
    /**
     * Add social buttons into the article
     */
    public function onAfterRender(){
        
        $app = JFactory::getApplication();
        /** @var $app JApplication **/

        if($app->isAdmin()) {
            return;
        }
        
        $buffer = JResponse::getBody();

        $doc     = JFactory::getDocument();
        /** @var $doc JDocumentHtml **/
        
        // Check document type
        $docType = $doc->getType();
        if(strcmp("html", $docType) != 0){
            return "";
        }
        
        $this->currentView    = $app->input->get->get("view");
        $this->currentTask    = $app->input->get->get("task");
        $this->currentOption  = $app->input->get->get("option");
        
        if(!$this->isAllowed()) {
            return;            
        }
      
        // Load language file
        $this->loadLanguage();
        
        // Get locale code automatically
        if($this->params->get("dynamicLocale", 0)) {
            $lang   = JFactory::getLanguage();
            $locale = $lang->getTag();
            $this->locale = str_replace("-","_",$locale);
        }
        
        // Generate the buttons
        $buttons    = $this->getContent();
        $buffer     = str_replace("{itpsharepoint}", $buttons, $buffer);
        
        // Put namespace in the HTML element
        if($this->params->get("facebookPutNamespace", 0) AND (1 == $this->params->get("facebookLikeRenderer", 2)) ) {
            $buffer = $this->putNamespaces($buffer);
        }
        
        JResponse::setBody($buffer);
        
    }
    
	/**
	 * 
	 * Put namespace schema to the HTML tag if rendering by XFBML
	 * @param string $buffer Output buffer
	 */
	private function putNamespaces($buffer) {
	    
	    $pattern = "/<html.*>/i";
	    if(preg_match($pattern, $buffer, $matches)) {
	        if(false === strpos($matches[0], 'http://ogp.me/ns/fb#')) {
	            $string   = ' xmlns:fb="http://ogp.me/ns/fb#" ';
        
                $newHtmlAttr = '<html '.$string; 
                $buffer      = str_replace("<html", $newHtmlAttr, $buffer);
	        }
	    }
	    
        return $buffer;
	}
	
    
    private function isAllowed() {
        
        // Parse the views where you want to put the buttons
        $displayIn = $this->params->get("displayIn");
        if(!empty($displayIn)) {
            $displayIn = str_replace("'",'"',$displayIn);
            $displayIn = explode(";",$displayIn);
            $displayInViews = array();
            
            // In JoomlaShopping task value is a view
            if( (strcmp($this->currentOption, "com_jshopping") == 0) AND (strcmp($this->currentTask, "view") == 0) ) {
                $this->currentView = "view";
            }
            
            foreach($displayIn as $item) {
                $item = json_decode(trim($item),true);
                if(!empty($item)) {
                    if($this->currentOption == $item[0] AND $this->currentView == $item[1])  {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Generate content
     * @return  string      Returns html code or empty string.
     */
    private function getContent(){
        
        $url    = JURI::getInstance()->toString();
        $title  = JFactory::getDocument()->getTitle();
        
        // Convert the url to short one
        if($this->params->get("shortener_service")) {
            $url = $this->getShortUrl($url);
        }
        
        // Start buttons box
        $html = '
        <div class="itp-sharepoint">';
        
        $html .= $this->getTwitter($this->params, $url, $title);
        $html .= $this->getStumbpleUpon($this->params, $url);
        $html .= $this->getLinkedIn($this->params, $url);
        $html .= $this->getBuffer($this->params, $url, $title);
        $html .= $this->getPinterest($this->params, $url, $title);
        $html .= $this->getReddit($this->params, $url, $title);
        $html .= $this->getTumblr($this->params, $url);
        $html .= $this->getFacebookLike($this->params, $url);
        $html .= $this->getGooglePlusOne($this->params, $url);
        $html .= $this->getGoogleShare($this->params, $url);
        
        // Gets extra buttons
        $html   .= $this->getExtraButtons($this->params, $url, $title);
        
        // End buttons box
        $html .= '
        </div>
        <div style="clear:both;"></div>
        ';
    
        return $html;
    }
    
    /**
     * Get an image from VirtueMart product details
     * @param array $uriParams
     */
    private function getVirtuemartImage($uriParams) {
        
        $image = "";
        
        if(!empty($uriParams["virtuemart_product_id"])) {

            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $query = $db->getQuery(true);
            
            $query
                ->select("#__virtuemart_medias.file_url")
                ->from("#__virtuemart_medias")
                ->join("RIGHT", "#__virtuemart_product_medias ON #__virtuemart_product_medias.virtuemart_media_id = #__virtuemart_medias.virtuemart_media_id")
                ->where("#__virtuemart_product_medias.virtuemart_product_id=" . (int)$uriParams["virtuemart_product_id"]);
              
            $db->setQuery($query, 0, 1);
            $fileURL = $db->loadResult();
            
            if(!empty($fileURL)) {
                $image = JURI::base().$fileURL;
            }
            
        }
        
        return $image;
    }
    
	/**
     * Get an image from Content article
     * @param array $uriParams
     */
    private function getContentImage($uriParams) {
        
        $image = "";
        
        if(!empty($uriParams["id"])) {

            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $query = $db->getQuery(true);
            
            $query
                ->select("#__content.images")
                ->from("#__content")
                ->where("#__content.id=" . (int)$uriParams["id"]);
              
            $db->setQuery($query, 0, 1);
            $params = $db->loadResult();
            
            if(!empty($params)) {
                $params  = json_decode($params, true);
                
                $fileURL = JArrayHelper::getValue($params, "image_intro");
                if(!empty($fileURL)) {
                    $image   = JURI::base().$fileURL;
                }
            }
            
        }
        
        return $image;
    }
    
	/**
     * Get an image from K2 item
     * @param array $uriParams
     */
    private function getK2Image($uriParams) {
        
        $image = "";
        
        if(!empty($uriParams["id"])) {
            $uriPath  = "media/k2/items/cache/";
            $k2Folder = JPATH_BASE.DIRECTORY_SEPARATOR."media".DIRECTORY_SEPARATOR."k2".DIRECTORY_SEPARATOR."items".DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR;
            $dir      = new DirectoryIterator($k2Folder);
            
            // Generate a file name
            $filename = md5("Image".(int)$uriParams["id"])."_M.jpg";
            
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    $k2File = $fileinfo->getFilename();
                    
                    if(strcmp($k2File, $filename) == 0) {
                        $image = JURI::base().$uriPath.$k2File;
                        break;
                    }
                }
            }
            
        }
        
        return $image;
    }
    
    private function getJoomShoppingImage($uriParams) {
        
        $image = "";
        
        if(!empty($uriParams["product_id"])) {
            
            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $query = $db->getQuery(true);
            
            $query
                ->select("image_name")
                ->from("#__jshopping_products_images")
                ->where("product_id=" . (int)$uriParams["product_id"])
                ->order("ordering");
              
            $db->setQuery($query, 0, 1);
            $imageName = $db->loadResult();
            
            if(!empty($imageName)) {
                $config = JSFactory::getConfig();
                $image  = $config->image_product_live_path."/".$imageName;
            }
            
            
        }
        
        return $image;
    }
    
    private function getHikaShopImage($uriParams) {
        
        $image    = "";
        
        if(!empty($uriParams["cid"])) {
            $db = JFactory::getDbo();
            /** @var $db JDatabaseMySQLi **/
            
            $qiery = $db->getQuery(true);
               
            $qiery
                ->select("#__hikashop_product.product_name, #__hikashop_product.product_page_title, #__hikashop_file.file_path")
                ->from("#__hikashop_product")
                ->join("LEFT", "#__hikashop_file ON #__hikashop_product.product_id = #__hikashop_file.file_ref_id")
                ->where("#__hikashop_product.product_id=" . (int)$uriParams["cid"]);
                  
            $db->setQuery($qiery, 0, 1);
            $result = $db->loadObject();
            
            if(!empty($result)) {
                
                // Get image
                $config       = hikashop_config();
                $uploadFolder = $config->get("uploadfolder");
                $image = JURI::base().$uploadFolder.$result->file_path;
            }
        }
        
        return $image;
    }
    
    private function getImage() {
        
        $uri        = JURI::getInstance();
        $router     = JFactory::getApplication()->getRouter();
        $uriParams  = $router->parse($uri);
        
    	static $result     = null;
    	if(!is_null($result)) {
    	    return $result;
    	}
    	
    	switch($this->currentOption) {
            case "com_content":
            	if(strcmp($this->currentView, "article") == 0) {
		            $result = $this->getContentImage($uriParams);
            	} 
            break;
            	
            case "com_k2":
                if(strcmp($this->currentView, "item") == 0) {
		            $result = $this->getK2Image($uriParams);
                }
                break;
                
            case "com_virtuemart":
                if(strcmp($this->currentView, "productdetails") == 0) {
                    $result = $this->getVirtuemartImage($uriParams);
                }
                break;
            
            case "com_jshopping":
                if(strcmp($this->currentTask, "view") == 0) {
                    $result = $this->getJoomShoppingImage($uriParams);
                }
                break;
                
            case "com_hikashop":
                if(strcmp($this->currentView, "product") == 0) {
                    $result = $this->getHikaShopImage($uriParams);
                }
                break;
                    
            default:
                $result = "";
                break;   
        }
        
        return $result;
        
    }
    
	/**
     * A method that make a long url to short url
     * 
     * @param string $link
     * @param array $params
     * @return string
     */
    private function getShortUrl($link){
        
        JLoader::register("ItpSharepointPluginShortUrl", dirname(__FILE__).DIRECTORY_SEPARATOR."shorturl.php");
        $options = array(
            "login"     => $this->params->get("shortener_login"),
            "api_key"   => $this->params->get("shortener_api_key"),
            "service"   => $this->params->get("shortener_service"),
        );
       
        
        try {
            
            $shortUrl 	= new ItpSharepointPluginShortUrl($link, $options);
            $shortLink  = $shortUrl->getUrl();
            
            // Get original link
            if(!$shortLink) {
                $shortLink = $link;
            } 
            
        } catch(Exception $e) {
            
            JLog::add($e->getMessage());
            
            // Get original link
            if(!$shortLink) {
                $shortLink = $link;
            }
                
        }
        
        return $shortLink;
            
    }
    
    /**
     * Generate a code for the extra buttons
     */
    private function getExtraButtons($params, $url, $title) {
        
        $html  = "";
        // Extra buttons
        for($i=1; $i < 6;$i++) {
            $btnName = "ebuttons" . $i;
            $extraButton = $params->get($btnName, "");
            if(!empty($extraButton)) {
                $extraButton = str_replace("{URL}", $url, $extraButton);
                $extraButton = str_replace("{TITLE}", $title, $extraButton);
                $html  .= $extraButton;
            }
        }
        
        return $html;
    }
    
    private function getTwitter($params, $url, $title){
        
        $html = "";
        if($params->get("twitterButton")) {
            
            $title  = htmlentities($title, ENT_QUOTES, "UTF-8");
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->twitterLocale = $params->get("twitterLanguage", "en");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->twitterLocale = JArrayHelper::getValue($locales, "twitter", "en");
            }
            
            $html = '
             	<div class="itp-sharepoint-tw">
                	<a href="https://twitter.com/share" class="twitter-share-button" data-url="' . $url . '" data-text="' . $title . '" data-via="' . $params->get("twitterName") . '" data-lang="' . $this->twitterLocale . '" data-size="' . $params->get("twitterSize") . '" data-related="' . $params->get("twitterRecommend") . '" data-hashtags="' . $params->get("twitterHashtag") . '" data-count="' . $params->get("twitterCounter") . '">Tweet</a>';
            
            if($params->get("load_twitter_library", 1)) {
                $html .= '<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
            }
            
            $html .='</div>';
        }
        
        return $html;
    }
    
    private function getGooglePlusOne($params, $url){
        
        $html = "";
        if($params->get("plusButton")) {
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->plusLocale = $params->get("plusLocale", "en");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->plusLocale = JArrayHelper::getValue($locales, "google", "en");
            }
            
            $html .= '<div class="itp-sharepoint-gone">';
            
            switch($params->get("plusRenderer")) {
                
                case 1:
                    $html .= $this->genGooglePlus($params, $url);
                    break;
                    
                default:
                    $html .= $this->genGooglePlusHTML5($params, $url);
                    break;
            }
            
            // Load the JavaScript asynchroning
    		if($params->get("loadGoogleJsLib")) {
      
                $html .= '<script>';
                $html .= ' window.___gcfg = {lang: "' . $this->plusLocale . '"};';
                
                $html .= '
                  (function() {
                    var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
                    po.src = "https://apis.google.com/js/plusone.js";
                    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
                  })();
                </script>';
    		}
            
          
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * 
     * Render the Google plus one in standart syntax
     * 
     * @param array $params
     * @param string $url
     */
    private function genGooglePlus($params, $url) {
        
        $annotation = "";
        if($params->get("plusAnnotation")) {
            $annotation = ' annotation="' . $params->get("plusAnnotation") . '"';
        }
        
        $html = '<g:plusone size="' . $params->get("plusType") . '" ' . $annotation . ' href="' . $url . '"></g:plusone>';
				
        return $html;
    }
    
    /**
     * 
     * Render the Google plus one in HTML5 syntax
     * 
     * @param array $params
     * @param string $url
     */
    private function genGooglePlusHTML5($params, $url) {
        
        $annotation = "";
        if($params->get("plusAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("plusAnnotation") . '"';
        }
        
        $html = '<div class="g-plusone" data-size="' . $params->get("plusType") . '" ' . $annotation . ' data-href="' . $url . '"></div>';
    				
        return $html;
    }
    
    
    private function getFacebookLike($params, $url){
        
        $html = "";
        if($params->get("facebookLikeButton")) {
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->fbLocale = $params->get("fbLocale", "en_US");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->fbLocale = JArrayHelper::getValue($locales, "facebook", "en_US");
            }
            
            // Faces
            $faces = (!$params->get("facebookLikeFaces")) ? "false" : "true";
            
            // Layout Styles
            $layout = $params->get("facebookLikeType", "button_count");
            if(strcmp("box_count", $layout)==0){
                $height = "80";
            } else {
                $height = "25";
            }
            
            // Generate code
            $html = '<div class="itp-sharepoint-fbl">';
            
            switch($params->get("facebookLikeRenderer")) {
                
                case 0: // iframe
                    $html .= $this->genFacebookLikeIframe($params, $url, $layout, $faces, $height);
                break;
                    
                case 1: // XFBML
                    $html .= $this->genFacebookLikeXfbml($params, $url, $layout, $faces, $height);
                break;
             
                default: // HTML5
                   $html .= $this->genFacebookLikeHtml5($params, $url, $layout, $faces, $height);
                break;
            }
            
            $html .="</div>";
        }
        
        return $html;
    }
    
    private function genFacebookLikeIframe($params, $url, $layout, $faces, $height) {
        
        $html = '
            <iframe src="//www.facebook.com/plugins/like.php?';
            
        $html .= 'href=' . rawurlencode($url) . '&amp;send=' . $params->get("facebookLikeSend",0). '&amp;locale=' . $this->fbLocale . '&amp;layout=' . $layout . '&amp;show_faces=' . $faces . '&amp;width=' . $params->get("facebookLikeWidth","450") . '&amp;action=' . $params->get("facebookLikeAction",'like') . '&amp;colorscheme=' . $params->get("facebookLikeColor",'light') . '&amp;height='.$height.'';
        if($params->get("facebookLikeFont")){
            $html .= "&amp;font=" . $params->get("facebookLikeFont");
        }
        if($params->get("facebookLikeAppId")){
            $html .= "&amp;appId=" . $params->get("facebookLikeAppId");
        }

        $html .= '" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:' . $params->get("facebookLikeWidth", "450") . 'px; height:' . $height . 'px;" allowTransparency="true"></iframe>';
            
        return $html;
    }
    
    private function genFacebookLikeXfbml($params, $url, $layout, $faces, $height) {
        
        $html = "";
                
        if($params->get("facebookRootDiv",1)) {
            $html .= '<div id="fb-root"></div>';
        }
        
        if($params->get("facebookLoadJsLib", 1)) {
           $appId = "";
           if($params->get("facebookLikeAppId")){
               $appId = '&amp;appId=' . $params->get("facebookLikeAppId"); 
           }
            
           $html .= ' 
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/all.js#xfbml=1'.$appId.'";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';
           
        }
        
        $html .= '
        <fb:like 
        href="' . $url . '" 
        layout="' . $layout . '" 
        show_faces="' . $faces . '" 
        width="' . $params->get("facebookLikeWidth","450") . '" 
        colorscheme="' . $params->get("facebookLikeColor","light") . '"
        send="' . $params->get("facebookLikeSend",0). '" 
        action="' . $params->get("facebookLikeAction",'like') . '" ';

        if($params->get("facebookLikeFont")){
            $html .= 'font="' . $params->get("facebookLikeFont") . '"';
        }
        $html .= '></fb:like>
        ';
        
        return $html;
    }
    
    private function genFacebookLikeHtml5($params, $url, $layout, $faces, $height) {
        
        $html = '';
                
        if($params->get("facebookRootDiv",1)) {
            $html .= '<div id="fb-root"></div>';
        }
                
        if($params->get("facebookLoadJsLib", 1)) {
           $appId = "";
           if($params->get("facebookLikeAppId")){
                $appId = '&amp;appId=' . $params->get("facebookLikeAppId"); 
            }
                
           $html .= ' 
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/all.js#xfbml=1'.$appId.'";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';
               
        }
        
        $html .= '
            <div 
            class="fb-like" 
            data-href="' . $url . '" 
            data-send="' . $params->get("facebookLikeSend",0). '" 
            data-layout="'.$layout.'" 
            data-width="' . $params->get("facebookLikeWidth","450") . '" 
            data-show-faces="' . $faces . '" 
            data-colorscheme="' . $params->get("facebookLikeColor","light") . '" 
            data-action="' . $params->get("facebookLikeAction",'like') . '"';
                
        if($params->get("facebookLikeFont")){
            $html .= ' data-font="' . $params->get("facebookLikeFont") . '" ';
        }
        
        $html .= '></div>';
        
        return $html;
        
    }
    
    private function getLinkedIn($params, $url){
        
        $html = "";
        if($params->get("linkedInButton")) {
            
            $html = '
            <div class="itp-sharepoint-lin">';
            
            if($params->get("load_linkedin_library", 1)) {
                $html .= '<script src="//platform.linkedin.com/in.js"></script>';
            }
            
            $html .= '<script type="IN/Share" data-url="' . $url . '" data-counter="' . $params->get("linkedInType", 'right'). '"></script>
            </div>
            ';

        }
        
        return $html;
    }
    
    private function getReddit($params, $url, $title){
        
        $html = "";
        if($params->get("redditButton")) {
            
            $title  = htmlentities($title, ENT_QUOTES, "UTF-8");
            
            $html .= '<div class="itp-sharepoint-reddit">';
            $redditType = $params->get("redditType");
            
            $jsButtons = range(1, 9);
            
            if(in_array($redditType, $jsButtons) ) {
                $html .='<script>
  reddit_url = "'. $url . '";
  reddit_title = "'.$title.'";
  reddit_bgcolor = "'.$params->get("redditBgColor").'";
  reddit_bordercolor = "'.$params->get("redditBorderColor").'";
  reddit_newwindow = "'.$params->get("redditNewTab").'";
</script>';
            }
            switch($redditType) {
                
                case 1:
                    $html .='<script src="//www.reddit.com/static/button/button1.js"></script>';
                    break;
                case 2:
                    $html .='<script src="//www.reddit.com/static/button/button2.js"></script>';
                    break;
                case 3:
                    $html .='<script src="//www.reddit.com/static/button/button3.js"></script>';
                    break;
                case 4:
                    $html .='<script src="//www.reddit.com/buttonlite.js?i=0"></script>';
                    break;
                case 5:
                    $html .='<script src="//www.reddit.com/buttonlite.js?i=1"></script>';
                    break;
                case 6:
                    $html .='<script src="//www.reddit.com/buttonlite.js?i=2"></script>';
                    break;
                case 7:
                    $html .='<script src="//www.reddit.com/buttonlite.js?i=3"></script>';
                    break;
                case 8:
                    $html .='<script src="//www.reddit.com/buttonlite.js?i=4"></script>';
                    break;
                case 9:
                    $html .='<script src="//www.reddit.com/buttonlite.js?i=5"></script>';
                    break;
                case 10:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit6.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;
                case 11:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit1.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 12:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit2.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 13:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit3.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 14:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit4.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 15:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit5.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 16:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit8.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 17:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit9.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 18:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit10.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 19:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit11.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 20:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit12.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 21:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit13.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                case 22:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url='. $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit14.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;   
                                    
                default:
                    $html .='<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit7.gif" alt="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SUBMIT_REDDIT").'" border="0" /> </a>';
                    break;
            }
            
            $html .='</div>';
                
        }
        
        return $html;
    }
    
    private function getTumblr($params, $url){
            
        $html = "";
        if($params->get("tumblrButton")) {
            
            $html .= '<div class="itp-sharepoint-tbr">';
            
            if($params->get("loadTumblrJsLib")) {
                $html .= '<script src="//platform.tumblr.com/v1/share.js"></script>';
            }
            
            switch($params->get("tumblrType")) {
                
                case 1:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:62px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;

                case 2:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;
                case 3:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;
                case 4:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1T.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;
                case 5:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:62px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2T.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;
                case 6:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3T.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;
                case 7:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4T.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;   
                                    
                default:
                    $html .='<a href="http://www.tumblr.com/share" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1.png\') top left no-repeat transparent;">'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_SHARE_THUMBLR").'</a>';
                    break;
            }
            
            $html .='</div>';
        }
        
        return $html;
    }
    
    private function getPinterest($params, $url, $title){
        
        $html = "";
        if($params->get("pinterestButton")) {
            
            $image      = $this->getImage();
            
            $media = "";
            if(!empty($image)) {
                $media = "&amp;media=" . rawurlencode($image);
            }
            
            $html .= '<div class="itp-sharepoint-pinterest">';
            $html .= '<a href="http://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . $media. '&amp;description=' . rawurlencode($title) . '" class="pin-it-button" count-layout="'.$params->get("pinterestType", "horizontal").'"><img border="0" src="//assets.pinterest.com/images/PinExt.png" title="'.JText::_("PLG_SYSTEM_ITPSHAREPOINT_PIN_IT").'" /></a>';
            $html .= '</div>';
            
            // Load the JS library
            if($params->get("loadPinterestJsLib")) {
                $html .= '<script src="//assets.pinterest.com/js/pinit.js"></script>';
            }
        }
        
        return $html;
    }
    
    private function getStumbpleUpon($params, $url){
        
        $html = "";
        if($params->get("stumbleButton")) {
            
            $html = "
            <div class=\"itp-sharepoint-su\">
            <su:badge layout='" . $params->get("stumbleType", 1). "' location='".$url."'></su:badge>
            </div>
            
            <script>
              (function() {
                var li = document.createElement('script'); li.type = 'text/javascript'; li.async = true;
                li.src = ('https:' == document.location.protocol ? 'https:' : 'http:') + '//platform.stumbleupon.com/1/widgets.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(li, s);
              })();
            </script>
                ";
        }
        
        return $html;
    }
    
    private function getBuffer($params, $url, $title){
        
        $html = "";
        if($params->get("bufferButton")) {
            
            $title  = htmlentities($title, ENT_QUOTES, "UTF-8");
            
            $image   = $this->getImage();
            
            $picture = "";
            if(!empty($image)) {
                $picture = 'data-picture="'.$image.'"';
            }
            
            $html = '
            <div class="itp-sharepoint-buffer">
            <a href="http://bufferapp.com/add" class="buffer-add-button" '.$picture.' data-text="' . $title . '" data-url="'.$url.'" data-count="'.$params->get("bufferType").'" data-via="'.$params->get("bufferTwitterName").'">Buffer</a><script src="//static.bufferapp.com/js/button.js"></script>
            </div>
            ';
        }
        
        return $html;
    }
    
    private function getButtonsLocales($locale) {
        
         // Default locales
        $result = array(
            "twitter"     => "en",
        	"facebook"    => "en_US",
        	"google"      => "en"
        );
        
        // The locales map
        $locales = array (
            "en_US" => array(
                "twitter"     => "en",
            	"facebook"    => "en_US",
            	"google"      => "en"
            ),
            "en_GB" => array(
                "twitter"     => "en",
            	"facebook"    => "en_GB",
            	"google"      => "en_GB"
            ),
            "th_TH" => array(
                "twitter"     => "th",
            	"facebook"    => "th_TH",
            	"google"      => "th"
            ),
            "ms_MY" => array(
                "twitter"     => "msa",
            	"facebook"    => "ms_MY",
            	"google"      => "ms"
            ),
            "tr_TR" => array(
                "twitter"     => "tr",
            	"facebook"    => "tr_TR",
            	"google"      => "tr"
            ),
            "hi_IN" => array(
                "twitter"     => "hi",
            	"facebook"    => "hi_IN",
            	"google"      => "hi"
            ),
            "tl_PH" => array(
                "twitter"     => "fil",
            	"facebook"    => "tl_PH",
            	"google"      => "fil"
            ),
            "zh_CN" => array(
                "twitter"     => "zh-cn",
            	"facebook"    => "zh_CN",
            	"google"      => "zh"
            ),
            "ko_KR" => array(
                "twitter"     => "ko",
            	"facebook"    => "ko_KR",
            	"google"      => "ko"
            ),
            "it_IT" => array(
                "twitter"     => "it",
            	"facebook"    => "it_IT",
            	"google"      => "it"
            ),
            "da_DK" => array(
                "twitter"     => "da",
            	"facebook"    => "da_DK",
            	"google"      => "da"
            ),
            "fr_FR" => array(
                "twitter"     => "fr",
            	"facebook"    => "fr_FR",
            	"google"      => "fr"
            ),
            "pl_PL" => array(
                "twitter"     => "pl",
            	"facebook"    => "pl_PL",
            	"google"      => "pl"
            ),
            "nl_NL" => array(
                "twitter"     => "nl",
            	"facebook"    => "nl_NL",
            	"google"      => "nl"
            ),
            "id_ID" => array(
                "twitter"     => "in",
            	"facebook"    => "nl_NL",
            	"google"      => "in"
            ),
            "hu_HU" => array(
                "twitter"     => "hu",
            	"facebook"    => "hu_HU",
            	"google"      => "hu"
            ),
            "fi_FI" => array(
                "twitter"     => "fi",
            	"facebook"    => "fi_FI",
            	"google"      => "fi"
            ),
            "es_ES" => array(
                "twitter"     => "es",
            	"facebook"    => "es_ES",
            	"google"      => "es"
            ),
            "ja_JP" => array(
                "twitter"     => "ja",
            	"facebook"    => "ja_JP",
            	"google"      => "ja"
            ),
            "nn_NO" => array(
                "twitter"     => "no",
            	"facebook"    => "nn_NO",
            	"google"      => "no"
            ),
            "ru_RU" => array(
                "twitter"     => "ru",
            	"facebook"    => "ru_RU",
            	"google"      => "ru"
            ),
            "pt_PT" => array(
                "twitter"     => "pt",
            	"facebook"    => "pt_PT",
            	"google"      => "pt"
            ),
            "pt_BR" => array(
                "twitter"     => "pt",
            	"facebook"    => "pt_BR",
            	"google"      => "pt"
            ),
            "sv_SE" => array(
                "twitter"     => "sv",
            	"facebook"    => "sv_SE",
            	"google"      => "sv"
            ),
            "zh_HK" => array(
                "twitter"     => "zh-tw",
            	"facebook"    => "zh_HK",
            	"google"      => "zh_HK"
            ),
            "zh_TW" => array(
                "twitter"     => "zh-tw",
            	"facebook"    => "zh_TW",
            	"google"      => "zh_TW"
            ),
            "de_DE" => array(
                "twitter"     => "de",
            	"facebook"    => "de_DE",
            	"google"      => "de"
            ),
            "bg_BG" => array(
                "twitter"     => "en",
            	"facebook"    => "bg_BG",
            	"google"      => "bg"
            ),
            
        );
        
        if(isset($locales[$locale])) {
            $result = $locales[$locale];
        }
        
        return $result;
        
    }
    
    private function getGoogleShare($params, $url){
        
        $html = "";
        if($params->get("gsButton")) {
            
            // Get locale code
            if(!$params->get("dynamicLocale")) {
                $this->gshareLocale = $params->get("gsLocale", "en");
            } else {
                $locales = $this->getButtonsLocales($this->locale); 
                $this->gshareLocale = JArrayHelper::getValue($locales, "google", "en");
            }
            
            $html .= '<div class="itp-sharepoint-gshare">';
            
            switch($params->get("gsRenderer")) {
                
                case 1:
                    $html .= $this->genGoogleShare($params, $url);
                    break;
                    
                default:
                    $html .= $this->genGoogleShareHTML5($params, $url);
                    break;
            }
            
            // Load the JavaScript asynchroning
        	if($params->get("loadGoogleJsLib")) {
        
                $html .= '<script>';
                if($this->gshareLocale) {
                   $html .= ' window.___gcfg = {lang: "'.$this->gshareLocale.'"}; ';
                }
                
                $html .= '
                  (function() {
                    var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
                    po.src = "https://apis.google.com/js/plusone.js";
                    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
                  })();
                </script>';
            }
          
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * 
     * Render the Google Share in standart syntax
     * 
     * @param array  $params
     * @param string $url
     * @param string $language
     */
    private function genGoogleShare($params, $url) {
        
        $annotation = "";
        if($params->get("gsAnnotation")) {
            $annotation = ' annotation="' . $params->get("gsAnnotation") . '"';
        }
        
        $size = "";
        if($params->get("gsAnnotation") != "vertical-bubble") {
            $size = ' height="' . $params->get("gsType") . '" ';
        }
        
        $html = '<g:plus action="share" ' . $annotation . $size . ' href="' . $url . '"></g:plus>';
        
        return $html;
    }
    
    /**
     * 
     * Render the Google Share in HTML5 syntax
     * 
     * @param array $params
     * @param string $url
     * @param string $language
     */
    private function genGoogleShareHTML5($params, $url) {
        
        $annotation = "";
        if($params->get("gsAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("gsAnnotation") . '"';
        }
        
        $size = "";
        if($params->get("gsAnnotation") != "vertical-bubble") {
            $size = ' data-height="' . $params->get("gsType") . '" ';
        }
        
        $html = '<div class="g-plus" data-action="share" ' . $annotation . $size . ' data-href="' . $url . '"></div>';

        return $html;
    }
    
    
}