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

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;

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
     * @var array
     */
    protected $targetWorkspaceNamesForNodesToBeIndexed = [];

    /**
     * @var array
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
     * @var \Neos\ContentRepository\Search\Indexer\NodeIndexerInterface
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
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace In case this is triggered during publishing, a Workspace will be passed in
     * @return void
     */
    public function indexNode(NodeInterface $node, Workspace $targetWorkspace = null)
    {
        // if this is triggered via afterNodePublishing, it could be a deletion, check and handle
        if ($node->isRemoved() && $targetWorkspace !== null && $targetWorkspace->getBaseWorkspace() === null) {
            $this->removeNode($node, $targetWorkspace);
        } else {
            $this->nodesToBeRemoved->detach($node);
            $this->nodesToBeIndexed->attach($node);
            $this->targetWorkspaceNamesForNodesToBeIndexed[$node->getContextPath()] = $targetWorkspace instanceof Workspace ? $targetWorkspace->getName() : null;

            $this->flushQueuesIfNeeded();
        }
    }

    /**
     * Schedule a node for removal of the index
     *
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace In case this is triggered during publishing, a Workspace will be passed in
     * @return void
     */
    public function removeNode(NodeInterface $node, Workspace $targetWorkspace = null)
    {
        $this->nodesToBeIndexed->detach($node);
        $this->nodesToBeRemoved->attach($node);
        $this->targetWorkspaceNamesForNodesToBeRemoved[$node->getContextPath()] = $targetWorkspace instanceof Workspace ? $targetWorkspace->getName() : null;

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
            /** @var NodeInterface $nodeToBeIndexed */
            foreach ($this->nodesToBeIndexed as $nodeToBeIndexed) {
                if (isset($this->targetWorkspaceNamesForNodesToBeIndexed[$nodeToBeIndexed->getContextPath()])) {
                    $this->nodeIndexer->indexNode($nodeToBeIndexed, $this->targetWorkspaceNamesForNodesToBeIndexed[$nodeToBeIndexed->getContextPath()]);
                } else {
                    $this->nodeIndexer->indexNode($nodeToBeIndexed);
                }
            }

            /** @var NodeInterface $nodeToBeRemoved */
            foreach ($this->nodesToBeRemoved as $nodeToBeRemoved) {
                $this->nodeIndexer->removeNode($nodeToBeRemoved);
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
