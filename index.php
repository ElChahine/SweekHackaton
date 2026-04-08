#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$env = new Symfony\Component\Dotenv\Dotenv();
$env->load(__DIR__.'/.env');

$kernel = new \Walibuy\Sweeecli\Kernel();

$kernel->initialize();
$kernel->run();
