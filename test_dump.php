<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();

$request = Symfony\Component\HttpFoundation\Request::create('/hotel/new', 'GET');
$response = $kernel->handle($request);

file_put_contents('test_hotel_output.html', $response->getContent());
echo "OK\n";
