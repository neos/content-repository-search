<?php
namespace Neos\ContentRepository\Search\Tests\Functional\Eel;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "Neos.ContentRepository.Search".        *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Search\Eel\IndexingHelper;

/**
 * Functional Testcase for ElasticSearchHelper
 */
class ElasticSearchHelperTest extends \Neos\Flow\Tests\FunctionalTestCase
{
    /**
     * @var IndexingHelper
     */
    protected $helper;

    public function setUp(): void
    {
        $this->helper = new IndexingHelper();
        parent::setUp();
    }

    /**
     * @test
     */
    public function extractNodeTypesAndSupertypesWorks()
    {
        /* @var $nodeTypeManager NodeTypeManager */
        $nodeTypeManager = $this->objectManager->get('Neos\ContentRepository\Domain\Service\NodeTypeManager');
        $nodeType = $nodeTypeManager->getNodeType('Neos.ContentRepository.Search.Test:Type3');

        $expected = array(
            'Neos.ContentRepository.Search.Test:Type3',
            'Neos.ContentRepository.Search.Test:Type1',
            'Neos.ContentRepository.Search.Test:BaseType',
            'Neos.ContentRepository.Search.Test:Type2'
        );

        $actual = $this->helper->extractNodeTypeNamesAndSupertypes($nodeType);
        self::assertSame($expected, $actual);
    }
}
