<?php
/**
 * @package         ITPSharePoint
 * @subpackage      Plugins
 * @copyright       Copyright (C) 2014 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * ITPSharePoint Plugin
 *
 * @package        ITPrism Plugins
 * @subpackage     ITPSharePoint
 */
class plgSystemITPSharePoint extends JPlugin
{
    /**
     * A JRegistry object holding the parameters for the plugin
     *
     * @var    Joomla\Registry\Registry
     * @since  1.5
     */
    public $params = null;

    private $locale = "en_US";
    private $fbLocale = "en_US";
    private $plusLocale = "en";
    private $gshareLocale = "en";
    private $twitterLocale = "en";
    private $currentView = "";
    private $currentTask = "";
    private $currentOption = "";
    private $currentLayout = "";

    private $imgPattern = '/src="([^"]*)"/i';

    /**
     * Clean meta data.
     *
     * @return void
     */
    public function onBeforeCompileHead()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if ($app->isAdmin()) {
            return;
        }

        $doc = JFactory::getDocument();
        /** @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp("html", $docType) != 0) {
            return;
        }

        $this->currentView   = $app->input->get("view");
        $this->currentOption = $app->input->get("option");

        if (!$this->isAllowed()) {
            return;
        }

        if ($this->params->get("loadCss")) {
            $doc->addStyleSheet(JUri::root() . "plugins/system/itpsharepoint/style.css");
        }

        if ($this->params->get("enable-floating", 0)) { // Enable floating box
            $this->prepareFloatingBox();
        }

        // Remove the indicator in the site description.
        // That prevent putting the code into the meta tags
        $desc = $doc->getDescription();
        if (false !== strpos($desc, "{itpsharepoint}")) {
            $desc = str_replace("{itpsharepoint}", "", $desc);
            $doc->setDescription($desc);
        }

    }

    /**
     * Add social buttons into the article.
     *
     * @return void
     */
    public function onAfterRender()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if ($app->isAdmin()) {
            return;
        }

        $doc = JFactory::getDocument();
        /** @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp("html", $docType) != 0) {
            return;
        }

        $this->currentView   = $app->input->get("view");
        $this->currentTask   = $app->input->get("task");
        $this->currentOption = $app->input->get("option");
        $this->currentLayout = $app->input->get("layout");

        $buffer = JResponse::getBody();
        if (!$this->isAllowed()) {

            // Clear content if the indicator exists.
            if (false !== strpos($buffer, "{itpsharepoint}")) {
                $buffer  = str_replace("{itpsharepoint}", "", $buffer);
                JResponse::setBody($buffer);
            }
            
            return;
        }

        // Load language file
        $this->loadLanguage();

        // Get locale code automatically
        if ($this->params->get("dynamicLocale", 0)) {
            $lang         = JFactory::getLanguage();
            $locale       = $lang->getTag();
            $this->locale = str_replace("-", "_", $locale);
        }

        // Generate the buttons

        if (false !== strpos($buffer, "{itpsharepoint}")) {

            // Get content
            $content = $this->getContent();

            if ($this->params->get("enable-floating", 0)) { // Enable floating box
                $content = '<div class="itp-sharepoint itp-sharepoint-floating itp-sharepoint-fstyle" id="itp-sharepoint">' . $content . '</div>';
            } else {
                $content = '<div class="itp-sharepoint itp-sharepoint-left">'.$content.'</div>';
            }

            // Include content
            if (!empty($content)) {
                $buffer  = str_replace("{itpsharepoint}", $content, $buffer);
            } else {
                $buffer  = str_replace("{itpsharepoint}", "", $buffer);
            }

        }

        // Put namespace in the HTML element
        if ($this->params->get("facebookPutNamespace", 0) and (1 == $this->params->get("facebookLikeRenderer", 2))) {
            $buffer = $this->putNamespaces($buffer);
        }

        JResponse::setBody($buffer);
    }

    /**
     * Put namespace schema to the HTML tag if rendering by XFBML.
     *
     * @param string $buffer Output buffer
     *
     * @return string
     */
    private function putNamespaces($buffer)
    {
        $pattern = "/<html.*>/i";
        if (preg_match($pattern, $buffer, $matches)) {
            if (false === strpos($matches[0], 'http://ogp.me/ns/fb#')) {
                $string = ' xmlns:fb="http://ogp.me/ns/fb#" ';

                $newHtmlAttr = '<html ' . $string;
                $buffer      = str_replace("<html", $newHtmlAttr, $buffer);
            }
        }

        return $buffer;
    }

