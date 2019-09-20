<?php
namespace Neos\ContentRepository\Search\Tests\Unit\Eel;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "Neos.ContentRepository.Search".        *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use Neos\ContentRepository\Search\Eel\IndexingHelper;

/**
 * Testcase for IndexingHelper
 */
class IndexingHelperTest extends \Neos\Flow\Tests\UnitTestCase
{
    /**
     * @var IndexingHelper
     */
    protected $helper;

    public function setUp(): void
    {
        $this->helper = new IndexingHelper();
    }

    /**
     * @test
     */
    public function buildAllPathPrefixesWorksWithRelativePaths()
    {
        $input = 'foo/bar/baz/testing';
        $expected = array(
            'foo',
            'foo/bar',
            'foo/bar/baz',
            'foo/bar/baz/testing',
        );

        self::assertSame($expected, $this->helper->buildAllPathPrefixes($input));
    }

    /**
     * @test
     */
    public function buildAllPathPrefixesWorksWithAbsolutePaths()
    {
        $input = '/foo/bar/baz/testing';
        $expected = array(
            '/',
            '/foo',
            '/foo/bar',
            '/foo/bar/baz',
            '/foo/bar/baz/testing',
        );

        self::assertSame($expected, $this->helper->buildAllPathPrefixes($input));
    }

    /**
     * @test
     */
    public function buildAllPathPrefixesWorksWithEdgeCase()
    {
        $input = '/';
        $expected = array(
            '/'
        );

        self::assertSame($expected, $this->helper->buildAllPathPrefixes($input));
    }

    /**
     * @test
     */
    public function extractHtmlTagsWorks()
    {
        $input = 'So.. I want to know... <h2>How do you feel?</h2>This is <p><b>some</b>Text.<h2>I Feel so good</h2>... so good...</p>';
        $expected = array(
            'text' => 'So.. I want to know... This is some Text. ... so good... ',
            'h2' => ' How do you feel? I Feel so good '
        );

        self::assertSame($expected, $this->helper->extractHtmlTags($input));
    }
}
