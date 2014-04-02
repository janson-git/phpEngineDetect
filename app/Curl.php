<?php
/**
 * @author Ivan Janson ivan.janson@gmail.com
 * 4/2/14 11:28 AM
 */

 class Curl
 {
     protected $curl;
     
     protected $url;
     protected $host;
     protected $headers;
     protected $html;
     
     protected
         $curlUserAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:24.0) Gecko/20100101 Firefox/24.0',
         $curlFollowLocation = true,
         $curlTimeout = 5,
         $curlMaxRedirects = 3
     ;
     
     public function __construct($url = null)
     {
         $this->curl = curl_init($url);
         $this->url = $url;

         curl_setopt_array($this->curl, array(
                 CURLOPT_SSL_VERIFYPEER => false,
                 CURLOPT_HEADER => true,
                 CURLOPT_RETURNTRANSFER => true,
                 CURLOPT_FOLLOWLOCATION => $this->curlFollowLocation,
                 CURLOPT_MAXREDIRS => $this->curlMaxRedirects,
                 CURLOPT_TIMEOUT => $this->curlTimeout,
                 CURLOPT_USERAGENT => $this->curlUserAgent,

                 CURLOPT_ENCODING => '', // just set to empty encoding to get all encodings of content,
             ));
     }
     
     
     public function load($url = null)
     {
         if (is_null($url) && is_null($this->url)) {
             throw new Exception('You must set url to load');
         }
         
         if (empty($url)) {
             $url = $this->url;
         }
         
         curl_setopt($this->curl, CURLOPT_URL, $url);
         $response = curl_exec($this->curl);

         if ( curl_errno($this->curl) !== 0 ) {
             throw new \Exception('cURL error: ' . curl_error($this->curl));
         }

         $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
         if ( $httpCode != 200 ) {
             throw new \Exception('cURL request returned HTTP code ' . $httpCode);
         }

         $headersLength = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
         $headers = substr($response, 0, $headersLength);
         $response = substr($response, $headersLength);

         
         $this->url = curl_getinfo($this->curl, CURLINFO_EFFECTIVE_URL);
         $this->host = parse_url($this->url, PHP_URL_HOST);

         $headers = preg_split('/^\s*$/m', trim($headers));
         // get last headers, after all redirects
         $headers = end($headers);
         $headers = str_replace("\r", "", $headers);
         $lines = array_slice(explode("\n", $headers), 1);
         foreach ( $lines as $line ) {
             if ( strpos(trim($line), ': ') !== false ) {
                 list($key, $value) = explode(': ', $line);

                 $this->headers[strtolower($key)] = $value;
             }
         }

         $this->html = $response;
         $this->html = mb_check_encoding($this->html, 'UTF-8') ? $this->html : utf8_encode($this->html);
     }
     
     public function getResponseHeaders()
     {
         return $this->headers;
     }

     public function getResponseHtml()
     {
         return $this->html;
     }

     public function getResponseHost()
     {
         return $this->host;
     }

     public function getResponseUrl()
     {
         return $this->url;
     }
 }