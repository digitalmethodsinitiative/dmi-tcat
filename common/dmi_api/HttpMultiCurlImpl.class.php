<?php
/**
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 *
 * @author "Koen Martens" <kmartens@sonologic.nl>
 * @package dmi
 * @subpackage HTTP
 */

/**
 * Description of HttpMultiCurlImpl
 *
 * @author "Koen Martens" <kmartens@sonologic.nl>
 * @package dmi
 * @subpackage HTTP
 */
class HttpMultiCurlImpl extends HttpCurlImpl {
    static private $handle=NULL;
    static private $instances=array();
    
    protected $error;
    protected $status;
    protected $contenttype;
    protected $callback;
    
    static function addInstance($HTTP) {
        if(HttpMultiCurlImpl::$handle===NULL)
            HttpMultiCurlImpl::$handle=curl_multi_init();
        if(HttpMultiCurlImpl::$handle===NULL)
            throw new Exception("Unable to initialize multicurl");
        
        $a=curl_multi_add_handle(HttpMultiCurlImpl::$handle,$HTTP->ch);
        
        if($a) throw new Exception("Unable to add curl handle to multi curl: $a");
        
        HttpMultiCurlImpl::$instances[(int)$HTTP->ch]=$HTTP;
        //while(curl_multi_exec(HttpMultiCurlImpl::$handle,$active)===CURLM_CALL_MULTI_PERFORM) {}
        while(curl_multi_exec(HttpMultiCurlImpl::$handle,$active)===CURLM_CALL_MULTI_PERFORM) {}
        //curl_multi_exec(HttpMultiCurlImpl::$handle,$active);
    }
    
    static function run($timeout = 1) {
        $active = NULL;
        $s = curl_multi_select(HttpMultiCurlImpl::$handle, $timeout);
        if ($s < 0)
            throw new Exception("multi curl select error: $s");

        if ($s > 0) {
            //while(curl_multi_exec(HttpMultiCurlImpl::$handle,$active)===CURLM_CALL_MULTI_PERFORM) {}
            while (curl_multi_exec(HttpMultiCurlImpl::$handle, $active) === CURLM_CALL_MULTI_PERFORM) {
                
            }
            //curl_multi_exec(HttpMultiCurlImpl::$handle,$active);
        }
        if (($info = curl_multi_info_read(HttpMultiCurlImpl::$handle)) !== FALSE) {
            $HTTP = HttpMultiCurlImpl::$instances[(int)$info['handle']];
            $HTTP->content = curl_multi_getcontent($HTTP->ch);
            $HTTP->error = curl_error($HTTP->ch);
            $HTTP->status = curl_getinfo($HTTP->ch, CURLINFO_HTTP_CODE);
            $HTTP->contenttype = curl_getinfo($HTTP->ch, CURLINFO_CONTENT_TYPE);
            $HTTP->effectiveUrl = curl_getinfo($HTTP->ch, CURLINFO_EFFECTIVE_URL);
            unset(HttpMultiCurlImpl::$instances[(int)$HTTP->ch]);
            curl_multi_remove_handle(HttpMultiCurlImpl::$handle, $HTTP->ch);
            curl_close($HTTP->ch);
            call_user_func($HTTP->callback, $HTTP);
        }

        return ($active);
    }
    
    static function getQueueSize() {
        if(HttpMultiCurlImpl::$handle!==NULL) HttpMultiCurlImpl::run();
        return count(HttpMultiCurlImpl::$instances);
    }
    
    static function getQueueString() {
        return print_r(HttpMulticurlImpl::$instances,true);
    }
    
    public function __construct($url) {
        parent::__construct($url);
    }

    public function execute($callback=NULL) {
        if($callback===NULL)
            throw new Exception("HttpMultiCurlImpl::execute called without callback");
        $this->callback=$callback;
        HttpMultiCurlImpl::addInstance($this);
        return TRUE;
    }
    
    public function getError() {
        return $this->error;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getContenttype() {
        return $this->contenttype;
    }

  public function getEffectiveUrl() {
    return curl_getinfo($this->ch,CURLINFO_EFFECTIVE_URL);
  }
}

/**
 * Initialize the multi-curl handle
 */
//HttpMultiCurlImpl::$handle=

?>
