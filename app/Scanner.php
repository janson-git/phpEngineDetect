<?php
/**
 * @author Ivan Lisitskiy ivan.li@livetex.ru
 * 3/31/14 1:19 PM
 */

class Scanner
{
    const UNKNOWN = 'unknown';

    protected $apps;

    public
        $debug = false,
        $curlUserAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1',
        $curlFollowLocation = true,
        $curlTimeout = 5,
        $curlMaxRedirects = 3
    ;

    /**
     * @param string|null $fullJsonPath
     */
    public function __construct($fullJsonPath = null)
    {
        if (!is_null($fullJsonPath)) {
            $this->loadAppsRules($fullJsonPath);
        }
    }

    /**
     * @param string $fullJsonPath
     * @param bool $appendToExists
     * @throws Exception
     */
    public function loadAppsRules($fullJsonPath, $appendToExists = false)
    {
        if (!file_exists($fullJsonPath)) {
            throw new Exception("File {$fullJsonPath} not found");
        }
        
        $json = json_decode(file_get_contents($fullJsonPath), true);
        if ($appendToExists) {
            $json['apps'] = array_merge($this->apps, $json['apps']);
        }
        $this->apps = $json['apps'];
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
        // избавляемся от якорей в URL
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
                        if (!is_array($sample)) {
                            $sample = [$sample];
                        }
                        foreach ($sample as $pattern) {
                            if (preg_match("#{$pattern}#", $url)) {
                                $appsStack[$appName] = ['name' => $appName, 'categories' => $categories];
                                break;
                            }
                        }
                        break;

                    case 'html':
                        if ($this->isHtmlMatch($sample, $pageContent)) {
                            $appsStack[$appName] = ['name' => $appName, 'categories' => $categories];
                        }
                        break;

                    case 'script':
                        preg_match_all('#<script[^>]+src=("|\')([^"\']+)#i', $pageContent, $matches);
                        if (!empty($matches)) {
                            foreach ($matches[2] as $uri) {
                                if ($this->isScriptMatch($sample, $uri)) {
                                    $appsStack[$appName] = ['name' => $appName, 'categories' => $categories];
                                    break;
                                }
                            }
                        }
                        break;

                    case 'meta':
                        preg_match_all("/<meta[^>]+>/i", $pageContent, $matches);
                        if (!empty($matches)) {
                            foreach ($matches[0] as $metaTag) {
                                if ($this->isMetaMatch($sample, $metaTag)) {
                                    $appsStack[$appName] = ['name' => $appName, 'categories' => $categories];
                                    break;
                                }
                            }
                        }
                        break;

                    case 'headers':
                        if ($this->isHeadersMatch($sample, $headers)) {
                            $appsStack[$appName] = ['name' => $appName, 'categories' => $categories];
                        }
                        break;
                    
                    
                }
            }
        }

        return $appsStack;
    }


    private function isHtmlMatch($pattern, $content)
    {
        if (empty($pattern)) {
            return false;
        }
        if (!is_array($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $string) {
            $test = "#{$string}#";
            if (preg_match($test, $content)) {
                return true;
            }
        }
        return false;
    }

    private function isScriptMatch($pattern, $content)
    {
        if (empty($pattern)) {
            return false;
        }
        if (!is_array($pattern)) {
            $pattern = [$pattern];
        }
        foreach ($pattern as $string) {
            $test = "#{$string}#";
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
                if (count($matches) == 4 && preg_match('#' . $metaContent . '#', $matches[2], $metaMatches)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isHeadersMatch($sample, $headers)
    {
        foreach ($sample as $headerName => $headerContent) {
            if (!is_array($headerContent)) {
                $headerContent = [$headerContent];
            }
            if (isset($headers[strtolower($headerName)])) {
                foreach ($headerContent as $headerData) {
                    @$matched = preg_match("#{$headerData}#", $headers[strtolower($headerName)]);
                    if ($matched) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
