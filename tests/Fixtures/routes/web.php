<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Route::get('/', 'HomeController@index')->name('home');
Route::get('/posts', 'PostController@index')->name('posts.index');
Route::get('/posts/{post}', 'PostController@show')->name('posts.show');
Route::resource('users', 'UserController');

// Unicode routes for testing
Route::get('/ru', fn () => 'Russian')->name('сайт.главная');
Route::get('/cn', fn () => 'Chinese')->name('网站.首页');
Route::get('/ar', fn () => 'Arabic')->name('موقع.الرئيسية');

// Special character routes
Route::get('/special', fn () => 'Special')->name('user-profile.show');
Route::get('/admin', fn () => 'Admin')->name('admin_dashboard.index');
Route::post('/api-v2/users', fn () => 'Create user')->name('api-v2_users.create');
