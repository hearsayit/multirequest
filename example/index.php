<?php

require_once ('config.php');

$urls = array ('http://forums.somethingawful.com/', 'http://asdlksda.sas', 'http://www.somethingpositive.net/', 'http://www.somethingawful.com/', 'http://awesome-hd.net/', 'http://www.istartedsomething.com/', 'http://www.somewhere.fr/', 'http://forums.tkasomething.com/', 'http://www.somewhereinblog.net/', 'http://www.killsometime.com/', 'http://v.sometrics.com/', 'http://www.fearsome-oekaki.com/', 'http://www.dosomething.org/', 'http://www.avonandsomerset.police.uk/');

function debug($message) {
	echo $message . '<br />';
	flush();
}

function debugRequestComplete(MultiRequest_Request $request, MultiRequest_Handler $handler) {
	debug('Request complete: ' . $request->getUrl().' Code: '.$request->getCode().' Time: '.$request->getTime());
	debug('Requests in waiting queue: ' . $handler->getRequestsInQueueCount());
	debug('Active requests: ' . $handler->getActiveRequestsCount());
}

function saveCompleteRequestToFile(MultiRequest_Request $request, MultiRequest_Handler $handler) {
	$filename = preg_replace('/[^\w\.]/', '', $request->getUrl());
	file_put_contents(DOWNLOADS_DIR.DIRECTORY_SEPARATOR.$filename, $request->getContent());
}

function prepareDownloadsDir() {
	$dirPath = DOWNLOADS_DIR;
	chmod($dirPath, 0777);
	$dirIterator = new RecursiveDirectoryIterator($dirPath);
	$recursiveIterator = new RecursiveIteratorIterator($dirIterator);
	foreach($recursiveIterator as $path) {
		if($path->isFile()) {
			unlink($path->getPathname());
		}
	}
}

prepareDownloadsDir(DOWNLOADS_DIR);

$mrHandler = new MultiRequest_Handler();
$mrHandler->setConnectionsLimit(CONNECTIONS_LIMIT);
$mrHandler->setTimeLimit(TIME_LIMIT);
$mrHandler->addOnRequestCompleteCallback('debugRequestComplete');
$mrHandler->addOnRequestCompleteCallback('saveCompleteRequestToFile');

foreach($urls as $url) {
	$request = new MultiRequest_Request($url);
	$mrHandler->pushRequestToQueue($request);
}

$startTime = time();

$mrHandler->exec();

debug('Total time: '.(time() - $startTime));