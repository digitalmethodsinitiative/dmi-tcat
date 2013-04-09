<?php
/**
 * @author koenmartens
 * @package dmi
 * @subpackage api
 */


include_once(dirname(__FILE__).'/HTTP.class.php');
include_once(dirname(__FILE__).'/HttpCurlImpl.class.php');
include_once(dirname(__FILE__).'/HttpMultiCurlImpl.class.php');

define('DMI_LOCAL',0);
define('DMI_TESTING',1);
define('DMI_PRODUCTION',2);

/**
 * Description of DmiTool
 *
 * @author koenmartens
 * @package dmi
 * @subpackage api
 */
class DmiTool {
    private $tool;
    private $branch;
    private $parms=array();
    private $cookieJar;
    
    /**
     * Create a new DMI tool accessor
     * 
     * @param string $tool name of the tool (eg 'coword', 'scrapeGoogle')
     * @param integer $branch one of DMI_LOCAL, DMI_TESTING or DMI_PRODUCTION, defaults to DMI_PRODUCTION
     */
    public function __construct($tool,$branch=DMI_PRODUCTION) {
        $this->tool=$tool;
        $this->branch=$branch;
        $this->cookieJar = tempnam('/tmp','_dmi_cookies_');
    }
    
    /**
     * Set parameter for tool invocation
     * 
     * @param string $key parameter name
     * @param mixed $value parameter value
     */
    public function setParm($key,$value) {
        $this->parms[$key]=$value;
    }
    
    /**
     * Unset parameter
     * 
     * @param string $key parameter name
     */
    public function unsetParm($key) {
        if(isset($this->parms[$key])) unset($this->parms[$key]);
    }
    
    /**
     * Generate tool url based on DMI_ platform selected
     * 
     * @return string base DMI tool url
     */
    private function baseUrl() {
        switch($this->branch) {
            case DMI_LOCAL:
                return "http://localhost/tools/beta";
            case DMI_TESTING:
                return "https://testing.digitalmethods.net/tools/beta";
            default:
                return "https://tools.digitalmethods.net/beta";
        }
    }
    
    /**
     * Clean up 
     */
    private function cleanup() {
        if(file_exists($this->cookieJar))
            unlink($this->cookieJar);
    }

    /**
     * Execute request
     * 
     * @param callable $callback 
     */
    public function execute($callback) {
        $urlBase=$this->baseUrl()."/".$this->tool."/";
        
        $HTTP=HttpFactory::getHttp($urlBase."?json=createjob",'curl');
        $HTTP->setCookieJar($this->cookieJar);
        $HTTP->_rpc=array();
        $HTTP->_rpc['params']=$this->parms;
        $HTTP->_rpc['callback']=$callback;
        $HTTP->_rpc['tool']=$this->tool;
        $HTTP->execute(array($this,"_rpcPostJob"));        
    }
    
    
    /**
     * Callback for {@see rpc}, do not call directly
     * 
     * @param HTTP $HTTP
     * @throws Exception 
     */
    public function _rpcPostJob($HTTP) {
        if($HTTP->getStatus()!='200') {
            $this->cleanup();
            call_user_func($HTTP->_rpc['callback'],array(
                                                    'error'=>'unable to create job',
                                                    'httpStatus'=>$HTTP->getStatus(),
                                                    'httpError'=>$HTTP->getError()
            ));
        } else {
            $data=json_decode($HTTP->getContent());
            if(!isset($data->jobid)) {
                $this->cleanup();
                call_user_func($HTTP->_rpc['callback'],array(
                                                    'error'=>'no job id assigned',
                                                    'httpContent'=>$HTTP->getContent()
                ));                
            } else {           
                $urlBase=$this->baseUrl()."/".$HTTP->_rpc['tool']."/";                
                $post=HttpFactory::getHttp($urlBase."?json=syn&of=json&jobid=".$data->jobid);
                $post->setCookieJar($this->cookieJar);
                $post->setPostParams($HTTP->_rpc['params']);
                $post->_rpc=$HTTP->_rpc;
                $post->execute(array($this,"_rpcProcessResult"));
            }
        }
    }
    
    /**
     * Called by _rpcPostJob, do not call directly
     * 
     * @param HTTP $HTTP 
     */
    public function _rpcProcessResult($HTTP) {
        if($HTTP->getStatus()!='200') {
            $this->cleanup();
            call_user_func($HTTP->_rpc['callback'],array(
                                                    'error'=>'post failed',
                                                    'httpStatus'=>$HTTP->getStatus(),
                                                    'httpError'=>$HTTP->getError()
            ));            
        } else {
            $this->cleanup();
            $data=json_decode($HTTP->getContent());
            call_user_func($HTTP->_rpc['callback'],$data);            
        }
    }
    
}

?>
