<?php
namespace Neos\ContentRepository\Search\Eel;

/*
 * This file is part of the TYPO3.TYPO3CR.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Object\ObjectManager;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use Neos\ContentRepository\Search\Search\QueryBuilderInterface;

/**
 *
 * Eel Helper to start search queries
 */
class SearchHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var ObjectManager
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
        $queryBuilder = $this->objectManager->get(QueryBuilderInterface::class);

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
