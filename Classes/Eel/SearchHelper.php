<?php
namespace Neos\ContentRepository\Search\Eel;

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
use Neos\ContentRepository\Search\Search\QueryBuilderInterface;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManager;

/**
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
     * @param Node $contextNode
     * @return QueryBuilderInterface
     */
    public function query(Node $contextNode): QueryBuilderInterface
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
