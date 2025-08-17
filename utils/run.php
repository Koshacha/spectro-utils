<?php

if (!defined('IMGPATH')) exit();

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (file_exists(IMGPATH . 'config.php')) {
    require_once IMGPATH . 'config.php';
}

function includeUtilities() {
    if (file_exists(__DIR__ . '/class.php')) {
        require_once __DIR__ . '/class.php';
    }
}

includeUtilities();

Modules::enable();

function runProgram() {
    if (file_exists(IMGPATH . 'app.php')) {
        require_once IMGPATH . 'app.php';
    }
    foreach (glob(IMGPATH . 'app.*.php') as $filename) {
        require_once $filename;
    }

    Route::exec();
}

function runActions() {
    if (file_exists(IMGPATH . 'functions.php')) {
        require_once IMGPATH . 'functions.php';
    }
    foreach (glob(IMGPATH . 'functions.*.php') as $filename) {
        require_once $filename;
    }

    Action::exec();
}
