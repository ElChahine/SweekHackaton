#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$env = new Symfony\Component\Dotenv\Dotenv();
$env->load(__DIR__.'/.env');

echo "Ma clé est : " . ($_ENV['CLAUDE_API_KEY'] ?? 'NON TROUVÉE');

$kernel = new \Walibuy\Sweeecli\Kernel();

$kernel->initialize();
$kernel->run();
