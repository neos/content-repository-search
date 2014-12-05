<?php
namespace TYPO3\TYPO3CR\Search\Indexer;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR.Search".        *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\Node;


/**
 * Indexer for Content Repository Nodes.
 *
 */
interface NodeIndexerInterface {

	/**
	 * Schedule a node for indexing
	 *
	 * @param Node $node
	 * @param mixed $targetWorkspace In case this is triggered during publishing, a Workspace will be passed in
	 * @return void
	 */
	public function indexNode(Node $node, $targetWorkspace = NULL);

	/**
	 * Schedule a node for removal of the index
	 *
	 * @param Node $node
	 * @return void
	 */
	public function removeNode(Node $node);

	/**
	 * Perform all changes to the index queued up. If an implementation directly changes the index this can be no operation.
	 *
	 * @return void
	 */
	public function flush();

}