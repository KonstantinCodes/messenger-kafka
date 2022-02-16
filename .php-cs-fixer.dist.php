<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('var')
    ->notPath('config/bundles.php')
    ->notPath('config/preload.php')
;

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PhpCsFixer:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'yoda_style' => false,
        'single_import_per_statement' => false,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
    ])
    ->setFinder($finder)
    ;

return $config;