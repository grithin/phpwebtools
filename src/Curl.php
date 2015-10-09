<?
namespace Grithin;
use Grithin\Debug;
use Grithin\Arrays;
use Grithin\Http;

//Debug with #$curl->options[CURLOPT_VERBOSE] = true;
/**
Example use

//$post is  either an array or a string
$post = ['bob'=>'sue','sue'=>'monkey'];
$post = '<?xml version="1.0"?>...';

$curl = new Curl;
$response = $curl->post('https://post.craigslist.org/bulk-rss/validate',$post);

echo $response->body;
print_r($response->headers);

*/

class Curl{
	public $headers = array();///<Add headers into this variable
	public $cookie_file = null;///<the file path used for the curl cookie
	public $follow_redirects = true;///<determines whether curl will follow redirects
	public $options = array();///<curl options
	public $referrer = null;///<fabricated referrer
	public $user_agent = null;///<fabricated user agen to use.  Defaults to the user agent requesting the page
	public $error = array();///<curl error
	public $timeout = 30;///<timeout after which curl fails
	public $deleteCookie = true;///<option of whether to delete cookie on curl object destruction
	static $defaultErrorHandler = null;///across all instances
	public $errorHandler = null;

	public $validate_ssl = false; //< will not try to validate the certificate.  If true, set CURLOPT_CAINFO
	public $validate_ssl_host = false; //< will not try to validate the host

	public $request = null;

	public $headerGroups = [
		'default'=>[/*'Content-Type'=>'application/x-www-form-urlencoded',*/],
		'json'=>['Content-Type'=>'application/json',]];
	public function setHeaderGroup($type){
		foreach((array)$this->headerGroups[$type] as $name=>$value){
			$this->headers[$name] = $value;
		}
	}
	public function unsetHeaderGroup($type){
		foreach((array)$this->headerGroups[$type] as $name=>$value){
			if($this->headerGroups['default'][$name]){
				$this->headers[$name] = $this->headerGroups['default'][$name];
			}else{
				unset($this->headers[$name]);
			}
		}
	}
	public function __construct(){
		$this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Curl/PHP '.PHP_VERSION;
		$this->cookie_file = '/tmp/curl.'.str_replace(' ','_',microtime());

		//remove this unwanted header which is set by default by curl
		$this->headers['Expect'] = '';
	}
	public function __destruct(){
		if($this->deleteCookie){
			@unlink($this->cookie_file);
		}
	}

	public function delete($url, $vars = array()){
		return $this->request('DELETE', $this->create_get_url($url, $vars));
	}
	///send GET curl request
	/**
	@param	url	url to send to
	@param	vars	variables to append to the url, see Http::appendsUrl()
	@return curlResponse object
	*/
	public function get($url, $vars = array()){
		return $this->requestFix('GET', $this->create_get_url($url, $vars));
	}

	protected function create_get_url($url, $vars = array()){
		if($vars){
			$url = Http::appendsUrl($vars,$url,false);
		}
		return $url;
	}

	public function head($url, $vars = array()){
		return $this->request('HEAD', $this->create_get_url($url, $vars));
	}
	///send POST curl request
	/**
	@param	url	url to send to
	@param	vars	variables to send in post
	@return curlResponse object
	*/
	public function post($url, $vars, $files = null){
		return $this->requestFix('POST', $url, $vars, $files);
	}
	///send PUT curl request
	/**
	@param	url	url to send to
	@param	vars	variables to send in post
	@return curlResponse object
	*/
	public function put($url, $vars, $files = null){
		return $this->requestFix('PUT', $url, $vars, $files);
	}
	/**
	curl library fails to handle relative paths with pathed cookies correctly, so have to manually absolutize paths
	*/
	public function requestFix($method, $url, $post_vars = array(), $files = null){
		//fixed post to handle relative url paths with
		$url = Http::absoluteUrl($url);
		$response = $this->request($method, $url, $post_vars, $files);
		if($this->follow_redirects && $response->headers['Location']){
			$url = Http::absoluteUrl($response->headers['Location'],$url);
			$response = $this->get($url);
		}
		$response->finalUrl = $url;
		return $response;
	}

	public function request($method, $url, $post_vars = array(), $files = null){
		$this->error = array();
		$this->request = curl_init();
		$this->setRequestOptions($url, $method, $post_vars, $files);
		$this->setRequestHeaders();

		$response = curl_exec($this->request);

		if ($response){
			$response = new CurlResponse($response);
		}else{
			throw new \Exception('Error number:'.curl_errno($this->request).' with text: '.curl_error($this->request));
		}

		if($response->headers['Content-Encoding'] == 'gzip'){
			if(!function_exists('gzdecode')){
				function gzdecode($data){
					$g=tempnam('/tmp','tp_');
					@file_put_contents($g,$data);
					ob_start();
					readgzfile($g);
					$d=ob_get_clean();
					unlink($g);
					return $d;
				}
			}
			$response->body = gzdecode($response->body);
		}elseif($response->headers['Content-Encoding'] == 'deflate'){
			$response->body = gzdeflate($response->body);
		}

		curl_close($this->request);

		return $response;
	}

