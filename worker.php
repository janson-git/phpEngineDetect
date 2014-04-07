<?php

require_once __DIR__ . '/bootstrap.php';


// DETECT
$loader  = new FileLoader();
$scanner = new Scanner($loader, __DIR__ . '/apps.json');
$curl    = new Curl();

$db = new \PDO("pgsql:dbname=test;host=localhost", 'postgres', 'postgres');


$url = urldecode($_REQUEST['url']);
$url = filter_var($url, FILTER_VALIDATE_URL);

if ($url !== false) {
    // проверим - есть ли у нас уже запись по этому сайту. Если есть - обновляем
    $isset = $db->query("SELECT * FROM site WHERE url = '{$url}'")->fetch(PDO::FETCH_ASSOC);

    
    // если нет - распознаём и сохраняем данные
    try {
        list($url) = explode('#', $url, 1);
        $curl->load($url);

        $pageHeaders = $curl->getResponseHeaders();
        $pageContent = $curl->getResponseHtml();

        $apps = $scanner->detect($url, $pageHeaders, $pageContent);
        
    } catch (Exception $e) {
        $apps = [ ['name' => $url, 'error' => $e->getMessage()] ];
    }
    
    
    // SAVE TO DB
    $db->beginTransaction();
    try {
        $data = json_encode($apps, JSON_UNESCAPED_UNICODE);
        $data = pg_escape_string($data);
        $url = pg_escape_string($url);

        $rawHeaders = pg_escape_string($curl->getResponseHeaders());
        $html = pg_escape_string($curl->getResponseHtml());

        if (empty($isset)) {
            $sql = "INSERT INTO site (url, site_data, headers, html) VALUES ('{$url}', '{$data}', '{$rawHeaders}', '{$html}')";
        } else {
            $id = $isset['id'];
            $sql = "UPDATE site SET site_data = '{$data}', headers = '{$rawHeaders}', html = '{$html}' WHERE id = {$id}";
        }
        $db->query($sql);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    $db->commit();
}
