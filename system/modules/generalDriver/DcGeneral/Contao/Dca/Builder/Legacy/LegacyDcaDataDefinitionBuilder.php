<?php
/**
 * PHP version 5
 * @package    generalDriver
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace DcGeneral\Contao\Dca\Builder\Legacy;

use ContaoDataProviderInformation;
use DcGeneral\DataDefinition\ContainerInterface;
use DcGeneral\DataDefinition\Section\BasicSectionInterface;
use DcGeneral\DataDefinition\Section\DefaultBasicSection;
use DcGeneral\DataDefinition\Section\DefaultDataProviderSection;

/**
 * Build the container config from legacy DCA syntax.
 */
class LegacyDcaDataDefinitionBuilder extends DcaReadingDataDefinitionBuilder
{
	const PRIORITY = 100;

	protected $dca;

	/**
	 * {@inheritDoc}
	 */
	public function build(ContainerInterface $container)
	{
		if (!$this->loadDca($container->getName()))
		{
			return;
		}

		$this->parseBasicSection($container);
		$this->parseDataProvider($container);
	}

	protected function parseBasicSection(ContainerInterface $container)
	{
		// parse data provider
		if ($container->hasBasicSection())
		{
			$config = $container->getBasicSection();
		}
		else
		{
			$config = new DefaultBasicSection();
			$container->setBasicSection($config);
		}

		switch ($this->getFromDca('list/sorting/mode'))
		{
			case 0: // Records are not sorted
			case 1: // Records are sorted by a fixed field
			case 2: // Records are sorted by a switchable field
			case 3: // Records are sorted by the parent table
				$config->setMode(BasicSectionInterface::MODE_FLAT);
				break;
			case 4: // Displays the child records of a parent record (see style sheets module)
				$config->setMode(BasicSectionInterface::MODE_PARENTEDLIST);
				break;
			case 5: // Records are displayed as tree (see site structure)
			case 6: // Displays the child records within a tree structure (see articles module)
				$config->setMode(BasicSectionInterface::MODE_HIERARCHICAL);
				break;
			default:
		}

		if (($switchToEdit = $this->getFromDca('config/switchToEdit')) !== null)
		{
			$config->setSwitchToEditEnabled((bool) $switchToEdit);
		}
	}

	/**
	 * This method parses all data provider related information from Contao legacy data container arrays.
	 *
	 * @param ContainerInterface $container
	 *
	 * @return void
	 */
	protected function parseDataProvider(ContainerInterface $container)
	{
		// parse data provider
		if ($container->hasDataProviderSection())
		{
			$config = $container->getDataProviderSection();
		}
		else
		{
			$config = new DefaultDataProviderSection();
			$container->setDataProviderSection($config);
		}

		if (($parentTable = $this->getFromDca('config/ptable')) !== null)
		{
			// Check config if it already exists, if not, add it.
			if (!$config->hasInformation($parentTable))
			{
				$providerInformation = new ContaoDataProviderInformation();
				$providerInformation->setName($parentTable);
				$config->addInformation($providerInformation);
			}
			else
			{
				$providerInformation = $config->getInformation($parentTable);
			}

			if ($providerInformation instanceof ContaoDataProviderInformation)
			{
				$providerInformation
					->setTableName($parentTable)
					->setInitializationData(array(
						'source' => $container->getName()
					));

				$container->getBasicSection()->setRootDataProvider($parentTable);
			}
		}

		// Check config if it already exists, if not, add it.
		if (!$config->hasInformation($container->getName()))
		{
			$providerInformation = new ContaoDataProviderInformation();
			$providerInformation->setName($container->getName());
			$config->addInformation($providerInformation);
		}
		else
		{
			$providerInformation = $config->getInformation($container->getName());
		}

		if ($providerInformation instanceof ContaoDataProviderInformation)
		{
			$providerInformation
				->setTableName($container->getName())
				->setInitializationData(array(
					'source' => $container->getName()
				))
				->isVersioningEnabled((bool)$this->getFromDca('config/enableVersioning'));

			$container->getBasicSection()->setDataProvider($container->getName());
		}
	}
}
