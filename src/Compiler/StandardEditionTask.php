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

namespace Tenside\StandardEdition\Compiler;

use Symfony\Component\Finder\Finder;
use Tenside\Compiler;
use Tenside\Compiler\AbstractTask;

/**
 * This Compiler task adds all content from tenside/standard-edition into the phar.
 *
 * @author Christian Schiffler <https://github.com/discordier>
 */
class StandardEditionTask extends AbstractTask
{
    /**
     * {@inheritDoc}
     */
    public function compile()
    {
        $root = $this->getPackageRoot('tenside/standard-edition');

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('StandardEditionTask.php')
            ->in($root . '/src');
        foreach ($finder as $file) {
            $this->addFile($file);
        }

        $this->addTensideBin();
    }

    /**
     * Add the tenside main binary.
     *
     * @return void
     */
    private function addTensideBin()
    {
        $root = $this->getPackageRoot('tenside/standard-edition');

        $content = file_get_contents($root . '/bin/tenside');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $this->addFileContent('bin/tenside', $content);

        $this->addFile(new \SplFileInfo($root . '/web/app.php'), true, 'web/app.php');
    }
}
