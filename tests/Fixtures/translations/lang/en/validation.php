<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'max' => [
        'numeric' => 'The :attribute may not be greater than :max.',
        'string' => 'The :attribute may not be greater than :max characters.',
    ],
    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
        'name' => 'name',
    ],
];
