<?php
require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$httpClient = $container->get('http_client');
$key = $_ENV['GEMINI_API_KEY'];

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'hello']
            ]
        ]
    ]
];

try {
    $response = $httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent', [
        'query' => ['key' => $key],
        'json' => $payload,
    ]);
    echo $response->getContent();
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
