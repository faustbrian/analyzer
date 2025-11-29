<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->name('api.')->group(function (): void {
    // V1 API routes
    Route::prefix('v1')->name('v1.')->group(function (): void {
        Route::get('/users', function () {
            return response()->json(['users' => []]);
        })->name('users.index');

        Route::get('/users/{user}', function ($user) {
            return response()->json(['user' => $user]);
        })->name('users.show');

        Route::get('/posts', function () {
            return response()->json(['posts' => []]);
        })->name('posts.index');

        Route::get('/auth/login', function () {
            return response()->json(['message' => 'login']);
        })->name('auth.login');
    });

    // V2 API routes
    Route::prefix('v2')->name('v2.')->group(function (): void {
        Route::get('/posts/{post}', function ($post) {
            return response()->json(['post' => $post]);
        })->name('posts.show');
    });

    // General API routes
    Route::get('/users', function () {
        return response()->json(['users' => []]);
    })->name('users.index');

    Route::get('/posts', function () {
        return response()->json(['posts' => []]);
    })->name('posts');
});
