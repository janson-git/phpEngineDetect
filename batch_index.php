<?php
set_time_limit(0);

require_once __DIR__ . '/bootstrap.php';


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


require_once __DIR__ . '/lib/RollingCurl.php';

$rc = new RollingCurl();
foreach ($urls as $url) {
    $rc->add(new RollingCurlRequest("http://scanner.loc/worker.php?url=" . urlencode($url)));
}
$rc->execute(10);

echo "All request sent";
