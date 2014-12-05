<?php
namespace TYPO3\TYPO3CR\Search\Tests\Functional\Eel;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR.Search".        *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Search\Eel\IndexingHelper;

/**
 * Functional Testcase for ElasticSearchHelper
 */
class ElasticSearchHelperTest extends \TYPO3\Flow\Tests\FunctionalTestCase {

	/**
	 * @var IndexingHelper
	 */
	protected $helper;

	public function setUp() {
		$this->helper = new IndexingHelper();
		parent::setUp();
	}

	/**
	 * @test
	 */
	public function extractNodeTypesAndSupertypesWorks() {
		/* @var $nodeTypeManager NodeTypeManager */
		$nodeTypeManager = $this->objectManager->get('TYPO3\TYPO3CR\Domain\Service\NodeTypeManager');
		$nodeType = $nodeTypeManager->getNodeType('TYPO3.TYPO3CR.Search.Test:Type3');

		$expected = array(
			'TYPO3.TYPO3CR.Search.Test:Type3',
			'TYPO3.TYPO3CR.Search.Test:Type1',
			'TYPO3.TYPO3CR.Search.Test:BaseType',
			'TYPO3.TYPO3CR.Search.Test:Type2'
		);

		$actual = $this->helper->extractNodeTypeNamesAndSupertypes($nodeType);
		$this->assertSame($expected, $actual);
	}
}