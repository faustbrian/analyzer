<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Translations;

use function __;
use function config;
use function trans;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigKeys
{
    public function dynamicFromConfig(): string
    {
        return trans(config('translation.default_message'));
    }

    public function fromMethod(): string
    {
        return __($this->getTranslationKey());
    }

    private function getTranslationKey(): string
    {
        return 'messages.welcome';
    }
}
