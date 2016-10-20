<?php
namespace TYPO3\TYPO3CR\Search\Indexer;

/*
 * This file is part of the TYPO3.TYPO3CR.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;

/**
 * Indexer for Content Repository Nodes with bulk mode support
 *
 */
interface BulkNodeIndexerInterface
{
    /**
     * Perform indexing without checking about duplication document
     *
     * This is used during bulk indexing to improve performance
     *
     * @param callable $callback
     * @return void
     */
    public function withBulkProcessing(callable $callback);
}
