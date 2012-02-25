<?PHP

/* 
 * Script: php-json-proxy: Get external JSON!
 * @version 1.0.1, Last updated: 02/25/2012
 *  
 * GitHub       - http://github.com/maanas/php-json-proxy/
 * Source       - http://github.com/maanas/php-json-proxy/raw/master/php-json-proxy.php
 * 
 * 
 * GET Parameters
 * Certain GET (query string) parameters may be passed into php-json-proxy.php to control its behavior.
 * @params url - The remote urlencoded URL resource to fetch
 * @params user_agent - This value will be sent to the remote URL request 
 * `User-Agent:` HTTP request header. If omitted, the browser user agent will be passed through. 
 * @params send_cookies - If send_cookies=1, all cookies will be forwarded through to the remote URL request.
 * @parms send_session - If send_session=1 and send_cookies=1, the SID cookie will be forwarded through to the remote URL request.
 * @params full_headers - If a JSON request and full_headers=1, the JSON response will contain detailed header information.
 * @params full_status - If a JSON request and full_status=1, the JSON response will contain detailed cURL status information, otherwise it will just contain the `http_code` property.
 * 
 * POST Parameters
 * All POST parameters are automatically passed through to the remote URL request.
 * 
 * JSON requests
 * This request will return the contents of the specified url in JSON format.
 * 
 * @example
 * Request:php-json-proxy.php?url=http://example.com/
 * Response: { "contents": "<html>...</html>", "headers": {...}, "status": {...} }
 * 
 * JSON object properties:
 * contents - (String) The contents of the remote URL resource.
 * headers - (Object) A hash of HTTP headers returned by the remote URL resource.
 * status - (Object) A hash of status codes returned by cURL.
 * 
 * 
 * Assumes magic_quotes_gpc = Off in php.ini
 * 
 * 
 * 
 */ 


/*
 * @var $valid_url_regex
 * Support only a json call. A url ending in json only will be parsed
 */ 

$valid_url_regex = '/(.*).json/';

// Get Url params
$url = $_GET['url'];

if ( !$url ) {
  
  // Passed url not specified.
  $contents = 'ERROR: url not specified';
  $status = array( 'http_code' => 'ERROR' );
  
} else if ( !preg_match( $valid_url_regex, $url ) ) {
  
  // Passed url doesn't match $valid_url_regex.
  $contents = 'ERROR: invalid url';
  $status = array( 'http_code' => 'ERROR' );
  
} else {
  $ch = curl_init( $url );
  
  if ( strtolower($_SERVER['REQUEST_METHOD']) == 'post' ) {
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $_POST );
  }
  
  if ( $_GET['send_cookies'] ) {
    $cookie = array();
    foreach ( $_COOKIE as $key => $value ) {
      $cookie[] = $key . '=' . $value;
    }
    if ( $_GET['send_session'] ) {
      $cookie[] = SID;
    }
    $cookie = implode( '; ', $cookie );
    
    curl_setopt( $ch, CURLOPT_COOKIE, $cookie );
  }
  
  curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
  curl_setopt( $ch, CURLOPT_HEADER, true );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  
  curl_setopt( $ch, CURLOPT_USERAGENT, $_GET['user_agent'] ? $_GET['user_agent'] : $_SERVER['HTTP_USER_AGENT'] );
  
  list( $header, $contents ) = preg_split( '/([\r\n][\r\n])\\1/', curl_exec( $ch ), 2 );
  
  $status = curl_getinfo( $ch );
  
  curl_close( $ch );
}

// Split header text into an array.
$header_text = preg_split( '/[\r\n]+/', $header );

if ( $_GET['mode'] == 'native' ) {
  if ( !$enable_native ) {
    $contents = 'ERROR: invalid mode';
    $status = array( 'http_code' => 'ERROR' );
  }
  
  // Propagate headers to response.
  foreach ( $header_text as $header ) {
    if ( preg_match( '/^(?:Content-Type|Content-Language|Set-Cookie):/i', $header ) ) {
      header( $header );
    }
  }
  
  print $contents;
  
} else {
  
  // $data will be serialized into JSON data.
  $data = array();
  
  // Propagate all HTTP headers into the JSON data object.
  if ( $_GET['full_headers'] ) {
    $data['headers'] = array();
    
    foreach ( $header_text as $header ) {
      preg_match( '/^(.+?):\s+(.*)$/', $header, $matches );
      if ( $matches ) {
        $data['headers'][ $matches[1] ] = $matches[2];
      }
    }
  }
  
  // Propagate all cURL request / response info to the JSON data object.
  if ( $_GET['full_status'] ) {
    $data['status'] = $status;
  } else {
    $data['status'] = array();
    $data['status']['http_code'] = $status['http_code'];
  }
  
  // Set the JSON data object contents, decoding it from JSON if possible.
  $decoded_json = json_decode( $contents );
  $data['contents'] = $decoded_json ? $decoded_json : $contents;
  
  // Generate appropriate content-type header.
  $is_xhr = strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
  header( 'Content-type: application/' . ( $is_xhr ? 'json' : 'x-javascript' ) );
  
  // Get JSONP callback.
  $jsonp_callback = $enable_jsonp && isset($_GET['callback']) ? $_GET['callback'] : null;
  
  // Generate JSON/JSONP string
  $json = json_encode( $data );
  
  print $jsonp_callback ? "$jsonp_callback($json)" : $json;
  
}

?>
