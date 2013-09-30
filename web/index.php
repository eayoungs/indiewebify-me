<?php

namespace Indieweb\IndiewebifyMe;

ob_start();
require __DIR__ . '/../vendor/autoload.php';
ob_end_clean();

use BarnabyWalters\Mf2;
use Guzzle;
use mf2\Parser as MfParser;
use Silex;
use Symfony\Component\HttpFoundation as Http;

function renderTemplate($template, array $__templateData = array()) {
	$render = function ($__path, $render=null) use ($__templateData) {
		ob_start();
		extract($__templateData);
		unset($__templateData);
		include __DIR__ . '/../templates/' . $__path . '.php';
		return ob_get_clean();
	};
	
	return $render($template, $render);
}

function render($template, array $data = array()) {
	$isHtml = pathinfo($template, PATHINFO_EXTENSION) === 'html';
	$out = '';
	
	if ($isHtml)
		$out .= renderTemplate('header.html', $data);
	$out .= renderTemplate($template, $data);
	if ($isHtml)
		$out .= renderTemplate('footer.html', $data);
	return $out;
}

function httpGet($url) {
	$client = new Guzzle\Http\Client();
	ob_start();
	$url = web_address_to_uri($url, true);
	ob_end_clean();
	
	try {
		$response = $client->get($url)->send();
		return array($response, null);
	} catch (Guzzle\Common\Exception\GuzzleException $e) {
		return array(null, $e);
	}
}

function fetchMf($url) {
	list($resp, $err) = httpGet($url);
	if ($err)
		return array(null, $err);
	
	$parser = new MfParser((string) $resp, $url);
	return array($parser->parse(), null);
}

// Web server setup

// Route static assets from CLI server
if (PHP_SAPI === 'cli-server') {
	error_reporting(0);
	
	if (file_exists(__DIR__ . $_SERVER['REQUEST_URI']) and !is_dir(__DIR__ . $_SERVER['REQUEST_URI'])) {
		return false;
	}
}

$app = new Silex\Application();

$app->get('/', function () {
	return render('index.html');
});

$app->get('/validate-rels/', function (Http\Request $request) {
	if (!$request->query->has('url')) {
		return render('validate-rels.html');
	} else {
		$url = $request->query->get('url');
		list($mfs, $err) = fetchMf($url);
		
		if ($err)
			return render('validate-rels.html', array(
				'error' => array(
					'message' => $err->getMessage()
				),
				'url' => $url
			));
		
		return render('validate-rels.html', array(
			'rels' => $mfs['rels']['me'],
			'url' => $url
		));
	}
});

$app->get('/validate-h-card/', function (Http\Request $request) {
	if (!$request->query->has('url')) {
		return render('validate-h-card.html');
	} else {
		$url = $request->query->get('url');
		list($mfs, $err) = fetchMf($url);
		
		$errorResponse = function($message) use ($url) {
			return render('validate-h-card.html', array(
				'error' => array(
					'message' => $message
				),
				'url' => $url
			));
		};
		
		if ($err)
			return $errorResponse($err->getMessage());
		
		$hCards = Mf2\findMicroformatsByType($mfs, 'h-card');
		
		if (count($hCards) === 0)
			return $errorResponse('No h-cards found — check your classnames');
		
		return render('validate-h-card.html', array(
			'hCard' => $hCards[0],
			'url' => $url
		));
	}
});

$app->get('/validate-h-entry/', function (Http\Request $request) {
	if (!$request->query->has('url')) {
		return render('validate-h-entry.html');
	} else {
		$url = $request->query->get('url');
		list($mfs, $err) = fetchMf($url);
		
		$errorResponse = function($message) use ($url) {
			return render('validate-h-entry.html', array(
				'error' => array(
					'message' => $message
				),
				'url' => $url
			));
		};
		
		if ($err)
			return $errorResponse($err->getMessage());
		
		$hEntries = Mf2\findMicroformatsByType($mfs, 'h-entry');
		
		if (count($hEntries) === 0)
			return $errorResponse('No h-entries found — check your classnames');
		
		return render('validate-h-entry.html', array(
			'hEntry' => $hEntries[0],
			'url' => $url
		));
	}
});

$app->run();
