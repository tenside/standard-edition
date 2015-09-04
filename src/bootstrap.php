<?php

/**
 * This file is part of tenside/standard-edition.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/standard-edition
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/standard-edition/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/standard-edition
 * @filesource
 */

/**
 * Try to include a file if it exists.
 *
 * @param string $file The file to include.
 *
 * @return bool|mixed The include result on success, false otherwise.
 */
function includeIfExists($file)
{
    return file_exists($file) ? include $file : false;
}

if ((!$loader = includeIfExists(__DIR__.'/../vendor/autoload.php'))
    && (!$loader = includeIfExists(__DIR__.'/../../../autoload.php'))
) {
    echo 'You must set up the project dependencies, run the following commands:'.PHP_EOL.
        'curl -sS https://getcomposer.org/installer | php'.PHP_EOL.
        'php composer.phar install'.PHP_EOL;
    exit(1);
}

// "git" binary not found when no PATH environment is present.
// https://github.com/contao-community-alliance/composer-client/issues/54
if (!getenv('PATH')) {
    if (defined('PHP_WINDOWS_VERSION_BUILD')) {
        putenv('PATH=%SystemRoot%\system32;%SystemRoot%;%SystemRoot%\System32\Wbem');
    } else {
        putenv('PATH=/opt/local/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin');
    }
}

return $loader;
