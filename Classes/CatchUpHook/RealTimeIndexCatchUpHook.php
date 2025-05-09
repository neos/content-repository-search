<?php

namespace Neos\ContentRepository\Search\CatchUpHook;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\NodeCreation\Event\NodeAggregateWithNodeWasCreated;
use Neos\ContentRepository\Core\Feature\NodeModification\Event\NodePropertiesWereSet;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodeGeneralizationVariantWasCreated;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodePeerVariantWasCreated;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodeSpecializationVariantWasCreated;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasTagged;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasUntagged;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\ContentRepository\Search\Indexer\NodeIndexingManager;
use Neos\EventStore\Model\EventEnvelope;
use Neos\Neos\Domain\SubtreeTagging\NeosSubtreeTag;
use Neos\Neos\Domain\SubtreeTagging\NeosVisibilityConstraints;

class RealTimeIndexCatchUpHook implements CatchUpHookInterface
{
    protected bool $handleEvents = false;

    public function __construct(
        private readonly ContentRepositoryId            $contentRepositoryId,
        private readonly ContentGraphReadModelInterface $contentGraphReadModel,
        private readonly NodeIndexingManager            $nodeIndexingManager,
        private readonly bool                           $enabledRealTimeIndexing = true,
    ) {
    }

    public function onBeforeCatchUp(SubscriptionStatus $subscriptionStatus): void
    {
        if ($subscriptionStatus === SubscriptionStatus::ACTIVE && $this->enabledRealTimeIndexing === true) {
            $this->handleEvents = true;
            return;
        }

        $this->handleEvents = false;
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if ($this->handleEvents === false) {
            return;
        }

        match ($eventInstance::class) {
            NodeAggregateWasRemoved::class => $this->removeNodes($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->affectedCoveredDimensionSpacePoints),
            default => null
        };
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if ($this->handleEvents === false) {
            return;
        }

        match ($eventInstance::class) {
            NodeAggregateWithNodeWasCreated::class => $this->updateNode($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->originDimensionSpacePoint->toDimensionSpacePoint()),
            NodePeerVariantWasCreated::class => $this->updateNode($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->peerOrigin->toDimensionSpacePoint()),
            NodeGeneralizationVariantWasCreated::class => $this->updateNode($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->generalizationOrigin->toDimensionSpacePoint()),
            NodeSpecializationVariantWasCreated::class => $this->updateNode($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->specializationOrigin->toDimensionSpacePoint()),
            NodePropertiesWereSet::class => $this->updateNode($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->originDimensionSpacePoint->toDimensionSpacePoint()),

            SubtreeWasTagged::class => $this->handleSubtreeTags($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->tag, $eventInstance->affectedDimensionSpacePoints),
            SubtreeWasUntagged::class => $this->updateNodesInDimensionSpacePoints($eventInstance->getWorkspaceName(), $eventInstance->nodeAggregateId, $eventInstance->affectedDimensionSpacePoints),

            // TODO: Currently it is not possible to clear the state in elasticsearch to get the state of the base workspace. But it was the same before Neos 9.
            // WorkspaceWasDiscarded::class => $this->discardWorkspace($eventInstance->getWorkspaceName()),
            // because we don't know which changes were discarded in a conflict, we discard all changes and will build up the index on succeeding calls (with the kept reapplied events)
            // WorkspaceWasRebased::class => $eventInstance->hasSkippedEvents() && $this->discardWorkspace($eventInstance->getWorkspaceName()),

            default => null
        };
    }

    public function onAfterBatchCompleted(): void
    {
        if ($this->handleEvents === false) {
            return;
        }
    }

    public function onAfterCatchUp(): void
    {
        if ($this->handleEvents === false) {
            return;
        }

        $this->nodeIndexingManager->flushQueues();
    }

    /**
     * Triggers update of node index.
     */
    protected function updateNode(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId, DimensionSpacePoint $dimensionSpacePoint): void
    {
        $contentGraph = $this->contentGraphReadModel->getContentGraph($workspaceName);
        $node = $contentGraph->getSubgraph($dimensionSpacePoint, NeosVisibilityConstraints::excludeRemoved())->findNodeById($nodeAggregateId);

        if ($node === null) {
            // Node not found, nothing to do here.
            return;
        }

        $this->nodeIndexingManager->indexNode($node);
    }

    /**
     * Wrapper for self::updateNode() to iterate multiple dimension space points.
     */
    protected function updateNodesInDimensionSpacePoints(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId, DimensionSpacePointSet $dimensionSpacePointSet): void
    {
        foreach ($dimensionSpacePointSet as $dimensionSpacePoint) {
            $this->updateNode($workspaceName, $nodeAggregateId, $dimensionSpacePoint);
        }
    }

    /**
     * Triggers removal of node index.
     */
    private function removeNodes(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId, DimensionSpacePointSet $dimensionSpacePoints): void
    {
        $contentGraph = $this->contentGraphReadModel->getContentGraph($workspaceName);

        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            $subgraph = $contentGraph->getSubgraph($dimensionSpacePoint, VisibilityConstraints::createEmpty());
            $node = $subgraph->findNodeById($nodeAggregateId);
            if ($node === null) {
                // No node in this dimension. So nothing to do here.
                continue;
            }
            $this->nodeIndexingManager->removeNode($node);

            $descendants = $subgraph->findDescendantNodes($nodeAggregateId, FindDescendantNodesFilter::create());

            foreach ($descendants as $descendant) {
                $this->nodeIndexingManager->removeNode($descendant);
            }
        }
    }

    /**
     * Handles subtree tags added to nodes.
     */
    private function handleSubtreeTags(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId, SubtreeTag $tag, DimensionSpacePointSet $affectedDimensionSpacePoints): void
    {
        // Remove documents from index on soft delete
        if ($tag === NeosSubtreeTag::removed()) {
            $this->removeNodes($workspaceName, $nodeAggregateId, $affectedDimensionSpacePoints);
            return;
        }

        $this->updateNodesInDimensionSpacePoints($workspaceName, $nodeAggregateId, $affectedDimensionSpacePoints);
    }
}
