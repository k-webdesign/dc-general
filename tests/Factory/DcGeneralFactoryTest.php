<?php

/**
 * This file is part of contao-community-alliance/dc-general-contao-frontend.
 *
 * (c) 2015-2019 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general-contao-frontend
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @copyright  2015-2019 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\Test\Factory;

use Contao\System;
use ContaoCommunityAlliance\DcGeneral\DataDefinitionContainerInterface;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;
use ContaoCommunityAlliance\DcGeneral\Factory\DcGeneralFactory;
use ContaoCommunityAlliance\DcGeneral\Test\TestCase;
use ContaoCommunityAlliance\Translator\TranslatorInterface;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * This class tests the DcGeneralFactory
 *
 * @covers \ContaoCommunityAlliance\DcGeneral\Factory\DcGeneralFactory
 */
class DcGeneralFactoryTest extends TestCase
{
    /**
     * Test that the build method works correctly.
     *
     * @return void
     */
    public function testCreateDcGeneral()
    {
        $eventDispatcher = new EventDispatcher();
        $mockTranslator = $this->getMockForAbstractClass(TranslatorInterface::class);

        System::setContainer($container = $this->getMockForAbstractClass(ContainerInterface::class));

        $mockDefinitionContainer = $this->getMockForAbstractClass(DataDefinitionContainerInterface::class);
        $container
            ->expects(self::once())
            ->method('get')
            ->with('cca.dc-general.data-definition-container')
            ->willReturn($mockDefinitionContainer);

        $mockDefinitionContainer
            ->expects(self::once())
            ->method('hasDefinition')
            ->with('test-container')
            ->willReturn(false);

        $cache = $this->createMock(Cache::class);

        /** @var TranslatorInterface $mockTranslator */
        $factory   = new DcGeneralFactory($cache);
        $dcGeneral = $factory
            ->setContainerName('test-container')
            ->setEventDispatcher($eventDispatcher)
            ->setTranslator($mockTranslator)
            ->createDcGeneral();

        self::assertInstanceOf(EnvironmentInterface::class, $dcGeneral->getEnvironment());
    }
}
