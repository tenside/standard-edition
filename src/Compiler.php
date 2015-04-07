<?php

/**
 * This file is part of tenside/core.
 *
 * (c) Christian Schiffler <https://github.com/discordier>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/core
 * @author     Christian Schiffler <https://github.com/discordier>
 * @copyright  Christian Schiffler <https://github.com/discordier>
 * @link       https://github.com/tenside/core
 * @license    https://github.com/tenside/core/blob/master/LICENSE MIT
 * @filesource
 */

namespace Tenside\StandardEdition;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * The Compiler class compiles tenside into a phar.
 *
 * @author Christian Schiffler <https://github.com/discordier>
 */
class Compiler extends \Tenside\Compiler
{
    /**
     * Detect the path to the vendor root.
     *
     * @return string
     *
     * @throws \RuntimeException When the directory can not be determined.
     */
    protected function getTensideUiDir()
    {
        if (is_dir($this->getVendorDir() . '/tenside/ui')) {
            return realpath($this->getVendorDir() . '/tenside/ui');
        }

        throw new \RuntimeException('Can not locate the tenside/ui root.');
    }

    /**
     * {@inheritDoc}
     */
    protected function addFiles(\Phar $phar)
    {
        $uiDir   = $this->getTensideUiDir();
        $destDir = __DIR__ . '/../assets';

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destDir = realpath($destDir);
        $this->logger->notice('Performing npm install...');
        $process = new Process('npm install --save-dev', $uiDir);
        $process->setTimeout(null)->mustRun();
        $this->logger->notice($process->getOutput());
        $this->logger->notice('Performing gulp install...');
        $process = new Process($uiDir . '/node_modules/.bin/gulp install', $uiDir);
        $process->setTimeout(null)->mustRun();
        $this->logger->notice($process->getOutput());
        $this->logger->notice('Performing gulp build...');
        $process = new Process(
            $uiDir . '/node_modules/.bin/gulp build',
            $uiDir,
            array_merge(
                $_SERVER,
                [
                    'DEST_DIR'         => $destDir,
                    //'TENSIDE_API'      => '',
                    'TENSIDE_VERSION'  => $this->getTensideVersion()['version'],
                    'COMPOSER_VERSION' => $this->getComposerVersion()['version'],
                ]
            )
        );
        $process->setTimeout(null)->mustRun();
        $this->logger->notice($process->getOutput());

        $finder = new Finder();
        $finder
            ->files()
            ->ignoreVCS(true)
            ->name('*.css')
            ->name('*.js')
            ->name('*.map')
            ->name('*.png')
            ->name('*.svg')
            ->name('*.html')
            ->name('*.otf')
            ->name('*.eot')
            ->name('*.ttf')
            ->name('*.woff')
            ->in($destDir);
        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->notName('stub.php')
            ->notName('app.php')
            ->in(dirname(__DIR__) . '/src');
        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        if (file_exists(dirname(__DIR__) . '/web/app.php') && !$phar->offsetExists('web/app.php')) {
            $this->addFile($phar, new \SplFileInfo(dirname(__DIR__) . '/web/app.php'));
        }

        // Now add the parent files.
        parent::addFiles($phar);
    }
}
