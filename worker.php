<?php
/**
 * @author Ivan Lisitskiy ivan.li@livetex.ru
 * 3/31/14 1:37 PM
 */

// Принимает на входе параметр $url, и запускает парсинг сайта на поиск задействованных приложений
// Успешный ответ сохраняет в отдельную папку
// results/[a-z]/[a-z]/site_name.txt


define('APP_DIR', __DIR__ . '/app');
require_once APP_DIR . '/Scanner.php';


if (isset($_REQUEST['url'])) {
    $url = urldecode($_REQUEST['url']);
    
    $scanner = new Scanner(__DIR__ . '/apps.json');
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $apps = $scanner->detect($url);
        
        preg_match("#http://([^\/]*)#", $url, $matches);
        

        $siteName = $matches[1];

        $firstLetter = substr($siteName, 0, 1);
        $secondLetter = substr($siteName, 1, 1);
        
        $fullPath = __DIR__ . '/results/' . $firstLetter . '/' . $secondLetter . '/' . $siteName . '.txt';
        
        $oldMask = umask(0);
        if (!file_exists(__DIR__ . '/results/' . $firstLetter)) {
            mkdir(__DIR__ . '/results/' . $firstLetter, 0777, TRUE);
        }
        if (!file_exists(__DIR__ . '/results/' . $firstLetter . '/' . $secondLetter)) {
            mkdir(__DIR__ . '/results/' . $firstLetter . '/' . $secondLetter, 0777, TRUE);
        }
        
        umask($oldMask);
        
        file_put_contents($fullPath, implode(',', $apps));
    }
}