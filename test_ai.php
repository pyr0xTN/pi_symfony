<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();

$request = Symfony\Component\HttpFoundation\Request::create('/api/gemini/generate-description', 'POST', [], [], [], [], json_encode(['name' => 'Test', 'type' => 'hotel']));
$response = $kernel->handle($request);

echo $response->getContent();