    private function isAllowed()
    {
        $result = false;

        // Parse the views where you want to put the buttons
        $displayOn = JString::trim($this->params->get("displayOn"));

        if (!empty($displayOn)) {
            $displayOn      = str_replace("'", '"', $displayOn);
            $displayOn      = explode(";", $displayOn);

            foreach ($displayOn as $item) {
                $item = json_decode(JString::trim($item), true);

                if (!empty($item[0]) and $this->currentOption == $item[0]) { // Validation by option

                    $result = true;

                    if (!empty($item[1]) and $this->currentView != $item[1]) { // Validation by view
                        $result = false;
                    }
                }

            }
        }

        return $result;
    }

    /**
     * Generate content.
     * 
     * @return  string      Returns html code or empty string.
     */
    private function getContent()
    {
        $url   = JURI::getInstance()->toString();
        $title = JFactory::getDocument()->getTitle();

        // Filter the URL
        $filter = JFilterInput::getInstance();
        $url    = $filter->clean($url);

        // Convert the url to short one
        if ($this->params->get("shortener_service")) {
            $url = $this->getShortUrl($url);
        }

        // Start buttons box
        $html = "";

        $html .= $this->getTwitter($this->params, $url, $title);
        $html .= $this->getStumbpleUpon($this->params, $url);
        $html .= $this->getLinkedIn($this->params, $url);
        $html .= $this->getBuffer($this->params, $url, $title);
        $html .= $this->getPinterest($this->params, $url, $title);
        $html .= $this->getReddit($this->params, $url, $title);
        $html .= $this->getTumblr($this->params, $url);
        $html .= $this->getGooglePlusOne($this->params, $url);
        $html .= $this->getGoogleShare($this->params, $url);
        $html .= $this->getFacebookLike($this->params, $url);

        // Gets extra buttons
        $html .= $this->getExtraButtons($this->params, $url, $title);

        return $html;
    }


    /**
     * A method that make a long url to short url
     *
     * @param string $link
     *
     * @return string
     */
    private function getShortUrl($link)
    {
        JLoader::register("ItpSharepointPluginShortUrl", dirname(__FILE__) . DIRECTORY_SEPARATOR . "shorturl.php");
        $options = array(
            "login"   => $this->params->get("shortener_login"),
            "api_key" => $this->params->get("shortener_api_key"),
            "service" => $this->params->get("shortener_service"),
        );

        $shortLink = "";

        try {

            $shortUrl  = new ItpFloatingSharePluginShortUrl($link, $options);
            $shortLink = $shortUrl->getUrl();

            // Get original link
            if (!$shortLink) {
                $shortLink = $link;
            }

        } catch (Exception $e) {

            JLog::add($e->getMessage());

            // Get original link
            if (!$shortLink) {
                $shortLink = $link;
            }

        }

        return $shortLink;

    }

