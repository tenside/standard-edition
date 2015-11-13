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

namespace Tenside\StandardEdition\Command;

use Composer\Command\Command;
use Composer\Composer;
use Composer\Factory;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;
use Composer\Downloader\FilesystemException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Update the phar.
 *
 * This class is a heavily influenced (copied and modified) version of the composer self-update command
 *
 * @see Composer\Command\SelfUpdateCommand
 */
class SelfUpdateCommand extends Command
{
    /**
     * Where whe are hosted.
     */
    const HOMEPAGE = 'tenside.org';

    /**
     * Backup file name.
     */
    const OLD_INSTALL_EXT = '-old.phar';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('tenside:self-update')
            ->setAliases(['tenside:selfupdate'])
            ->setDescription('Updates tenside.phar to the latest version.')
            ->setDefinition(array(
                new InputOption(
                    'rollback',
                    'r',
                    InputOption::VALUE_NONE,
                    'Revert to an older installation of tenside'
                ),
                new InputOption(
                    'clean-backups',
                    null,
                    InputOption::VALUE_NONE,
                    'Delete old backups during an update. ' .
                    'This makes the current version of tenside the only backup available after the update'
                ),
                new InputArgument('version', InputArgument::OPTIONAL, 'The version to update to'),
                new InputOption('no-progress', null, InputOption::VALUE_NONE, 'Do not output download progress.'),
            ))
            ->setHelp(
                sprintf(
                    'The <info>self-update</info> command checks tenside.org for newer
versions of tenside and if found, installs the latest.

<info>php tenside.phar self-update</info>

',
                    self::HOMEPAGE
                )
            );
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     *
     * @throws FilesystemException When the temporary directory or local file are not writable.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $baseUrl          = (extension_loaded('openssl') ? 'https' : 'http') . '://' . self::HOMEPAGE;
        $config           = Factory::createConfig();
        $inputOutput      = $this->getIO();
        $remoteFilesystem = new RemoteFilesystem($inputOutput, $config);
        $cacheDir         = $config->get('cache-dir');
        $rollbackDir      = $config->get('home');
        $localFilename    = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];

        // check if current dir is writable and if not try the cache dir from settings
        $tmpDir = is_writable(dirname($localFilename)) ? dirname($localFilename) : $cacheDir;

        // check for permissions in local filesystem before start connection process
        if (!is_writable($tmpDir)) {
            throw new FilesystemException(
                sprintf(
                    'Tenside update failed: the "%s" directory used to download the temp file could not be written',
                    $tmpDir
                )
            );
        }
        if (!is_writable($localFilename)) {
            throw new FilesystemException('Tenside update failed: the "'.$localFilename.'" file could not be written');
        }

        if ($input->getOption('rollback')) {
            return $this->rollback($rollbackDir, $localFilename);
        }

        $latestVersion = trim($remoteFilesystem->getContents(self::HOMEPAGE, $baseUrl. '/version', false));
        $updateVersion = $input->getArgument('version') ?: $latestVersion;

        if (preg_match('{^[0-9a-f]{40}$}', $updateVersion) && $updateVersion !== $latestVersion) {
            $inputOutput->writeError(
                '<error>You can not update to a specific SHA-1 as those phars are not available for download</error>'
            );

            return 1;
        }

        if (Composer::VERSION === $updateVersion) {
            $inputOutput->writeError('<info>You are already using tenside version '.$updateVersion.'.</info>');

            return 0;
        }

        $tempFilename = $tmpDir . '/' . basename($localFilename, '.phar').'-temp.phar';
        $backupFile   = sprintf(
            '%s/%s-%s%s',
            $rollbackDir,
            strtr(Composer::RELEASE_DATE, ' :', '_-'),
            preg_replace('{^([0-9a-f]{7})[0-9a-f]{33}$}', '$1', Composer::VERSION),
            self::OLD_INSTALL_EXT
        );

        $inputOutput->writeError(sprintf('Updating to version <info>%s</info>.', $updateVersion));
        $remoteFilename = $baseUrl .
            preg_match('{^[0-9a-f]{40}$}', $updateVersion)
                ? '/tenside.phar'
                : sprintf('/download/%s/tenside.phar', $updateVersion);
        $remoteFilesystem->copy(self::HOMEPAGE, $remoteFilename, $tempFilename, !$input->getOption('no-progress'));
        if (!file_exists($tempFilename)) {
            $inputOutput->writeError(
                '<error>The download of the new tenside version failed for an unexpected reason</error>'
            );

            return 1;
        }

        // remove saved installations of tenside
        if ($input->getOption('clean-backups')) {
            $this->cleanupOldBackups($rollbackDir);
        }

        if ($err = $this->setLocalPhar($localFilename, $tempFilename, $backupFile)) {
            $inputOutput->writeError('<error>The file is corrupted ('.$err->getMessage().').</error>');
            $inputOutput->writeError('<error>Please re-run the self-update command to try again.</error>');

            return 1;
        }

        if (file_exists($backupFile)) {
            $inputOutput->writeError(
                'Use <info>tenside self-update --rollback</info> to return to version ' . Composer::VERSION
            );

            return 0;
        }

        $inputOutput->writeError(
            sprintf(
                '<warning>A backup of the current version could not be written to %s, ' .
                'no rollback possible</warning>',
                $backupFile
            )
        );

        return 0;
    }

    /**
     * Rollback to the previous version.
     *
     * @param string $rollbackDir   The directory where the rollback files are located.
     *
     * @param string $localFilename The file to rollback to.
     *
     * @return int
     *
     * @throws \UnexpectedValueException If the version could not be found.
     *
     * @throws FilesystemException       If the rollback directory is not writable.
     */
    protected function rollback($rollbackDir, $localFilename)
    {
        $rollbackVersion = $this->getLastBackupVersion($rollbackDir);
        if (!$rollbackVersion) {
            throw new \UnexpectedValueException(
                sprintf(
                    'Composer rollback failed: no installation to roll back to in "%s"',
                    $rollbackDir
                )
            );
        }

        if (!is_writable($rollbackDir)) {
            throw new FilesystemException(
                sprintf(
                    'Composer rollback failed: the "%s" dir could not be written to',
                    $rollbackDir
                )
            );
        }

        $old = $rollbackDir . '/' . $rollbackVersion . self::OLD_INSTALL_EXT;

        if (!is_file($old)) {
            throw new FilesystemException('Composer rollback failed: "'.$old.'" could not be found');
        }
        if (!is_readable($old)) {
            throw new FilesystemException('Composer rollback failed: "'.$old.'" could not be read');
        }

        $oldFile     = sprintf('%s/"%s"', $rollbackDir, $rollbackVersion, self::OLD_INSTALL_EXT);
        $inputOutput = $this->getIO();
        $inputOutput->writeError(sprintf('Rolling back to version <info>%s</info>.', $rollbackVersion));
        if ($err = $this->setLocalPhar($localFilename, $oldFile)) {
            $inputOutput->writeError(
                sprintf(
                    '<error>The backup file was corrupted (%s) and has been removed.</error>',
                    $err->getMessage()
                )
            );

            return 1;
        }

        return 0;
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * Update the local file with the new file optionally creating a backup first.
     *
     * If an exception occurs that is not returned.
     *
     * @param string $localFilename The local filename.
     *
     * @param string $newFilename   The new file name.
     *
     * @param null   $backupTarget  The backup file name.
     *
     * @return \Exception|null
     *
     * @throws \PharException            When the phar is invalid.
     *
     * @throws \UnexpectedValueException If the phar archive can't be opened.
     */
    protected function setLocalPhar($localFilename, $newFilename, $backupTarget = null)
    {
        try {
            // @codingStandardsIgnoreStart
            @chmod($newFilename, fileperms($localFilename));
            // @codingStandardsIgnoreEnd
            if (!ini_get('phar.readonly')) {
                // test the phar validity
                $phar = new \Phar($newFilename);
                // free the variable to unlock the file
                unset($phar);
            }

            // copy current file into installations dir
            if ($backupTarget && file_exists($localFilename)) {
                // @codingStandardsIgnoreStart
                @copy($localFilename, $backupTarget);
                // @codingStandardsIgnoreEnd
            }

            rename($newFilename, $localFilename);
        } catch (\Exception $e) {
            if ($backupTarget) {
                // @codingStandardsIgnoreStart
                @unlink($newFilename);
                // @codingStandardsIgnoreEnd
            }
            if (!$e instanceof \UnexpectedValueException && !$e instanceof \PharException) {
                throw $e;
            }

            return $e;
        }

        return null;
    }

    /**
     * Retrieve most recent backup version.
     *
     * @param string $rollbackDir The directory where the rollback files are contained within.
     *
     * @return bool|string
     */
    protected function getLastBackupVersion($rollbackDir)
    {
        $finder = $this->getOldInstallationFinder($rollbackDir);
        $finder->sortByName();
        $files = iterator_to_array($finder);

        if (count($files)) {
            return basename(end($files), self::OLD_INSTALL_EXT);
        }

        return false;
    }

    /**
     * Create a finder instance that is capable of finding
     *
     * @param string $rollbackDir The directory where the rollback files are contained within.
     *
     * @return Finder
     */
    protected function getOldInstallationFinder($rollbackDir)
    {
        $finder = Finder::create()
            ->depth(0)
            ->files()
            ->name('*' . self::OLD_INSTALL_EXT)
            ->in($rollbackDir);

        return $finder;
    }

    /**
     * Clean the backup directory.
     *
     * @param string $rollbackDir The backup directory.
     *
     * @return void
     */
    protected function cleanupOldBackups($rollbackDir)
    {
        $finder = $this->getOldInstallationFinder($rollbackDir);

        $fileSystem = new Filesystem;
        foreach ($finder as $file) {
            $file = (string) $file;
            $this->getIO()->writeError('<info>Removing: ' . $file . '</info>');
            $fileSystem->remove($file);
        }
    }
}
