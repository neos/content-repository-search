<?php
namespace TYPO3\TYPO3CR\SearchCommons\Indexer;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR.SearchCommons". *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;


/**
 * Indexer for Content Repository Nodes.
 *
 */
interface NodeIndexerInterface {

	/**
	 * index this node, and add it to the current bulk request.
	 *
	 * @param NodeData $nodeData
	 * @return void
	 */
	public function indexNode(NodeData $nodeData);

	/**
	 * @param NodeData $nodeData
	 * @return void
	 */
	public function removeNode(NodeData $nodeData);

	/**
	 * Perform all changes to the index queued up. If an implementation directly changes the index this can be no operation.
	 *
	 * @return void
	 */
	public function flush();

}