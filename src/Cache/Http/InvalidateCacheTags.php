<?php

/**
 * This file is part of contao-community-alliance/dc-general.
 *
 * (c) 2013-2022 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general
 * @author     Sven Baumann <baumann.sv@gmail.com>
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @author     Ingolf Steinhardt <info@e-spin.de>
 * @copyright  2013-2022 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general/blob/master/LICENSE LGPL-3.0-or-later
 * @filesource
 */

declare(strict_types=1);

namespace ContaoCommunityAlliance\DcGeneral\Cache\Http;

use ContaoCommunityAlliance\DcGeneral\Controller\ModelCollector;
use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\BasicDefinitionInterface;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;
use ContaoCommunityAlliance\DcGeneral\Event\InvalidHttpCacheTagsEvent;
use FOS\HttpCache\CacheInvalidator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * This is for purge the invalidate http cache tags.
 */
class InvalidateCacheTags implements InvalidateCacheTagsInterface
{
    /**
     * The http cache namespace.
     *
     * @var string
     */
    private $namespace;

    /**
     * The event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * The http cache manager.
     *
     * @var CacheInvalidator|null
     */
    private $cacheManager;

    /**
     * The cache tags.
     *
     * @var string[]
     */
    private $tags;

    /**
     * The constructor.
     *
     * @param string                   $namespace    The http cache namespace.
     * @param EventDispatcherInterface $dispatcher   The event dispatcher.
     * @param CacheInvalidator|null    $cacheManager The http cache manager.
     */
    public function __construct(
        string $namespace,
        EventDispatcherInterface $dispatcher,
        CacheInvalidator $cacheManager = null
    ) {
        $this->namespace    = $namespace;
        $this->dispatcher   = $dispatcher;
        $this->cacheManager = $cacheManager;
    }

    /**
     * {@inheritDoc}
     */
    public function purgeCacheTags(ModelInterface $model, EnvironmentInterface $environment): void
    {
        if (null === $this->cacheManager) {
            return;
        }

        $this->clearCacheTags();
        $this->addCurrentModelTag($model);
        $this->addParentModelTag($model, $environment);

        $event = new InvalidHttpCacheTagsEvent($environment);
        $event->setNamespace($this->namespace)->setTags($this->tags);
        $this->dispatcher->dispatch($event);

        $this->cacheManager->invalidateTags($this->cleanUpTags($event->getTags()));
    }

    /**
     * Add the cache tag for the current model.
     *
     * @param ModelInterface $model The current model.
     *
     * @return void
     */
    private function addCurrentModelTag(ModelInterface $model): void
    {
        $this->addModelTag($model);
    }

    /**
     * Add the cache tag for the parent model, if current model is a children of.
     *
     * @param ModelInterface       $model       The current model.
     * @param EnvironmentInterface $environment The dc general environment.
     *
     * @return void
     */
    private function addParentModelTag(ModelInterface $model, EnvironmentInterface $environment): void
    {
        if ((null === $environment->getParentDataDefinition())
        ) {
            return;
        }

        $definition = $environment->getDataDefinition()->getBasicDefinition();
        $collector  = new ModelCollector($environment);
        switch ($definition->getMode()) {
            case BasicDefinitionInterface::MODE_HIERARCHICAL:
                $this->addModelTag($collector->searchParentFromHierarchical($model));
                return;
            case BasicDefinitionInterface::MODE_PARENTEDLIST:
                $this->addModelTag($collector->searchParentOf($model));
                return;
            default:
        }
    }

    /**
     * Add the model tag.
     *
     * @param ModelInterface $model The model.
     *
     * @return void
     */
    private function addModelTag(ModelInterface $model): void
    {
        $modelNamespace = $this->namespace . $model->getProviderName();
        $this->tags[]   = $modelNamespace;
        $this->tags[]   = $modelNamespace . '.' . $model->getId();
    }

    /**
     * Clean up the tags. To be sure that there are no empty and double entries.
     *
     * @param array $tags The tags the should be cleaned up.
     *
     * @return string[]
     */
    private function cleanUpTags(array $tags): array
    {
        return \array_values(\array_filter(\array_unique($tags)));
    }

    /**
     * The cache tags are initially cleared. To avoid that already used tags are used again.
     *
     * @return void
     */
    private function clearCacheTags(): void
    {
        $this->tags = [];
    }
}
