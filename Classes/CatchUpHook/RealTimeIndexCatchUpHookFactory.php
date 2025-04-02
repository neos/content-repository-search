<?php

namespace Neos\ContentRepository\Search\CatchUpHook;

use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryDependencies;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Search\Indexer\NodeIndexingManager;
use Neos\Flow\Annotations as Flow;

class RealTimeIndexCatchUpHookFactory implements CatchUpHookFactoryInterface
{
    #[Flow\InjectConfiguration()]
    protected array $configuration;

    public function __construct(
        private readonly NodeIndexingManager $nodeIndexingManager,
    ) {
    }

    public function build(CatchUpHookFactoryDependencies $dependencies): CatchUpHookInterface
    {
        return new RealTimeIndexCatchUpHook(
            $dependencies->contentRepositoryId,
            $dependencies->projectionState,
            $this->nodeIndexingManager,
            $this->configuration['realtimeIndexing']['enabled'] ?? false
        );
    }
}
