<?php
require_once 'vendor/autoload.php';

use App\Repository\ArticleRepository;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$repo = $container->get(ArticleRepository::class);
$articles = $repo->findAll();

echo "Number of articles: " . count($articles) . "\n";
echo "Memory usage: " . memory_get_usage() . " bytes\n";
echo "Peak memory: " . memory_get_peak_usage() . " bytes\n";

// Check if any article has huge data
foreach ($articles as $article) {
    $size = strlen(serialize($article));
    if ($size > 10000) {
        echo "Large article ID {$article->getId()}: {$size} bytes\n";
    }
}