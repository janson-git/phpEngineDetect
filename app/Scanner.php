<?php
/**
 * @author Ivan Lisitskiy ivan.li@livetex.ru
 * 3/31/14 1:19 PM
 */

class Scanner
{
    const UNKNOWN = 'unknown';
    /** @var  JsonLoader */
    protected $jsonLoader;
    protected $apps;

    public
        $debug = false,
        $curlUserAgent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:24.0) Gecko/20100101 Firefox/24.0',
        $curlFollowLocation = true,
        $curlTimeout = 5,
        $curlMaxRedirects = 3
    ;

    /**
     * @param JsonLoader $loader
     * @param string|null $fullJsonPath
     */
    public function __construct(JsonLoader $loader, $fullJsonPath = null)
    {
        $this->jsonLoader = $loader;
        
        if (!is_null($fullJsonPath)) {
            $this->loadAppsRules($fullJsonPath);
        }
    }

    /**
     * @param string $fullJsonPath
     * @param bool $appendToExists
     * @throws Exception
     */
    protected function loadAppsRules($fullJsonPath, $appendToExists = false)
    {
        $jsonData = $this->jsonLoader->load($fullJsonPath);
        
        $decoded = json_decode($jsonData, true);
        if ($appendToExists) {
            $decoded['apps'] = array_merge($this->apps, $decoded['apps']);
        }
        $this->apps = $decoded['apps'];
    }
    
    
    /**
     * Perform a cURL request
     * @param string $url
     * @throws Exception
     * @return \stdClass
     */
    protected function curl($url)
    {
        if ( $this->debug ) {
            echo 'cURL request: ' . $url . "\n";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => $this->curlFollowLocation,
                CURLOPT_MAXREDIRS => $this->curlMaxRedirects,
                CURLOPT_TIMEOUT => $this->curlTimeout,
                CURLOPT_USERAGENT => $this->curlUserAgent,

                CURLOPT_ENCODING => '', // just set to empty encoding to get all encodings of content,
            ));
        $response = curl_exec($ch);


        if ( curl_errno($ch) !== 0 ) {
            throw new \Exception('cURL error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ( $httpCode != 200 ) {
            throw new \Exception('cURL request returned HTTP code ' . $httpCode);
        }

        $headersLength = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headersLength);
        $response = substr($response, $headersLength);

        $result = new stdClass();

        $result->url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $result->host = parse_url($result->url, PHP_URL_HOST);

        $headers = preg_split('/^\s*$/m', trim($headers));
        // get last headers, after all redirects
        $headers = end($headers);
        $headers = str_replace("\r", "", $headers);
        $lines = array_slice(explode("\n", $headers), 1);
        foreach ( $lines as $line ) {
            if ( strpos(trim($line), ': ') !== false ) {
                list($key, $value) = explode(': ', $line);

                $result->headers[strtolower($key)] = $value;
            }
        }

        $result->html = $response;
        $result->html = mb_check_encoding($result->html, 'UTF-8') ? $result->html : utf8_encode($result->html);

        return $result;
    }


    public function detect($url)
    {
        list($url) = explode('#', $url, 1);

        try {
            $result = $this->curl($url);
        } catch (Exception $e) {
            return [$e->getMessage()];
        }

        $pageContent = $result->html;
        $headers = $result->headers;

        
        /**
         * $appsStack = [
         *      [
         *          'name' => '1-C Bitrix',
         *          'categories' => ['CMS', 'ecommerce'],
         *      ],
         *      [
         *          'name' => 'jQuery',
         *          'categories' => ['JS framework'],
         *      ],
         * ];
         */
        $appsStack = [];
        foreach ($this->apps as $appName => $app) {
            $categories = $app['cats'];
            
            foreach ($app as $type => $sample) {

                switch ($type) {
                    case 'url':
                        $sample = $this->parse($sample);
                        foreach ($sample as $item) {
                            $pattern = $item['regex'];
                            if (preg_match("#{$pattern}#", $url)) {
                                $appsStack[$appName] = $this->setDetected($sample, $type, $url);
                                break;
                            }
                        }
                        break;

                    case 'html':
                        if ($this->isHtmlMatch($sample, $pageContent)) {
                            $appsStack[$appName] = $this->setDetected($sample, $type, $pageContent);
                        }
                        break;

                    case 'script':
                        preg_match_all('#<script[^>]+src=("|\')([^"\']+)#i', $pageContent, $matches);
                        if (!empty($matches)) {
                            foreach ($matches[2] as $uri) {
                                if ($this->isScriptMatch($sample, $uri)) {
                                    $appsStack[$appName] = $this->setDetected($sample, $type, $uri);
                                    break;
                                }
                            }
                        }
                        break;

                    case 'meta':
                        preg_match_all("/<meta[^>]+>/i", $pageContent, $matches);
                        if (!empty($matches)) {
                            foreach ($matches[0] as $metaTag) {
                                if ($this->isMetaMatch($sample, $metaTag, $appName)) {
                                    $appsStack[$appName] = $this->setDetected($sample, $type, $metaTag);
                                    break;
                                }
                            }
                        }
                        break;

                    case 'headers':
                        if ($this->isHeadersMatch($sample, $headers)) {
                            $appsStack[$appName] = $this->setDetected($sample, $type, $headers);
                        }
                        break;
                }
                
                if (isset($appsStack[$appName])) {
                    $appsStack[$appName]['name'] = $appName;
                    $appsStack[$appName]['categories'] = $categories;
                }
            }
        }

        return $appsStack;
    }


    private function isHtmlMatch($pattern, $content)
    {
        $pattern = $this->parse($pattern);

        foreach ($pattern as $item) {
            $string = $item['regex'];
            $test = "#{$string}#";
            if (preg_match($test, $content)) {
                return true;
            }
        }
        return false;
    }

    private function isScriptMatch($pattern, $content)
    {
        $patterns = $this->parse($pattern);
        
        foreach ($patterns as $pattern) {
            $test = "#{$pattern['regex']}#";
            if (preg_match($test, $content)) {
                return true;
            }
        }
        return false;
    }

    private function isMetaMatch($metaCondition, $content)
    {
        $metaName = key($metaCondition);
        $metaContent = $metaCondition[$metaName];

        if (preg_match('#name=["\']' . $metaName . '["\']#i', $content)) {
            if (preg_match('/content=("|\')([^"\']+)("|\')/i', $content, $matches)) {
                $metaContent = $this->parse($metaContent);
                foreach ($metaContent as $item) {
                    $pattern = $item['regex'];
                    if (count($matches) == 4 && preg_match("#{$pattern}#", $matches[2])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function isHeadersMatch($sample, $headers)
    {
        foreach ($sample as $headerName => $headerContent) {
            $headerContent = $this->parse($headerContent);
            if (isset($headers[strtolower($headerName)])) {
                foreach ($headerContent as $headerData) {
                    $pattern = $headerData['regex'];
                    @$matched = preg_match("#{$pattern}#", $headers[strtolower($headerName)]);
                    if ($matched) {
                        return true;
                    }
                }
            }
        }
        return false;
    }


    /**
     * Parse apps.json patterns
     */
    protected function parse($patterns) {
        $attrs = null;
        $parsed = [];

		// Convert single patterns to an array
		if ( is_string($patterns) ) {
            $patterns = [ $patterns ];
        }

        foreach ($patterns as $pattern) {
            $attrs = [];

            $parts = explode('\\;', $pattern);
            // get rules from JSON
            foreach ($parts as $i => $attr) {
                if ( $i ) {
                    // Key value pairs
                    $attr = explode(':', $attr);
                    if ( count($attr) > 1 ) {
                        $attrs[array_shift($attr)] = implode(':', $attr);
                    }
                } else {
                    $attrs['string'] = $attr;
                    try {
                        $attrs['regex'] = str_replace('/', '\/', $attr); // Escape slashes in regular expression
                    } catch (Exception $e) {
                        $attrs['regex'] = null;
                    }
                }
            }

            array_push($parsed, $attrs);
        }
        
        return $parsed;
    }
    
    protected function setDetected($pattern, $type, $value, $key = null)
    {
        $app = [
            'detected' => true,
            'type' => $type,
            'confidence' => [],
            'versions' => [],
        ];

        // Set confidence level
        array_push($app['confidence'], isset($pattern['confidence']) ? $pattern['confidence'] : 100);

        // Detect version number
        if ( isset($pattern['version']) ) {
            $version = $pattern['version'];
            preg_match_all($pattern['regex'], $value, $matches);

            if ( !empty($matches) ) {
                foreach ($matches as $i => $match) {
                    // Parse ternary operator
                    preg_match('\\\\' . $i . '\\?([^:]+):(.+)$', $version, $ternary);

                    if ( $ternary && count($ternary) === 3 ) {
                        $version = str_replace($ternary[0], $match ? $ternary[1] : $ternary[2], $version);
                    }

                    // Replace back references
                    $version = str_replace('\\' . $i, $match ? $match : '', $version);
                }

                if ( $version && in_array($version, $app['versions'])) {
                    array_push($app['versions'], $version);
                }

                $app['version'] = $this->getVersion($app['versions']);
            }
        }
        
        return $app;
    }
    

    protected function getVersion($versions) {
        $i = null;
        $resolved = null;

        if ( empty($versions) ) {
            return null;
        }

        usort($versions, function($a, $b) {
                return $a - $b;
            });

        $resolved = $versions[0];

        for ( $i = 1; $i < count($versions); $i++ ) {
            if ( array_key_exists($resolved, $versions[$i]) ) {
                $resolved = $versions[$i];
            } else {
                break;
            }
        }

        return $resolved;
    }

}
