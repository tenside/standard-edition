<?php

/**
 * This file is part of tenside/standard-edition.
 *
 * (c) Christian Schiffler <https://github.com/discordier>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/standard-edition
 * @author     Christian Schiffler <https://github.com/discordier>
 * @copyright  Christian Schiffler <https://github.com/discordier>
 * @link       https://github.com/tenside/standard-edition
 * @license    https://github.com/tenside/standard-edition/blob/master/LICENSE MIT
 * @filesource
 */

use Tenside\Web\Application;

if (PHP_SAPI === 'cli') {
    echo 'Warning: This is the web interface of Tenside, it should not be invoked via the CLI version of PHP.'.PHP_EOL;
}

// FIXME: change this.
ini_set('display_errors', 1);

require __DIR__.'/../src/bootstrap.php';

$application = new Application();
$application->run();
