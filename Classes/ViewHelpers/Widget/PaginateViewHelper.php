<?php
namespace Neos\ContentRepository\Search\ViewHelpers\Widget;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Search\Search\QueryBuilderInterface;
use Neos\ContentRepository\Search\ViewHelpers\Widget\Controller\PaginateController;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Exception\InfiniteLoopException;
use Neos\FluidAdaptor\Core\ViewHelper\Exception as ViewHelperException;
use Neos\FluidAdaptor\Core\Widget\AbstractWidgetViewHelper;
use Neos\FluidAdaptor\Core\Widget\Exception\InvalidControllerException;
use Neos\FluidAdaptor\Core\Widget\Exception\MissingControllerException;

/**
 * This ViewHelper renders a Pagination of search results.
 *
 * This might still get refactored in the future.
 */
class PaginateViewHelper extends AbstractWidgetViewHelper
{
    /**
     * @Flow\Inject
     * @var PaginateController
     */
    protected $controller;

    /**
     * Initialize the arguments.
     *
     * @return void
     * @throws ViewHelperException
     */
    public function initializeArguments()
    {
        parent::initializeArguments();

        $this->registerArgument('query', QueryBuilderInterface::class, 'Query', true);
        $this->registerArgument('as', 'string', 'as', true);
        $this->registerArgument(
            'configuration',
            'array',
            'Widget configuration',
            false,
            ['insertAbove' => false, 'insertBelow' => true, 'itemsPerPage' => 10, 'maximumNumberOfLinks' => 99]
        );
    }

    /**
     * Render this view helper
     *
     * @return string
     * @throws InfiniteLoopException
     * @throws InvalidControllerException
     * @throws MissingControllerException
     */
    public function render() : string
    {
        return $this->initiateSubRequest();
    }
}
