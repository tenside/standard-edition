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

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Tenside\CoreBundle\TensideCoreBundle;
use Tenside\Ui\Bundle\TensideUiBundle;

/**
 * This class is the main kernel for the application.
 */
class AppKernel extends Kernel
{
    /**
     * The dependency container.
     *
     * @var Symfony\Component\DependencyInjection\Container
     */
    protected $container;

    /**
     * {@inheritDoc}
     */
    public function registerBundles()
    {
        return [
            new SecurityBundle(),
            new TensideCoreBundle(),
            new TensideUiBundle(),
            new FrameworkBundle(),
            new MonologBundle(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeContainer()
    {
        parent::initializeContainer();
    }

    /**
     * {@inheritDoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        // TODO: Can we really omit loading of the config in phar environment?
        // The container is already built, therefore it should be safe.
        if (!\Phar::running()) {
            $loader->load(__DIR__ . '/config/config_' . $this->getEnvironment() . '.yml');
        }

        $loader->load(function (ContainerBuilder $container) {
            $container->setDefinition(
                'application',
                (new Definition('Tenside\\Web\\Application'))
                    ->setFactory('Tenside\\StandardEdition\\ApplicationFactory::createApplication')
            );
        });
    }
}