    /**
     * Generate a code for the extra buttons.
     * 
     * @param Joomla\Registry\Registry $params
     * @param string $url
     * @param string $title
     *         
     * @return string
     */
    private function getExtraButtons($params, $url, $title)
    {
        $html = "";
        // Extra buttons
        for ($i = 1; $i < 6; $i++) {
            $btnName     = "ebuttons" . $i;
            $extraButton = $params->get($btnName, "");
            if (!empty($extraButton)) {
                $extraButton = str_replace("{URL}", $url, $extraButton);
                $extraButton = str_replace("{TITLE}", $title, $extraButton);
                $html .= $extraButton;
            }
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     * @param string    $title
     *
     * @return string
     */
    private function getTwitter($params, $url, $title)
    {

        $html = "";
        if ($params->get("twitterButton")) {

            $title = htmlentities($title, ENT_QUOTES, "UTF-8");

            // Get locale code
            if (!$params->get("dynamicLocale")) {
                $this->twitterLocale = $params->get("twitterLanguage", "en");
            } else {
                $locales             = $this->getButtonsLocales($this->locale);
                $this->twitterLocale = JArrayHelper::getValue($locales, "twitter", "en");
            }

            $html = '
             	<div class="itp-sharepoint-tw">
                	<a href="https://twitter.com/share" class="twitter-share-button" data-url="' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8'))  . '" data-text="' . $title . '" data-via="' . $params->get("twitterName") . '" data-lang="' . $this->twitterLocale . '" data-size="' . $params->get("twitterSize") . '" data-related="' . $params->get("twitterRecommend") . '" data-hashtags="' . $params->get("twitterHashtag") . '" data-count="' . $params->get("twitterCounter") . '">Tweet</a>';

            if ($params->get("load_twitter_library", 1)) {
                $html .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>";
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getGooglePlusOne($params, $url)
    {
        $html = "";
        if ($params->get("plusButton")) {

            // Get locale code
            if (!$params->get("dynamicLocale")) {
                $this->plusLocale = $params->get("plusLocale", "en");
            } else {
                $locales          = $this->getButtonsLocales($this->locale);
                $this->plusLocale = JArrayHelper::getValue($locales, "google", "en");
            }

            $html .= '<div class="itp-sharepoint-gone">';

            switch ($params->get("plusRenderer")) {

                case 1:
                    $html .= $this->genGooglePlus($params, $url);
                    break;

                default:
                    $html .= $this->genGooglePlusHTML5($params, $url);
                    break;
            }

            // Load the JavaScript asynchroning
            if ($params->get("loadGoogleJsLib")) {

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
     * Render the Google plus one in standart syntax.
     *
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function genGooglePlus($params, $url)
    {

        $annotation = "";
        if ($params->get("plusAnnotation")) {
            $annotation = ' annotation="' . $params->get("plusAnnotation") . '"';
        }

        $html = '<g:plusone size="' . $params->get("plusType") . '" ' . $annotation . ' href="' . $url . '"></g:plusone>';

        return $html;
    }

    /**
     * Render the Google plus one in HTML5 syntax.
     *
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function genGooglePlusHTML5($params, $url)
    {
        $annotation = "";
        if ($params->get("plusAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("plusAnnotation") . '"';
        }

        $html = '<div class="g-plusone" data-size="' . $params->get("plusType") . '" ' . $annotation . ' data-href="' . $url . '"></div>';

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getFacebookLike($params, $url)
    {
        $html = "";
        if ($params->get("facebookLikeButton")) {

            // Get locale code
            if (!$params->get("dynamicLocale")) {
                $this->fbLocale = $params->get("fbLocale", "en_US");
            } else {
                $locales        = $this->getButtonsLocales($this->locale);
                $this->fbLocale = JArrayHelper::getValue($locales, "facebook", "en_US");
            }

            // Faces
            $faces = (!$params->get("facebookLikeFaces")) ? "false" : "true";

            // Layout Styles
            $layout = $params->get("facebookLikeType", "button_count");
            if (strcmp("box_count", $layout) == 0) {
                $height = "80";
            } else {
                $height = "25";
            }

            // Generate code
            $html = '<div class="itp-sharepoint-fbl">';

            switch ($params->get("facebookLikeRenderer")) {

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

            $html .= "</div>";
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     * @param string    $layout
     * @param string    $faces
     * @param int       $height
     *
     * @return string
     */
    private function genFacebookLikeIframe($params, $url, $layout, $faces, $height)
    {
        $html = '
            <iframe src="//www.facebook.com/plugins/like.php?';

        $html .= 'href=' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . '&amp;send=' . $params->get("facebookLikeSend", 0) . '&amp;locale=' . $this->fbLocale . '&amp;layout=' . $layout . '&amp;show_faces=' . $faces . '&amp;width=' . $params->get("facebookLikeWidth", "450") . '&amp;action=' . $params->get("facebookLikeAction", 'like') . '&amp;colorscheme=' . $params->get("facebookLikeColor", 'light') . '&amp;height=' . $height . '';
        if ($params->get("facebookLikeFont")) {
            $html .= "&amp;font=" . $params->get("facebookLikeFont");
        }
        if ($params->get("facebookLikeAppId")) {
            $html .= "&amp;appId=" . $params->get("facebookLikeAppId");
        }

        if ($params->get("facebookKidDirectedSite")) {
            $html .= '&amp;kid_directed_site=true';
        }

        $html .= '" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:' . $params->get("facebookLikeWidth", "450") . 'px; height:' . $height . 'px;" allowTransparency="true"></iframe>';

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     * @param string    $layout
     * @param string    $faces
     *
     * @return string
     */
    private function genFacebookLikeXfbml($params, $url, $layout, $faces)
    {
        $html = "";

        if ($params->get("facebookRootDiv", 1)) {
            $html .= '<div id="fb-root"></div>';
        }

        if ($params->get("facebookLoadJsLib", 1)) {
            $appId = "";
            if ($params->get("facebookLikeAppId")) {
                $appId = '&amp;appId=' . $params->get("facebookLikeAppId");
            }

            $html .= '
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/sdk.js#xfbml=1&version=v2.0' . $appId . '";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';

        }

        $html .= '
        <fb:like 
        href="' . $url . '" 
        layout="' . $layout . '" 
        show_faces="' . $faces . '" 
        width="' . $params->get("facebookLikeWidth", "450") . '"
        colorscheme="' . $params->get("facebookLikeColor", "light") . '"
        share="' . $params->get("facebookLikeShare", 0) . '"
        action="' . $params->get("facebookLikeAction", 'like') . '" ';

        if ($params->get("facebookLikeFont")) {
            $html .= 'font="' . $params->get("facebookLikeFont") . '"';
        }

        if ($params->get("facebookKidDirectedSite")) {
            $html .= ' kid_directed_site="true"';
        }

        $html .= '></fb:like>
        ';

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     * @param string    $layout
     * @param string    $faces
     *
     * @return string
     */
    private function genFacebookLikeHtml5($params, $url, $layout, $faces)
    {
        $html = '';

        if ($params->get("facebookRootDiv", 1)) {
            $html .= '<div id="fb-root"></div>';
        }

        if ($params->get("facebookLoadJsLib", 1)) {
            $appId = "";
            if ($params->get("facebookLikeAppId")) {
                $appId = '&amp;appId=' . $params->get("facebookLikeAppId");
            }

            $html .= '
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/' . $this->fbLocale . '/sdk.js#xfbml=1&version=v2.0' . $appId . '";
  fjs.parentNode.insertBefore(js, fjs);
}(document, \'script\', \'facebook-jssdk\'));</script>';

        }

        $html .= '
            <div 
            class="fb-like" 
            data-href="' . $url . '" 
            data-share="' . $params->get("facebookLikeShare", 0) . '"
            data-layout="' . $layout . '"
            data-width="' . $params->get("facebookLikeWidth", "450") . '"
            data-show-faces="' . $faces . '" 
            data-colorscheme="' . $params->get("facebookLikeColor", "light") . '"
            data-action="' . $params->get("facebookLikeAction", 'like') . '"';

        if ($params->get("facebookLikeFont")) {
            $html .= ' data-font="' . $params->get("facebookLikeFont") . '" ';
        }

        if ($params->get("facebookKidDirectedSite")) {
            $html .= ' data-kid-directed-site="true"';
        }

        $html .= '></div>';

        return $html;

    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getLinkedIn($params, $url)
    {
        $html = "";
        if ($params->get("linkedInButton")) {

            // Get locale code
            if (!$params->get("dynamicLocale")) {
                $locale = $params->get("linkedInLocale", "en_US");
            } else {
                $locale = $this->locale;
            }

            $html = '<div class="itp-sharepoint-lin">';

            if ($params->get("load_linkedin_library", 1)) {
                $html .= '<script src="//platform.linkedin.com/in.js">lang: ' . $locale . '</script>';
            }

            $html .= '<script type="IN/Share" data-url="' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8'))  . '" data-counter="' . $params->get("linkedInType", 'right') . '"></script>
            </div>
            ';
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     * @param string    $title
     *
     * @return string
     */
    private function getReddit($params, $url, $title)
    {
        $html = "";
        if ($params->get("redditButton")) {

            $title = htmlentities($title, ENT_QUOTES, "UTF-8");

            $html .= '<div class="itp-sharepoint-reddit">';
            $redditType = $params->get("redditType");

            $jsButtons = range(1, 9);

            if (in_array($redditType, $jsButtons)) {
                $html .= '<script>
  reddit_url = "' . $url . '";
  reddit_title = "' . $title . '";
  reddit_bgcolor = "' . $params->get("redditBgColor") . '";
  reddit_bordercolor = "' . $params->get("redditBorderColor") . '";
  reddit_newwindow = "' . $params->get("redditNewTab") . '";
</script>';
            }

            switch ($redditType) {

                case 1:
                    $html .= '<script src="//www.reddit.com/static/button/button1.js"></script>';
                    break;
                case 2:
                    $html .= '<script src="//www.reddit.com/static/button/button2.js"></script>';
                    break;
                case 3:
                    $html .= '<script src="//www.reddit.com/static/button/button3.js"></script>';
                    break;
                case 4:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=0"></script>';
                    break;
                case 5:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=1"></script>';
                    break;
                case 6:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=2"></script>';
                    break;
                case 7:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=3"></script>';
                    break;
                case 8:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=4"></script>';
                    break;
                case 9:
                    $html .= '<script src="//www.reddit.com/buttonlite.js?i=5"></script>';
                    break;
                case 10:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit6.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 11:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit1.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 12:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit2.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 13:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit3.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 14:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit4.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 15:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit5.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 16:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit8.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 17:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit9.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 18:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit10.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 19:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit11.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 20:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit12.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 21:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit13.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
                case 22:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit14.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;

                default:
                    $html .= '<a href="http://www.reddit.com/submit" onclick="window.location = \'http://www.reddit.com/submit?url=' . $url . '\'; return false"> <img src="//www.reddit.com/static/spreddit7.gif" alt="' . JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SUBMIT_REDDIT") . '" border="0" /> </a>';
                    break;
            }
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     *
     * @return string
     */
    private function getTumblr($params)
    {
        $html = "";
        if ($params->get("tumblrButton")) {

            $html .= '<div class="itp-sharepoint-tbr">';

            if ($params->get("loadTumblrJsLib")) {
                $html .= '<script src="//platform.tumblr.com/v1/share.js"></script>';
            }

            $thumlrTitle = JText::_("PLG_CONTENT_ITPFLOATINGSHARE_SHARE_THUMBLR");

            switch ($params->get("tumblrType")) {

                case 1:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:62px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 2:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 3:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 4:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 5:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:62px; height:20px; background:url(\'//platform.tumblr.com/v1/share_2T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 6:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:129px; height:20px; background:url(\'//platform.tumblr.com/v1/share_3T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
                case 7:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:20px; height:20px; background:url(\'//platform.tumblr.com/v1/share_4T.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;

                default:
                    $html .= '<a href="http://www.tumblr.com/share" title="' . $thumlrTitle . '" style="display:inline-block; text-indent:-9999px; overflow:hidden; width:81px; height:20px; background:url(\'//platform.tumblr.com/v1/share_1.png\') top left no-repeat transparent;">' . $thumlrTitle . '</a>';
                    break;
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     * @param string    $title
     *
     * @return string
     */
    private function getPinterest($params, $url, $title)
    {
        $html = "";
        if ($params->get("pinterestButton")) {

            $html .= '<div class="itp-sharepoint-pinterest">';

            if (strcmp("one", $this->params->get('pinterestImages', "one")) == 0) {

                $html .= '<a href="//pinterest.com/pin/create/button/?url=' . rawurldecode(html_entity_decode($url, ENT_COMPAT, 'UTF-8')) . '&amp;description=' . rawurlencode($title) . '" data-pin-do="buttonPin" data-pin-config="' . $params->get("pinterestType", "beside") . '"><img src="//assets.pinterest.com/images/pidgets/pin_it_button.png" /></a>';
            } else {
                $html .= '<a href="//pinterest.com/pin/create/button/" data-pin-do="buttonBookmark" ><img src="//assets.pinterest.com/images/pidgets/pin_it_button.png" /></a>';
            }

            // Load the JS library
            if ($params->get("loadPinterestJsLib")) {
                $html .= '<script type="text/javascript" async src="//assets.pinterest.com/js/pinit.js"></script>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getStumbpleUpon($params, $url)
    {
        $html = "";
        if ($params->get("stumbleButton")) {

            $html = "
            <div class=\"itp-sharepoint-su\">
            <su:badge layout='" . $params->get("stumbleType", 1) . "' location='" . $url . "'></su:badge>
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

    /**
     * @param Joomla\Registry\Registry $params
     * @param string $url
     * @param string $title
     *
     * @return string
     */
    private function getBuffer($params, $url, $title)
    {
        $html = "";
        if ($params->get("bufferButton")) {

            $title = htmlentities($title, ENT_QUOTES, "UTF-8");

            $html = '
            <div class="itp-sharepoint-buffer">
            <a href="http://bufferapp.com/add" class="buffer-add-button" data-text="' . $title . '" data-url="' . html_entity_decode($url, ENT_COMPAT, 'UTF-8')  . '" data-count="' . $params->get("bufferType") . '" data-via="' . $params->get("bufferTwitterName") . '">Buffer</a><script src="//static.bufferapp.com/js/button.js"></script>
            </div>
            ';
        }

        return $html;
    }

    private function getButtonsLocales($locale)
    {
        // Default locales
        $result = array(
            "twitter"  => "en",
            "facebook" => "en_US",
            "google"   => "en"
        );

        // The locales map
        $locales = array(
            "en_US" => array(
                "twitter"  => "en",
                "facebook" => "en_US",
                "google"   => "en"
            ),
            "en_GB" => array(
                "twitter"  => "en",
                "facebook" => "en_GB",
                "google"   => "en_GB"
            ),
            "th_TH" => array(
                "twitter"  => "th",
                "facebook" => "th_TH",
                "google"   => "th"
            ),
            "ms_MY" => array(
                "twitter"  => "msa",
                "facebook" => "ms_MY",
                "google"   => "ms"
            ),
            "tr_TR" => array(
                "twitter"  => "tr",
                "facebook" => "tr_TR",
                "google"   => "tr"
            ),
            "hi_IN" => array(
                "twitter"  => "hi",
                "facebook" => "hi_IN",
                "google"   => "hi"
            ),
            "tl_PH" => array(
                "twitter"  => "fil",
                "facebook" => "tl_PH",
                "google"   => "fil"
            ),
            "zh_CN" => array(
                "twitter"  => "zh-cn",
                "facebook" => "zh_CN",
                "google"   => "zh"
            ),
            "ko_KR" => array(
                "twitter"  => "ko",
                "facebook" => "ko_KR",
                "google"   => "ko"
            ),
            "it_IT" => array(
                "twitter"  => "it",
                "facebook" => "it_IT",
                "google"   => "it"
            ),
            "da_DK" => array(
                "twitter"  => "da",
                "facebook" => "da_DK",
                "google"   => "da"
            ),
            "fr_FR" => array(
                "twitter"  => "fr",
                "facebook" => "fr_FR",
                "google"   => "fr"
            ),
            "pl_PL" => array(
                "twitter"  => "pl",
                "facebook" => "pl_PL",
                "google"   => "pl"
            ),
            "nl_NL" => array(
                "twitter"  => "nl",
                "facebook" => "nl_NL",
                "google"   => "nl"
            ),
            "id_ID" => array(
                "twitter"  => "in",
                "facebook" => "nl_NL",
                "google"   => "in"
            ),
            "hu_HU" => array(
                "twitter"  => "hu",
                "facebook" => "hu_HU",
                "google"   => "hu"
            ),
            "fi_FI" => array(
                "twitter"  => "fi",
                "facebook" => "fi_FI",
                "google"   => "fi"
            ),
            "es_ES" => array(
                "twitter"  => "es",
                "facebook" => "es_ES",
                "google"   => "es"
            ),
            "ja_JP" => array(
                "twitter"  => "ja",
                "facebook" => "ja_JP",
                "google"   => "ja"
            ),
            "nn_NO" => array(
                "twitter"  => "no",
                "facebook" => "nn_NO",
                "google"   => "no"
            ),
            "ru_RU" => array(
                "twitter"  => "ru",
                "facebook" => "ru_RU",
                "google"   => "ru"
            ),
            "pt_PT" => array(
                "twitter"  => "pt",
                "facebook" => "pt_PT",
                "google"   => "pt"
            ),
            "pt_BR" => array(
                "twitter"  => "pt",
                "facebook" => "pt_BR",
                "google"   => "pt"
            ),
            "sv_SE" => array(
                "twitter"  => "sv",
                "facebook" => "sv_SE",
                "google"   => "sv"
            ),
            "zh_HK" => array(
                "twitter"  => "zh-tw",
                "facebook" => "zh_HK",
                "google"   => "zh_HK"
            ),
            "zh_TW" => array(
                "twitter"  => "zh-tw",
                "facebook" => "zh_TW",
                "google"   => "zh_TW"
            ),
            "de_DE" => array(
                "twitter"  => "de",
                "facebook" => "de_DE",
                "google"   => "de"
            ),
            "bg_BG" => array(
                "twitter"  => "en",
                "facebook" => "bg_BG",
                "google"   => "bg"
            ),
            "cs_CZ" => array(
                "twitter"  => "cs",
                "facebook" => "cs_CZ",
                "google"   => "cs"
            ),

        );

        if (isset($locales[$locale])) {
            $result = $locales[$locale];
        }

        return $result;

    }

    /**
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function getGoogleShare($params, $url)
    {
        $html = "";
        if ($params->get("gsButton")) {

            // Get locale code
            if (!$params->get("dynamicLocale")) {
                $this->gshareLocale = $params->get("gsLocale", "en");
            } else {
                $locales            = $this->getButtonsLocales($this->locale);
                $this->gshareLocale = JArrayHelper::getValue($locales, "google", "en");
            }

            $html .= '<div class="itp-sharepoint-gshare">';

            switch ($params->get("gsRenderer")) {

                case 1:
                    $html .= $this->genGoogleShare($params, $url);
                    break;

                default:
                    $html .= $this->genGoogleShareHTML5($params, $url);
                    break;
            }

            // Load the JavaScript asynchroning
            if ($params->get("loadGoogleJsLib")) {

                $html .= '<script>';
                if ($this->gshareLocale) {
                    $html .= ' window.___gcfg = {lang: "' . $this->gshareLocale . '"}; ';
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
     * Render the Google Share in standard syntax.
     *
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function genGoogleShare($params, $url)
    {
        $annotation = "";
        if ($params->get("gsAnnotation")) {
            $annotation = ' annotation="' . $params->get("gsAnnotation") . '"';
        }

        $size = "";
        if ($params->get("gsAnnotation") != "vertical-bubble") {
            $size = ' height="' . $params->get("gsType") . '" ';
        }

        $html = '<g:plus action="share" ' . $annotation . $size . ' href="' . $url . '"></g:plus>';

        return $html;
    }

    /**
     * Render the Google Share in HTML5 syntax.
     *
     * @param Joomla\Registry\Registry $params
     * @param string    $url
     *
     * @return string
     */
    private function genGoogleShareHTML5($params, $url)
    {
        $annotation = "";
        if ($params->get("gsAnnotation")) {
            $annotation = ' data-annotation="' . $params->get("gsAnnotation") . '"';
        }

        $size = "";
        if ($params->get("gsAnnotation") != "vertical-bubble") {
            $size = ' data-height="' . $params->get("gsType") . '" ';
        }

        $html = '<div class="g-plus" data-action="share" ' . $annotation . $size . ' data-href="' . $url . '"></div>';

        return $html;
    }

    private function prepareFloatingBox()
    {
        $doc = JFactory::getDocument();
        /** @var $doc JDocumentHtml * */

        $css = '.itp-sharepoint-fstyle {
        	position: fixed;
        	top:' . $this->params->get("fpTop", "30") . 'px !important;
        	left:' . $this->params->get("fpLeft", "60") . 'px !important;
    	}';

        $doc->addStyleDeclaration($css);

        if ($this->params->get("resizeProtection")) {

            $js = 'var itpSharePointMinWidth = '.(int)$this->params->get("fpMinWidth", 1200).';';
            $doc->addScriptDeclaration($js);

            if (version_compare(JVERSION, "3") < 0) { // Use Mootools on Joomla! 2.5

                JHtml::_('behavior.framework');
                $doc->addScript("plugins/system/itpsharepoint/joomla2.js");

            } else { // Use jQuery on Joomla! 3

                JHtml::_('jquery.framework');
                $doc->addScript("plugins/system/itpsharepoint/joomla3.js");

            }
        }

    }

}
