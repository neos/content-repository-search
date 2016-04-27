<?php
namespace TYPO3\TYPO3CR\Search\Eel;

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
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Search\Search\QueryBuilderInterface;

/**
 *
 * Eel Helper to start search queries
 */
class SearchHelper implements \TYPO3\Eel\ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var \TYPO3\Flow\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Create a new Search Query underneath the given $contextNode
     *
     * @param NodeInterface $contextNode
     * @return QueryBuilderInterface
     */
    public function query(NodeInterface $contextNode)
    {
        $queryBuilder = $this->objectManager->get('TYPO3\TYPO3CR\Search\Search\QueryBuilderInterface');
        return $queryBuilder->query($contextNode);
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
