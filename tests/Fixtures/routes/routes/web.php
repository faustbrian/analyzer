<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn (): Factory|View => view('welcome'))->name('home');

Route::get('/about', fn (): Factory|View => view('about'))->name('about');

Route::get('/contact', fn (): Factory|View => view('contact'))->name('contact');

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
Route::get('/profile', fn (): Factory|View => view('profile'))->name('users.profile');

Route::get('/profile/edit', fn (): Factory|View => view('profile.edit'))->name('users.edit');

// Admin routes with prefix
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', fn (): Factory|View => view('admin.dashboard'))->name('dashboard');

    Route::get('/users', fn (): Factory|View => view('admin.users.index'))->name('users.index');

    Route::get('/posts/create', fn (): Factory|View => view('admin.posts.create'))->name('posts.create');
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
Route::get('/login', fn (): Factory|View => view('auth.login'))->name('login');

Route::get('/register', fn (): Factory|View => view('auth.register'))->name('register');

Route::get('/dashboard', fn (): Factory|View => view('dashboard'))->name('dashboard');
