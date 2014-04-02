<?php

define('APP_DIR', __DIR__ . '/app');

spl_autoload_register(function($className) {
        $classPath = APP_DIR . '/' . $className . '.php';
        if (!file_exists($classPath)) {
            throw new Exception("Class '{$className}' not found to auto load");
        }
        require_once $classPath;
    });


// DETECT
$loader = new FileLoader();

$scanner = new Scanner($loader, __DIR__ . '/apps.json');

$db = new \PDO("pgsql:dbname=test;host=localhost", 'postgres', 'postgres');

$curl = new Curl();
$scanner->setCurl($curl);

$url = urldecode($_REQUEST['url']);
$url = filter_var($url, FILTER_VALIDATE_URL);

if ($url !== false) {
    // проверим - есть ли у нас уже запись по этому сайту. Если есть - не нужно добавлять.
    $isset = $db->query("SELECT * FROM site WHERE url = '{$url}'")->fetch(PDO::FETCH_ASSOC);

    if (empty($isset)) {
        // если нет - распознаём и сохраняем данные
        $apps = $scanner->detect($url);

        // TODO: save site data to separate table with many-to-many links site<->apps
        // SAVE TO DB
        $db->beginTransaction();
        try {
            $data = json_encode($apps, JSON_UNESCAPED_UNICODE);
            $data = pg_escape_string($data);
            $url = pg_escape_string($url);

            $headers = pg_escape_string($scanner->getRawHeaders());
            $html = pg_escape_string($scanner->getHtml());

            $sql = "INSERT INTO site (url, site_data, headers, html) VALUES ('{$url}', '{$data}', '{$headers}', '{$html}')";
            $db->query($sql);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        $db->commit();
    }

}
