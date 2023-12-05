<?php

declare(strict_types=1);

/*
 * This file is part of the CFONB Parser package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $config): void {
    $config->importShortClasses();
    $config->importNames();

    $config->paths([
        __DIR__ . '/src',
    ]);

    $config->import(LevelSetList::UP_TO_PHP_80);
    $config->import(SetList::CODE_QUALITY);
};
