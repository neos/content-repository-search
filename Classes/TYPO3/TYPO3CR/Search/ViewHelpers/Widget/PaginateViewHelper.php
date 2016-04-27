<?php
namespace TYPO3\TYPO3CR\Search\ViewHelpers\Widget;

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
use TYPO3\Fluid\Core\Widget\AbstractWidgetViewHelper;
use TYPO3\TYPO3CR\Search\Search\QueryBuilderInterface;

/**
 * This ViewHelper renders a Pagination of search results.
 *
 * This might still get refactored in the future.
 */
class PaginateViewHelper extends AbstractWidgetViewHelper
{
    /**
     * @Flow\Inject
     * @var \TYPO3\TYPO3CR\Search\ViewHelpers\Widget\Controller\PaginateController
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
