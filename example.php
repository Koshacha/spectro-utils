<?php

function includeUtilites() {
    if (file_exists(IMGPATH . 'utils/class.php')) {
        require_once IMGPATH . 'utils/class.php';
    }

    return;
}

Utilities::enable(['route']);

Route::lazyPage('/about/:cat/:id', function($id, $cat, $options) {
    Route::useTemplate('deliveries');

    Route::setMeta([
        'title' => "About Us",
        'h1' => 'About Us 123',
        'description' => 'About Us 123'
    ]);

    return [
        '$template' => 'test',
        'TITLE' => "About Us: $cat",
        'DESCRIPTION' => 'About Us ' . $id
    ];
});