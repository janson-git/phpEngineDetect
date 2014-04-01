<?php

define('APP_DIR', __DIR__ . '/app');

require_once APP_DIR . '/Scanner.php';
require_once APP_DIR . '/Category.php';

// проверяем детектирование на этих страницах
//$urls = [
//    'http://nvbinder.wordpress.com',
//    'http://asimplelifeincolorado.wordpress.com/2014/03/23/letting-down-my-guard-and-writing-what-i-want-to-write/',
//    'http://dle-news.ru/',
//    'https://www.1c-bitrix.ru/',
//];

// а это - локально сохранённые тестовые страницы. Для более быстрой отладки
$urls = [
    'http://scanner.loc/testwppage.html?bigWAdminID=',
    'http://scanner.loc/dlepage.html',
    'http://scanner.loc/bitrixpage.html',
    'http://google.com',
    'http://yandex.ru',

    'http://joomla.org',
    'http://wordpress.org'
];

// немного URL с сайта alexa.com из категории Business/HR
//$urls = [
//    'citehr.com',
//    'bls.gov/home.htm',
//    'salary.com',
//    'payscale.com',
//    'socialsecurity.gov',
//    'hewitt.com',
//    'dol.gov',
//    'humanresources.about.com',
//    'shrm.org',
//    'diversityinc.com',
//    'stevepavlina.com/blog/',
//    'osha.gov',
//    'hr.com',
//    'ere.net',
//    'cisin.com',
//    'blr.com',
//    'peopleadmin.com',
//    'wageworks.com',
//    'dalecarnegie.com',
//    'doleta.gov',
//    'mercer.com',
//    'astd.org',
//    'brightscope.com',
//    'tmp.com',
//    'trinet.com',
//];



// DETECT CYCLE!
$scanner = new Scanner(__DIR__ . '/apps.json');
$category = new Category(__DIR__ . '/apps.json');

$db = new \PDO("pgsql:dbname=test;host=localhost", 'postgres', 'postgres');

?>
<html>
<head>
    <style>
        body {
            font-family: monospace;
        }
        .url {
            color: #006600;
            width: 50%;
            height: 15px;
            overflow: hidden;
            float: left;
        }
        .engine {
            width: 50%;
            overflow: hidden;
        }
        .separator {
            border-bottom: #ccc 1px solid;
            margin: 5px;
        }
        .icon img {
            margin-left: 10px;
            width: 15px;
            height: 15px;
        }
        .appType {
            color: #ccc;
        }
     </style>
</head>
<body>

<?php
foreach ($urls as $url) {
    // проверим - есть ли у нас уже запись по этому сайту. Если есть - не нужно добавлять.
    $isset = $db->query("SELECT * FROM site WHERE url = '{$url}'")->fetch(PDO::FETCH_ASSOC);
    
    if (empty($isset)) {
        // если нет - распознаём и сохраняем данные
        $apps = $scanner->detect($url);

        $data = json_encode($apps, JSON_UNESCAPED_UNICODE);
        $data = pg_escape_string($data);
        $url = pg_escape_string($url);

        // TODO: save site data to separate table with many-to-many links site<->apps
        
        // SAVE TO DB
        $db->beginTransaction();
        try {
            $sql = "INSERT INTO site (url, site_data) VALUES ('{$url}', '{$data}')";
            $db->query($sql);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        $db->commit();

    } else {
        // если есть - берём данные из БД
        $apps = json_decode($isset['site_data'], true);
    }
    

    // AND DISPLAY RESULT
    ?>
    <div class="url"><?= $url ?></div>
    <div class="engine">&nbsp;
    <?php
    foreach ($apps as $app) {
        $categories = $app['categories'];
        array_walk($categories, function(&$item) use($category) {
                $item = $category->getCategoryById($item);
            });
        ?>
        <div class="icon">
            <img src="http://scanner.loc/images/icons/<?= $app['name'] ?>.png" title="<?= $app['name'] ?>"><?= $app['name'] ?>
            <span class="appType">(<?= implode(', ', $categories) ?>)</span>
        </div>
        <?php
    }
    ?>
    </div>
    <div class="separator"></div>
<?php
}
?>

</body>
</html>
