<?php
/**
 * Straight-forward CURL implementation
 * 
 * @author Koen Martens <kmartens@sonologic.nl>
 * @package dmi
 * @subpackage HTTP
 */

/**
 * Uses a new curl session for each request
 * 
 * @package dmi
 * @subpackage HTTP
 */
class HttpCurlImpl implements Http {
  protected $url;
  protected $ch;
  protected $content;
  protected $headers=array();
  protected $contentType;
  protected $error;
  protected $status;
  protected $effectiveUrl;

  /**
   * Callback for processing headers received from curl
   * 
   * @internal
   * @param resource $ch
   * @param string $str
   * @return int
   */
  public function _readHeader($ch,$str) {
      if(preg_match("|^([^:]+):\s*(.*)$|",$str,$matches)) {
        $this->headers[$matches[1]]=$matches[2];
      }
      return strlen($str);
  }
  
  /**
   * Constructor.
   * 
   * @param string $url 
   */
  public function __construct($url) {
    $this->url=$url;
    $this->ch=curl_init($url);
    curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,TRUE);
    curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,TRUE);
    curl_setopt($this->ch,CURLOPT_MAXREDIRS,16);
    curl_setopt($this->ch,CURLOPT_USERAGENT,DMI_DEFAULT_UA);
    curl_setopt($this->ch,CURLOPT_HEADER,FALSE);
    curl_setopt($this->ch,CURLOPT_HEADERFUNCTION,array($this,"_readHeader"));
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false); // @todo make this configurable
  }

  public function setProxy($host,$port) {
      curl_setopt($this->ch,CURLOPT_PROXY,$host);
      curl_setopt($this->ch,CURLOPT_PROXYPORT,$port);
  }
  
  /**
   * Set user agent string to be sent with the request headers.
   * 
   * @param string $uastring 
   */
  public function setUserAgent($uastring) {
    curl_setopt($this->ch,CURLOPT_USERAGENT,$uastring);
  }
  
  /**
   * Set the source IP
   * @param string $ip source IP address to use
   */
  public function setSourceIP($ip) {
    curl_setopt($this->ch, CURLOPT_INTERFACE, $ip);
  }
  
  /**
   * Set the cookie jar (file)
   * @param string $path path to a file to store/retrieve cookies
   */
  public function setCookieJar($path) {
      curl_setopt($this->ch, CURLOPT_COOKIEJAR, $path);
      curl_setopt($this->ch, CURLOPT_COOKIEFILE, $path);
  }

  /**
   * Set IP resolver to IPv4 or IPv6
   * @param int ipv 4 to select IPv4 only, 6 for IPv6 only and
   * anything else for IPv6+IPv4
   */
  public function setResolver($ipv) {
      if($ipv==4) curl_setopt($this->ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
      if($ipv==6) curl_setopt($this->ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
      if($ipv!=4 && $ipv!=6) curl_setopt($this->ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_WHATEVER);
  }
  
  /**
   * Set timeout
   * @param int timeout request timeout in seconds
   */
  public function setTimeout($timeout) {
      curl_setopt($this->ch, CURLOPT_TIMEOUT, 5);
  }
  
  /**
   * Set referer url
   * @param string url referer url to send to the server
   */
  public function setReferer($url) {
      curl_setopt($this->ch, CURLOPT_REFERER, $url);
  }
  
  public function setHeader($name,$value) {
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, array($name.': '.$value));
  }
  
  public function setFollow($follow) {
      curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$follow);
  }
  
  public function setPostParams($fields) {
	curl_setopt($this->ch,CURLOPT_POST,count($fields)>0);
	$fields_string = "";
	foreach($fields as $k => $v) 
		$fields_string .= urlencode($k)."=".urlencode($v)."&";
	$fields_string = substr($fields_string,0,-1);
	curl_setopt($this->ch,CURLOPT_POSTFIELDS,$fields_string);
  }
  
  public function execute($callback=NULL) {
  /*    $job=Session::$singleton->getJob();
      if($job) {
          if($job->getAsync()) {
              
              yield();
          }
      }*/
      return $this->_execute($callback);
  }
  
  /**
   * @internal
   * @param string|array $callback
   * @return boolean 
   */
  public function _execute($callback=NULL) {
    $this->content=curl_exec($this->ch);
    $this->error=curl_error($this->ch);
    $this->contentType=curl_getinfo($this->ch,CURLINFO_CONTENT_TYPE);
    $this->effectiveUrl=curl_getinfo($this->ch,CURLINFO_EFFECTIVE_URL);
    $this->status=curl_getinfo($this->ch,CURLINFO_HTTP_CODE);
    curl_close($this->ch);
    if($callback!==NULL) {
        call_user_func($callback,$this);
        return TRUE;
    } else {
        if($this->content) return TRUE; else return FALSE;
    }
  }
  
  public function getUrl() {
      return $this->url;
  }

  public function getError() {
    return $this->error;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getContent() {
    return $this->content;
  }

  public function getContentType() {
    return $this->contentType;
  }
  
  public function getHeader($header) {
      if(!isset($this->headers[$header])) return null;
      return $this->headers[$header];
  }

  public function getHeaders() {
      return $this->headers;
  }

  public function getEffectiveUrl() {
    return $this->effectiveUrl;
  }

}

?>
