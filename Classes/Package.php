<?php
namespace Neos\ContentRepository\Search;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Core\Booting\Step;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\PublishingService;
use Neos\ContentRepository\Domain\Model\Workspace;

/**
 * The Search Package
 */
class Package extends BasePackage
{
    /**
     * Invokes custom PHP code directly after the package manager has been initialized.
     *
     * @param Bootstrap $bootstrap The current bootstrap
     *
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $package = $this;
        $dispatcher->connect(Sequence::class, 'afterInvokeStep', function (Step $step) use ($package, $bootstrap) {
            if ($step->getIdentifier() === 'neos.flow:reflectionservice') {
                $package->registerIndexingSlots($bootstrap);
            }
        });
    }

    /**
     * Registers slots for signals in order to be able to index nodes
     *
     * @param Bootstrap $bootstrap
     */
    public function registerIndexingSlots(Bootstrap $bootstrap)
    {
        $configurationManager = $bootstrap->getObjectManager()->get(ConfigurationManager::class);
        $settings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $this->getPackageKey());
        if (isset($settings['realtimeIndexing']['enabled']) && $settings['realtimeIndexing']['enabled'] === true) {
            // handle changes to nodes
            $bootstrap->getSignalSlotDispatcher()->connect(Node::class, 'nodeAdded', Indexer\NodeIndexingManager::class, 'indexNode', false);
            $bootstrap->getSignalSlotDispatcher()->connect(Node::class, 'nodeUpdated', Indexer\NodeIndexingManager::class, 'indexNode', false);
            $bootstrap->getSignalSlotDispatcher()->connect(Node::class, 'nodeRemoved', Indexer\NodeIndexingManager::class, 'removeNode', false);
            // discard needs to be handled separately as it does not emit `nodeRemoved`
            $bootstrap->getSignalSlotDispatcher()->connect(PublishingService::class, 'nodeDiscarded', Indexer\NodeIndexingManager::class, 'removeNode', false);
            // all publishing calls (Workspace, PublishingService) eventually trigger this - and publishing is triggered in various ways
            $bootstrap->getSignalSlotDispatcher()->connect(Workspace::class, 'afterNodePublishing', Indexer\NodeIndexingManager::class, 'indexNode', false);
            // make sure we always flush at the end, regardless of indexingBatchSize
            $bootstrap->getSignalSlotDispatcher()->connect(PersistenceManager::class, 'allObjectsPersisted', Indexer\NodeIndexingManager::class, 'flushQueues', false);
        }
    }
}
