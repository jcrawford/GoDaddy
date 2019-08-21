<?php
require 'vendor/autoload.php';

use GoDaddy\Log\Analyzer;

try {

    $stdOut = fopen("php://stdout", "w");

    $analyzer = new Analyzer('access_log.txt', "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"");
    // start the analyzing process
    $analyzer->analyze();

    fwrite($stdOut, "There were {$analyzer->getSuccessCount()} successful requests.\n");
    fwrite($stdOut, "There were {$analyzer->getErrorCount()} requests with errors.\n");
    fwrite($stdOut, "\nThese are the top 10 files.\n");
    foreach($analyzer->getFiles() as $file => $value) {
        fwrite($stdOut, "\t{$file} was requested {$value['total']} for a total of {$value['percentage']}.\n");
    }
    
    fwrite($stdOut, "\nThese are the top 10 referrers.\n");
    foreach($analyzer->getReferrers() as $referrer => $value) {
        fwrite($stdOut, "\t{$referrer} was requested {$value['total']} for a total of {$value['percentage']}.\n");
    }

    fwrite($stdOut, "\nThese are the top 10 user agents.\n");
    foreach($analyzer->getUserAgents() as $user_agent => $value) {
        fwrite($stdOut, "\t{$user_agent} was requested {$value['total']} for a total of {$value['percentage']}.\n");
    }
    
} catch(Exception $e) {
    echo $e->getMessage();
}