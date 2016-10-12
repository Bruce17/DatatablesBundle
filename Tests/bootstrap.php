<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$file = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($file)) {
    $file = __DIR__ . '/../../../../../../vendor/autoload.php';

    if (!file_exists($file)) {
        echo <<<EOT
You need to install the project dependencies using Composer:
$ wget http://getcomposer.org/composer.phar
OR
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install --dev
$ phpunit
EOT;
        exit(1);
    }
}
