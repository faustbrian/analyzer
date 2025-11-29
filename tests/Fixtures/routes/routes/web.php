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
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

// Posts resource routes
Route::resource('posts', 'PostController')->names([
    'index' => 'posts.index',
    'create' => 'posts.create',
    'store' => 'posts.store',
    'show' => 'posts.show',
    'edit' => 'posts.edit',
    'update' => 'posts.update',
    'destroy' => 'posts.destroy',
]);

// User profile routes
Route::get('/profile', function () {
    return view('profile');
})->name('users.profile');

Route::get('/profile/edit', function () {
    return view('profile.edit');
})->name('users.edit');

// Admin routes with prefix
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::get('/users', function () {
        return view('admin.users.index');
    })->name('users.index');

    Route::get('/posts/create', function () {
        return view('admin.posts.create');
    })->name('posts.create');
});

// Nested resources
Route::resource('posts.comments', 'CommentController')->names([
    'index' => 'posts.comments.index',
    'store' => 'posts.comments.store',
]);

Route::resource('users.posts', 'UserPostController')->names([
    'index' => 'users.posts.index',
    'show' => 'users.posts.show',
]);

// Authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
