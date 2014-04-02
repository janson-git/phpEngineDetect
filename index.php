<?php

define('APP_DIR', __DIR__ . '/app');
define('SAVE_PARSED_TO_DB', false);

spl_autoload_register(function($className) {
        $classPath = APP_DIR . '/' . $className . '.php';
        if (!file_exists($classPath)) {
            throw new Exception("Class '{$className}' not found to auto load");
        }
        require_once $classPath;
    });


$urls = [
    // local test pages
    'http://scanner.loc/local_pages/testwppage.html?bigWAdminID=',
    'http://scanner.loc/local_pages/dlepage.html',
    'http://scanner.loc/local_pages/bitrixpage.html',
    
    // other urls
//    'http://google.com',
//    'http://yandex.ru',
//    'http://joomla.org',
//    'http://wordpress.org',
//    'https://github.com',
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
$loader = new FileLoader();

$scanner = new Scanner($loader, __DIR__ . '/apps.json');
$category = new Category($loader, __DIR__ . '/apps.json');

if (SAVE_PARSED_TO_DB) {
    $db = new \PDO("pgsql:dbname=test;host=localhost", 'postgres', 'postgres');
}

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
            margin-right: 10px;
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
    if (SAVE_PARSED_TO_DB) {
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

    } else {
        $apps = $scanner->detect($url);
    }
    

    // AND DISPLAY RESULT
    ?>
    <div>
            
        <div class="url"><?= $url ?></div>
        <div class="engine">
        <?php
        foreach ($apps as $app) {
            
            if (isset($app['error'])) {
                ?>
                <div class="icon"><?= $app['error'] ?></div>
                <?php
                continue;
            }
            
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
    </div>
    <div class="separator"></div>
<?php
}
?>

</body>
</html>
