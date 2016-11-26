<?php
namespace Neos\ContentRepository\Search\ViewHelpers\Widget;

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
use Neos\FluidAdaptor\Core\Widget\AbstractWidgetViewHelper;
use Neos\ContentRepository\Search\Search\QueryBuilderInterface;

/**
 * This ViewHelper renders a Pagination of search results.
 *
 * This might still get refactored in the future.
 */
class PaginateViewHelper extends AbstractWidgetViewHelper
{
    /**
     * @Flow\Inject
     * @var \Neos\ContentRepository\Search\ViewHelpers\Widget\Controller\PaginateController
     */
    protected $controller;

    /**
     * Render this view helper
     *
     * @param QueryBuilderInterface $query
     * @param string $as
     * @param array $configuration
     * @return string
     */
    public function render(QueryBuilderInterface $query, $as, array $configuration = array('itemsPerPage' => 10, 'insertAbove' => false, 'insertBelow' => true, 'maximumNumberOfLinks' => 99))
    {
        $response = $this->initiateSubRequest();
        return $response->getContent();
    }
}
