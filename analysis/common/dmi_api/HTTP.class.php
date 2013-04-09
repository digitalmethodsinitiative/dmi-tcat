<?php
/**
*  HTTP abstraction
*
*
*  @author Koen Martens <kmartens@sonologic.nl>
*  @version 1.0
*  @package dmi
 * @subpackage HTTP
*
*/

require_once(dirname(__FILE__).'/url_to_absolute.php');

/**
 * Default User Agent to use in HTTP requests 
 */
define('DMI_DEFAULT_UA','Mozilla/5.0 (X11; U; Linux i686; nb-NO; rv:1.9.0.4) Gecko/2008111318 Ubuntu/8.10 (intrepid) Firefox/3.0.4');

/**
*  Interface for abstraction of various HTTP retrieval classes
*
*  @package dmi
 * @subpackage HTTP
*/
interface Http {

  /**
   * Constructor, needs a URL
   * @param string $url the url to retrieve
   */
  public function __construct($url);

  /**
   * Set user-agent string to send to the server
   * @param string $uastring User-agent string
   */
  public function setUserAgent($uastring);

  /**
   * Set the cookie jar (file)
   * @param string $path path to a file to store/retrieve cookies
   */
  public function setCookieJar($path);
  
  /**
   * Set IP resolver to IPv4 or IPv6
   * @param int ipv 4 to select IPv4 only, anything else for IPv6+IPv4
   */
  public function setResolver($ipv);
  /**
   * Set source IP
   * @param string $ip source ip address for the request
   */
  public function setSourceIP($ip);
  
  /**
   * Set timeout
   * @param int timeout request timeout in seconds
   */
  public function setTimeout($timeout);
  
  /**
   * Set referer url
   * @param string url referer url to send to the server
   */
  public function setReferer($url);
  
  /**
   * Add header.
   * 
   * @param string $name header name
   * @param string $value header value
   */
  public function setHeader($name,$value);
  
  /**
   * Add POST data
   * 
   * @param array $fields array of param name => param value 
   */
  public function setPostParams($fields);
  
  /**
   * Set proxy to use.
   * 
   * @param string $host hostname of the proxy server
   * @param string $port port number of the proxy server
   */
  public function setProxy($host,$port);
  
  /**
   * Retrieve page
   */
  public function execute($callback=NULL);

  /**
   * Get error from last request
   * @return string
   */
  public function getUrl();
  public function getError();
  public function getStatus();
  public function getContent();
  public function getContentType();
  public function getHeader($header);
  public function getHeaders();
  public function getEffectiveUrl();
}

/**
 * HTTP abstraction factory
 * @author "Koen Martens" <kmartens@sonologic.nl>
 * @package dmi
 * @subpackage HTTP
 */
class HttpFactory {

  /**
  *  Return a HTTP instance of the (optionally) requested type
  *  Possible types are:
  *  'curl' - simple curl implementation
  *  'multicurl' - implementation using a pool of curl instances (not implemented yet)
  *  'implode' - implementation using the implode(file(..)) idiom (not implemented yet)
  *  'client' - implementation off-loading requests to the client (not implemented yet)
  *  @param string $url (optional) the url to use when insantiating the object
  *  @param string $type (optional) implementation type, defaults to 'curl'
  *  @return HTTP an instantiated object implementing the @see HTTP interface
  */

  static function getHttp($url,$type='curl') {
    switch($type) {
      default:	// fall-through to default 'curl'
      case 'curl': return new HttpCurlImpl($url);
      case 'client': return new HttpClientImpl($url);
      case 'multi': return new HttpMultiCurlImpl($url);
    }
  }

}

?>
