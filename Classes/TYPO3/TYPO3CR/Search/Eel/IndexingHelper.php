<?php
namespace TYPO3\TYPO3CR\Search\Eel;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR.Search". *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeType;
use TYPO3\Media\Domain\Model\ImageVariant;
use TYPO3\Media\Domain\Model\Asset;

/**
 * IndexingHelper
 */
class IndexingHelper implements ProtectedContextAwareInterface {

	/**
	 * @Flow\Inject
	 * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var \TYPO3\Flow\Resource\Publishing\ResourcePublisher
	 * @Flow\Inject
	 */
	protected $resourcePublisher;

	/**
	 * Build all path prefixes. From an input such as:
	 *
	 *   foo/bar/baz
	 *
	 * it emits an array with:
	 *
	 *   foo
	 *   foo/bar
	 *   foo/bar/baz
	 *
	 * This method works both with absolute and relative paths.
	 *
	 * @param string $path
	 * @return array<string>
	 */
	public function buildAllPathPrefixes($path) {
		if (strlen($path) === 0) {
			return array();
		} elseif ($path === '/') {
			return array('/');
		}

		$currentPath = '';
		if ($path{0} === '/') {
			$currentPath = '/';
		}
		$path = ltrim($path, '/');

		$pathPrefixes = array();
		foreach (explode('/', $path) as $pathPart) {
			$currentPath .= $pathPart . '/';
			$pathPrefixes[] = rtrim($currentPath, '/');
		}

		return $pathPrefixes;
	}

	/**
	 * Returns an array of node type names including the passed $nodeType and all its supertypes, recursively
	 *
	 * @param NodeType $nodeType
	 * @return array<String>
	 */
	public function extractNodeTypeNamesAndSupertypes(NodeType $nodeType) {
		$nodeTypeNames = array();
		$this->extractNodeTypeNamesAndSupertypesInternal($nodeType, $nodeTypeNames);
		return array_values($nodeTypeNames);
	}

	/**
	 * Recursive function for fetching all node type names
	 *
	 * @param NodeType $nodeType
	 * @param array $nodeTypeNames
	 * @return void
	 */
	protected function extractNodeTypeNamesAndSupertypesInternal(NodeType $nodeType, array &$nodeTypeNames) {
		$nodeTypeNames[$nodeType->getName()] = $nodeType->getName();
		foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
			$this->extractNodeTypeNamesAndSupertypesInternal($superType, $nodeTypeNames);
		}
	}

	/**
	 * Convert an array of nodes to an array of node identifiers
	 *
	 * @param array<NodeInterface> $nodes
	 * @return array
	 */
	public function convertArrayOfNodesToArrayOfNodeIdentifiers($nodes) {
		if (!is_array($nodes) && !$nodes instanceof \Traversable) {
			return array();
		}
		$nodeIdentifiers = array();
		foreach ($nodes as $node) {
			$nodeIdentifiers[] = $node->getIdentifier();
		}

		return $nodeIdentifiers;
	}


	/**
	 * @param $propertyName
	 * @param  $node
	 * @param ImageVariant $value
	 * @return array
	 */
	//public function extractAssetInto($propertyName, $node, $value) {
	public function extractImageVariant($propertyName, $node, $value) {

		// media
		// tag angabe ansonsten
		// ähnliches wie fulltextParts bloß für Media

		xdebug_break();

		if($value !== NULL) {
			$resource = $value->getResource();
			// resourceManager
			$path = $this->resourcePublisher->getPersistentResourceWebUri($value->getResource());


			// nicht persistent
			//$uri = $test = $value->getResource()->getUri();
		}

		$imageArr = array();

		return $imageArr;

	}


	/**
	 * @param $test
	 * @param  $node
	 * @param array<TYPO3\Media\Domain\Model\Asset> $value
	 * @return array
	 */
	public function extractAssetList($test, $node, $value) {

		xdebug_break();


		/*
			 persistenceManager
			getIdentifierByObject($value)
			 */

		//statt fulltextPart AssetParts

		foreach($value as $asset) {
			$resource = $asset->getResource();
			$path = $this->resourcePublisher->getPersistentResourceWebUri($asset->getResource());
		}


		$imageArr = array();

		return $imageArr;

	}

	/**
	 *
	 * @param $string
	 * @return array
	 */
	public function extractHtmlTags($string) {
		// prevents concatenated words when stripping tags afterwards
		$string = str_replace(array('<', '>'), array(' <', '> '), $string);
		// strip all tags except h1-6
		$string = strip_tags($string, '<h1><h2><h3><h4><h5><h6>');

		$parts = array(
			'text' => ''
		);
		while (strlen($string) > 0) {

			$matches = array();
			if (preg_match('/<(h1|h2|h3|h4|h5|h6)[^>]*>.*?<\/\1>/ui', $string, $matches, PREG_OFFSET_CAPTURE)) {
				$fullMatch = $matches[0][0];
				$startOfMatch = $matches[0][1];
				$tagName = $matches[1][0];

				if ($startOfMatch > 0) {
					$parts['text'] .= substr($string, 0, $startOfMatch);
					$string = substr($string, $startOfMatch);
				}
				if (!isset($parts[$tagName])) {
					$parts[$tagName] = '';
				}

				$parts[$tagName] .= ' ' . $fullMatch;
				$string = substr($string, strlen($fullMatch));
			} else {
				// no h* found anymore in the remaining string
				$parts['text'] .= $string;
				break;
			}
		}


		foreach ($parts as &$part) {
			$part = preg_replace('/\s+/u', ' ', strip_tags($part));
		}

		return $parts;
	}

	/**
	 *
	 *
	 * @param $bucketName
	 * @param $string
	 * @return array
	 */
	public function extractInto($bucketName, $string) {
		return array(
			$bucketName => $string
		);
	}

	/**
	 * All methods are considered safe
	 *
	 * @param string $methodName
	 * @return boolean
	 */
	public function allowsCallOfMethod($methodName) {
		return TRUE;
	}
}