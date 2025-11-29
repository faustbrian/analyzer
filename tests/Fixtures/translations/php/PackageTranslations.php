<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Translations;

use function __;
use function trans;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class PackageTranslations
{
    public function vendorMessages(): array
    {
        return [
            'welcome' => trans('package::messages.welcome'),
            'error' => __('vendor-package::errors.404'),
            'info' => trans('package::info.about'),
        ];
    }
}
