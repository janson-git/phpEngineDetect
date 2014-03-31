<?php

define('APP_DIR', __DIR__ . '/app');

require_once APP_DIR . '/Scanner.php';
require_once APP_DIR . '/Category.php';


$urls = [
    'http://nvbinder.wordpress.com',
    'http://asimplelifeincolorado.wordpress.com/2014/03/23/letting-down-my-guard-and-writing-what-i-want-to-write/',
    'http://dle-news.ru/',
    'https://www.1c-bitrix.ru/',
    
];

$urls = [
//    __DIR__ . '/testwppage.html',
//    __DIR__ . '/dlepage.html',
//    __DIR__ . '/bitrixpage.html',

    'http://scanner.loc/testwppage.html?bigWAdminID=',
    'http://scanner.loc/dlepage.html',
    'http://scanner.loc/bitrixpage.html',
//    'http://joomla.org',
];


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


// FIXME: try to use workers. With one of MQ in future
//
//$ch = curl_init();
//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
//curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//
//for ($i = 0; $i < 10; $i++) {
//    $url = $urls[$i];
//    if (strpos($url, 'http://') === false) {
//        $url = 'http://' . $url;
//    }
//    
//    $request = 'http://scanner.loc/worker.php?url=' . urlencode($url);
////    curl_setopt($ch, CURLOPT_URL, $request);
////    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
////    curl_setopt($ch, CURLOPT_NOBODY, true);
////    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
////    
////    
////    curl_exec($ch);
//    
//    file_get_contents($request, null, null, 0, 100);
//}
//
//echo "END!";
//exit;


// DETECT CYCLE!
$scanner = new Scanner(__DIR__ . '/apps.json');
$category = new Category(__DIR__ . '/apps.json');


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
    $apps = $scanner->detect($url);
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
