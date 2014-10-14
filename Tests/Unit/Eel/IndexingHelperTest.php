<?php
namespace TYPO3\TYPO3CR\Search\Tests\Unit\Eel;

/*                                                                                                  *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR.Search".                            *
 *                                                                                                  *
 * It is free software; you can redistribute it and/or modify it under                              *
 * the terms of the GNU Lesser General Public License, either version 3                             *
 *  of the License, or (at your option) any later version.                                          *
 *                                                                                                  *
 * The TYPO3 project - inspiring people to share!                                                   *
 *                                                                                                  */

use TYPO3\TYPO3CR\Search\Eel\IndexingHelper;

/**
 * Testcase for IndexingHelper
 */
class IndexingHelperTest extends \TYPO3\Flow\Tests\UnitTestCase {

	/**
	 * @var IndexingHelper
	 */
	protected $helper;

	public function setUp() {
		$this->helper = new IndexingHelper();
	}

	/**
	 * @test
	 */
	public function buildAllPathPrefixesWorksWithRelativePaths() {
		$input = 'foo/bar/baz/testing';
		$expected = array(
			'foo',
			'foo/bar',
			'foo/bar/baz',
			'foo/bar/baz/testing',
		);

		$this->assertSame($expected, $this->helper->buildAllPathPrefixes($input));
	}

	/**
	 * @test
	 */
	public function buildAllPathPrefixesWorksWithAbsolutePaths() {
		$input = '/foo/bar/baz/testing';
		$expected = array(
			'/foo',
			'/foo/bar',
			'/foo/bar/baz',
			'/foo/bar/baz/testing',
		);

		$this->assertSame($expected, $this->helper->buildAllPathPrefixes($input));
	}

	/**
	 * @test
	 */
	public function buildAllPathPrefixesWorksWithEdgeCase() {
		$input = '/';
		$expected = array(
			'/'
		);

		$this->assertSame($expected, $this->helper->buildAllPathPrefixes($input));
	}

	/**
	 * @test
	 */
	public function extractHtmlTagsWorks() {
		$input = 'So.. I want to know... <h2>How do you feel?</h2>This is <p><b>some</b>Text.<h2>I Feel so good</h2>... so good...</p>';
		$expected = array(
			'text' => 'So.. I want to know... This is some Text. ... so good... ',
			'h2' => ' How do you feel? I Feel so good '
		);

		$this->assertSame($expected, $this->helper->extractHtmlTags($input));
	}
}