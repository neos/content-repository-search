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
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * Indexer for Content Repository Nodes.
 *
 */
interface NodeIndexerInterface
{
    /**
     * Schedule a node for indexing
     *
     * @param Node $node
     * @param mixed $targetWorkspaceName In case this is triggered during publishing, a Workspace will be passed in
     * @return void
     */
    public function indexNode(Node $node, ?WorkspaceName $targetWorkspaceName = null): void;

    /**
     * Schedule a node for removal of the index
     *
     * @param Node $node
     * @return void
     */
    public function removeNode(Node $node, ?WorkspaceName $targetWorkspaceName = null): void;

    /**
     * Perform all changes to the index queued up. If an implementation directly changes the index this can be no operation.
     *
     * @return void
     */
    public function flush(): void;
}