	protected function setRequestHeaders(){
		$headers = array();
		foreach ($this->headers as $key => $value){
			$headers[] = $key.': '.$value;
		}
		curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
	}

	protected function setRequestOptions($url, $method, $vars, $files = null){
		$purl = parse_url($url);

		if ($purl['scheme'] == 'https'){
			curl_setopt($this->request, CURLOPT_PORT , empty($purl['port'])?443:$purl['port']);
			if ($this->validate_ssl){
				curl_setopt($this->request,CURLOPT_SSL_VERIFYPEER, true);
			}elseif($this->validate_ssl_host){
				curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, 0);
			}else{
				curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, 2);
			}
		}

		$method = strtoupper($method);
		switch ($method){
			case 'HEAD':
				curl_setopt($this->request, CURLOPT_NOBODY, true);
				break;
			case 'GET':
				curl_setopt($this->request, CURLOPT_HTTPGET, true);
				break;
			case 'PUT':
				curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, "PUT");
				$toPost = true;
				break;
			case 'POST':
				curl_setopt($this->request, CURLOPT_POST, true);
				$toPost = true;
				break;
			default:
				curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
		}

		curl_setopt($this->request, CURLOPT_URL, $url);

		if ($files || !empty($vars)){
				if (!$toPost){
					throw new \InvalidArgumentException('POST-vars may only be set for a POST or PUT Request.');
				}
				if(!is_array($vars)){
					curl_setopt($this->request, CURLOPT_POSTFIELDS, $vars);
				}elseif($files){
					foreach($files as &$file){
						if($file[0] != '@'){
							$file = '@'.$file;
						}
					}
					unset($file);
					curl_setopt($this->request, CURLOPT_POSTFIELDS, Arrays::merge($files,$vars));
				}else{
					curl_setopt($this->request, CURLOPT_POSTFIELDS, Http::buildQuery($vars));
				}
		}elseif ($toPost){
			throw new \InvalidArgumentException('POST-vars must be set for a POST-Request.');
		}

		# Set some default CURL options
		curl_setopt($this->request, CURLOPT_HEADER, true);
		curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->request, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($this->request, CURLOPT_TIMEOUT, $this->timeout);

		if ($this->cookie_file){
			curl_setopt($this->request, CURLOPT_COOKIEFILE, $this->cookie_file);
			curl_setopt($this->request, CURLOPT_COOKIEJAR, $this->cookie_file);
		}

		/* relative paths fix, see requestFix
		if ($this->follow_redirects){
			curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, true);
		}
		*/

		if ($this->referrer){
			curl_setopt($this->request, CURLOPT_REFERER, $this->referrer);
		}

		# Set any custom CURL options
		foreach ($this->options as $option => $value){
			curl_setopt($this->request, $option, $value);
		}
	}

	///if cookie_file, parse cookies
	function cookies(){
		if($this->cookie_file){
			$content = file_get_contents($this->cookie_file);
			$lines =  explode("\n",$content);
			$cookies = [];
			foreach($lines as $line){
				if(substr($line,0,1) == '#'){
					continue;
				}
				$parts = explode("\t", $line);
				/*
				0 domain - The domain that created AND that can read the variable.
				1 flag - A TRUE/FALSE value indicating if all machines within a given domain can access the variable. This value is set automatically by the browser, depending on the value you set for domain.
				2 path - The path within the domain that the variable is valid for.
				3 secure - A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.
				4 expiration - The UNIX time that the variable will expire on. UNIX time is defined as the number of seconds since Jan 1, 1970 00:00:00 GMT.
				5 name - The name of the variable.
				6 value - The value of the variable.
				*/
				$cookies[$parts[0]][$parts[2]][$parts[5]] = $parts[6];
			}
		}
		return $cookies;
	}
}

///Parses the response from a Curl request into an object containing the response body and an associative array of headers
/**
Curl response headers available in $headers attribute.  Response body available in $body attribute.
@note	__toString method is set to print out the body of the response
@author Sean Huber <shuber@huberry.com>
**/
class CurlResponse{
	public $body = '';
	public $headers = array();

	function __construct($response){
		list($header, $this->body) = explode("\r\n\r\n", $response, 2);
		$headers = explode("\r\n", $header);

		# Extract the version and status from the first header
		$version_and_status = array_shift($headers);
		preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', $version_and_status, $matches);
		$this->headers['Http-Version'] = $matches[1];
		$this->headers['Status-Code'] = $matches[2];
		$this->headers['Status'] = $matches[2].' '.$matches[3];

		# Convert headers into an associative array
		foreach ($headers as $header){
			preg_match('#(.*?)\:\s(.*)#', $header, $matches);
			$this->headers[$matches[1]] = $matches[2];
		}
	}

	public function __toString(){
		return $this->body;
	}

	public function isHtml(){
		$type = isset($this->headers['Content-Type'])?$this->headers['Content-Type']:'';
		if (preg_match('/(x|ht)ml/i', $type)){
			return true;
		}else{
			return false;
		}
	}

	public function getMimeType(){
		$type = isset($this->headers['Content-Type'])?$this->headers['Content-Type']:false;
		if ($type){
			list($type) = explode(";", $type);
			$type = trim($type);
		}
		return $type;
	}
}