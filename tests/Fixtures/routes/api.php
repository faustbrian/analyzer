<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Route;

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
Route::get('/api/users', 'Api\UserController@index')->name('api.users');
Route::get('/api/posts', 'Api\PostController@index')->name('api.posts');

// API versioned routes
Route::prefix('api/v1')->name('api.v1.')->group(function (): void {
    Route::get('/users', fn (): string => 'v1 users')->name('users.index');
    Route::get('/posts', fn (): string => 'v1 posts')->name('posts.index');
    Route::post('/auth/login', fn (): string => 'v1 login')->name('auth.login');
});

Route::prefix('api/v2')->name('api.v2.')->group(function (): void {
    Route::get('/users', fn (): string => 'v2 users')->name('users.show');
    Route::get('/posts/{id}', fn (): string => 'v2 post')->name('posts.show');
});
