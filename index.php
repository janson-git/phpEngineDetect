<?php

define('APP_DIR', __DIR__ . '/app');

spl_autoload_register(function($className) {
        $classPath = APP_DIR . '/' . $className . '.php';
        if (!file_exists($classPath)) {
            throw new Exception("Class '{$className}' not found to auto load");
        }
        require_once $classPath;
    });


require_once __DIR__ . '/lib/RollingCurl.php';
//require_once __DIR__ . '/lib/RollingCurl.php';

$urls = [
    // local test pages
    'http://scanner.loc/local_pages/testwppage.html?bigWAdminID=',
    'http://scanner.loc/local_pages/dlepage.html',
    'http://scanner.loc/local_pages/bitrixpage.html',
    
    // other urls
    'http://www.php.net/manual/ru/gearman.examples-reverse-task.php',
    'http://google.com',
    'http://yandex.ru',
    'http://joomla.org',
    'http://wordpress.org',
    'https://github.com',
    
    'http://citehr.com',
    'http://bls.gov/home.htm',
    'http://salary.com',
    'http://payscale.com',
    'http://socialsecurity.gov',
    'http://hewitt.com',
    'http://dol.gov',
    'http://humanresources.about.com',
    'http://dalecarnegie.com',
    'http://doleta.gov',
    'http://mercer.com',
    'http://astd.org',
    'http://brightscope.com',
    'http://tmp.com',
    'http://trinet.com',
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
            margin-right: 10px;
            width: 15px;
            height: 15px;
        }
        .appType {
            color: #ccc;
        }
        .error {
            color: red;
            font-weight: bold;
        }
     </style>
</head>
<body>

<?php

$curl = new Curl();
$scanner->setCurl($curl);


foreach ($urls as $url) {
    // проверим - есть ли у нас уже запись по этому сайту. Если есть - не нужно добавлять.
    $isset = $db->query("SELECT * FROM site WHERE url = '{$url}'")->fetch(PDO::FETCH_ASSOC);

    if (empty($isset)) {
        $apps = [
            0 => ['error' => 'Not parsed'],
        ];
    } else {
        $apps = json_decode($isset['site_data'], true);
    }

    
    // AND DISPLAY RESULT
    ?>
    <div>
            
        <div class="url"><?= $url ?></div>
        <div class="engine">
        <?php
        if (empty($apps)) {
            echo '&nbsp;';
        } else {
            foreach ($apps as $app) {
                
                if (isset($app['error'])) {
                    ?>
                    <div class="icon error"><?= $app['error'] ?></div>
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
