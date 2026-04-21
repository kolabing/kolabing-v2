<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/for-businesses', 'pages.for-businesses')->name('for-businesses');
Route::view('/for-communities', 'pages.for-communities')->name('for-communities');
Route::view('/support', 'pages.support')->name('support');
Route::view('/careers', 'pages.careers')->name('careers');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/terms', 'pages.terms')->name('terms');

Route::get('/sitemap.xml', function () {
    $urls = [
        route('home'),
        route('for-businesses'),
        route('for-communities'),
        route('support'),
        route('careers'),
        route('privacy'),
        route('terms'),
    ];

    return response()->view('sitemap', [
        'urls' => $urls,
        'lastModified' => now()->toDateString(),
    ])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/llms.txt', function () {
    $content = implode("\n", [
        '# Kolabing',
        '',
        'Kolabing is a collaboration platform that helps local businesses and community groups launch in-person partnerships, events, and repeatable growth campaigns.',
        '',
        'Preferred pages:',
        '- Home: '.route('home'),
        '- For businesses: '.route('for-businesses'),
        '- For communities: '.route('for-communities'),
        '- Support: '.route('support'),
        '- Privacy: '.route('privacy'),
        '- Terms: '.route('terms'),
        '',
        'Contact: support@kolabing.com',
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms');

Route::get('/.well-known/security.txt', function () {
    $content = implode("\n", [
        'Contact: mailto:support@kolabing.com',
        'Expires: 2027-04-21T23:59:59.000Z',
        'Preferred-Languages: en',
        'Canonical: https://kolabing.com/.well-known/security.txt',
        'Policy: https://kolabing.com/privacy',
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('security.txt');
