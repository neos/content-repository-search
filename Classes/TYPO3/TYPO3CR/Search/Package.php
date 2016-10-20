<?php
namespace TYPO3\TYPO3CR\Search;

/*
 * This file is part of the TYPO3.TYPO3CR.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Core\Booting\Step;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Package\Package as BasePackage;
use TYPO3\Flow\Persistence\Doctrine\PersistenceManager;
use TYPO3\TYPO3CR\Domain\Model\Node;
use TYPO3\TYPO3CR\Domain\Model\Workspace;

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
        $dispatcher->connect(\TYPO3\Flow\Core\Booting\Sequence::class, 'afterInvokeStep', function (Step $step) use ($package, $bootstrap) {
            if ($step->getIdentifier() === 'typo3.flow:reflectionservice') {
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
            $bootstrap->getSignalSlotDispatcher()->connect(Node::class, 'nodeAdded', Indexer\NodeIndexingManager::class, 'indexNode');
            $bootstrap->getSignalSlotDispatcher()->connect(Node::class, 'nodeUpdated', Indexer\NodeIndexingManager::class, 'indexNode');
            $bootstrap->getSignalSlotDispatcher()->connect(Node::class, 'nodeRemoved', Indexer\NodeIndexingManager::class, 'removeNode');
            // all publishing calls (Workspace, PublishingService) eventually trigger this - and publishing is triggered in various ways
            $bootstrap->getSignalSlotDispatcher()->connect(Workspace::class, 'afterNodePublishing', Indexer\NodeIndexingManager::class, 'indexNode', false);
            // make sure we always flush at the end, regardless of indexingBatchSize
            $bootstrap->getSignalSlotDispatcher()->connect(PersistenceManager::class, 'allObjectsPersisted', Indexer\NodeIndexingManager::class, 'flushQueues');
        }
    }
}

