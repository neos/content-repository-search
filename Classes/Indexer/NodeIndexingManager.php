<?php

namespace Neos\ContentRepository\Search\Indexer;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

/**
 * Indexer for Content Repository Nodes. Manages an indexing queue to allow for deferred indexing.
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexingManager
{
    /**
     * @var \SplObjectStorage<Node>
     */
    protected $nodesToBeIndexed;

    /**
     * @var \SplObjectStorage<Node>
     */
    protected $nodesToBeRemoved;

    /**
     * @var array<WorkspaceName>
     */
    protected $targetWorkspaceNamesForNodesToBeIndexed = [];

    /**
     * @var array<WorkspaceName>
     */
    protected $targetWorkspaceNamesForNodesToBeRemoved = [];

    /**
     * the indexing batch size (from the settings)
     *
     * @var integer
     */
    protected $indexingBatchSize;

    /**
     * @Flow\Inject
     * @var NodeIndexerInterface
     */
    protected $nodeIndexer;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->nodesToBeIndexed = new \SplObjectStorage();
        $this->nodesToBeRemoved = new \SplObjectStorage();
    }

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->indexingBatchSize = $settings['indexingBatchSize'];
    }

    /**
     * Schedule a node for indexing
     *
     * @param Node $node
     * @param WorkspaceName|null $targetWorkspace In case this is triggered during publishing, a Workspace will be passed in
     * @return void
     */
    public function indexNode(Node $node, ?WorkspaceName $targetWorkspace = null)
    {
        $this->nodesToBeRemoved->detach($node);
        $this->nodesToBeIndexed->attach($node);
        $this->targetWorkspaceNamesForNodesToBeIndexed[NodeAddress::fromNode($node)->toJson()] = $targetWorkspace instanceof WorkspaceName ? $targetWorkspace : null;

        $this->flushQueuesIfNeeded();
    }

    /**
     * Schedule a node for removal of the index
     *
     * @param Node $node
     * @param WorkspaceName|null $targetWorkspace In case this is triggered during publishing, a Workspace will be passed in
     * @return void
     */
    public function removeNode(Node $node, ?WorkspaceName $targetWorkspace = null)
    {
        $this->nodesToBeIndexed->detach($node);
        $this->nodesToBeRemoved->attach($node);
        $this->targetWorkspaceNamesForNodesToBeRemoved[NodeAddress::fromNode($node)->toJson()] = $targetWorkspace instanceof WorkspaceName ? $targetWorkspace : null;

        $this->flushQueuesIfNeeded();
    }

    /**
     * Flush the indexing/removal queues, actually processing them, if the
     * maximum indexing batch size has been reached.
     *
     * @return void
     */
    protected function flushQueuesIfNeeded()
    {
        if ($this->nodesToBeIndexed->count() + $this->nodesToBeRemoved->count() > $this->indexingBatchSize) {
            $this->flushQueues();
        }
    }

    /**
     * Flush the indexing/removal queues, actually processing them.
     *
     * @return void
     */
    public function flushQueues()
    {
        $flush = function () {
            /** @var Node $nodeToBeIndexed */
            foreach ($this->nodesToBeIndexed as $nodeToBeIndexed) {
                if (isset($this->targetWorkspaceNamesForNodesToBeIndexed[NodeAddress::fromNode($nodeToBeIndexed)->toJson()])) {
                    $this->nodeIndexer->indexNode($nodeToBeIndexed, $this->targetWorkspaceNamesForNodesToBeIndexed[NodeAddress::fromNode($nodeToBeIndexed)->toJson()]);
                } else {
                    $this->nodeIndexer->indexNode($nodeToBeIndexed);
                }
            }

            /** @var Node $nodeToBeRemoved */
            foreach ($this->nodesToBeRemoved as $nodeToBeRemoved) {
                if (isset($this->targetWorkspaceNamesForNodesToBeRemoved[NodeAddress::fromNode($nodeToBeRemoved)->toJson()])) {
                    $this->nodeIndexer->removeNode($nodeToBeRemoved, $this->targetWorkspaceNamesForNodesToBeRemoved[NodeAddress::fromNode($nodeToBeRemoved)->toJson()]);
                } else {
                    $this->nodeIndexer->removeNode($nodeToBeRemoved);
                }
            }

            $this->nodeIndexer->flush();
            $this->nodesToBeIndexed = new \SplObjectStorage();
            $this->nodesToBeRemoved = new \SplObjectStorage();
            $this->targetWorkspaceNamesForNodesToBeIndexed = [];
            $this->targetWorkspaceNamesForNodesToBeRemoved = [];
        };

        if ($this->nodeIndexer instanceof BulkNodeIndexerInterface) {
            $this->nodeIndexer->withBulkProcessing($flush);
        } else {
            $flush();
        }
    }
}
