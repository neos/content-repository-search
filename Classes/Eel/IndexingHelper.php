<?php
declare(strict_types=1);

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

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Search\AssetExtraction\AssetExtractorInterface;
use Neos\ContentRepository\Search\Dto\NodeAggregateIdPath;
use Neos\ContentRepository\Search\Exception\IndexingException;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Media\Domain\Model\AssetInterface;
use Psr\Log\LoggerInterface;

/**
 * IndexingHelper
 */
class IndexingHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var AssetExtractorInterface
     */
    protected $assetExtractor;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Build all path prefixes. From an input such as:
     *
     *   /foo/bar/baz
     *
     * it emits an array with:
     *
     *   /
     *   /foo
     *   /foo/bar
     *   /foo/bar/baz
     *
     * This method works both with absolute and relative paths. If a relative path is given,
     * the returned array will lack the first element and the leading slashes, obviously.
     *
     * @param string $path
     * @return array<string>
     */
    public function buildAllPathPrefixes(?string $path): array
    {
        if ($path === '' || $path === null) {
            return [];
        }

        if ($path === '/') {
            return ['/'];
        }

        $currentPath = '';
        $pathPrefixes = [];
        if (strpos($path, '/') === 0) {
            $currentPath = '/';
            $pathPrefixes[] = $currentPath;
        }
        $path = ltrim($path, '/');

        foreach (explode('/', $path) as $pathPart) {
            $currentPath .= $pathPart . '/';
            $pathPrefixes[] = rtrim($currentPath, '/');
        }

        return $pathPrefixes;
    }

    /**
     * Returns an array of node type names including the passed $nodeType and all its supertypes, recursively
     *
     * @param NodeType $nodeTypeName
     * @return array<String>
     */
    public function extractNodeTypeNamesAndSupertypes(Node $node): array
    {
        $nodeTypeNames = [];
        $nodeTypeManager = $this->contentRepositoryRegistry->get($node->contentRepositoryId)->getNodeTypeManager();
        $nodeType = $nodeTypeManager->getNodeType($node->nodeTypeName);
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
    protected function extractNodeTypeNamesAndSupertypesInternal(NodeType $nodeType, array &$nodeTypeNames): void
    {
        $nodeTypeNames[$nodeType->name->value] = $nodeType->name->value;
        foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
            $this->extractNodeTypeNamesAndSupertypesInternal($superType, $nodeTypeNames);
        }
    }

    /**
     * Convert an array of nodes to an array of node identifiers
     *
     * @param array<Node> $nodes
     * @return array
     */
    public function convertArrayOfNodesToArrayOfNodeIdentifiers($nodes): array
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeIdentifiers = [];

        /** @var Node $node */
        foreach ($nodes as $node) {
            $nodeIdentifiers[] = $node->aggregateId->value;
        }

        return $nodeIdentifiers;
    }

    /**
     * Convert an array of nodes to an array of node property
     *
     * @param array<Node> $nodes
     * @param string $propertyName
     * @return array
     */
    public function convertArrayOfNodesToArrayOfNodeProperty($nodes, string $propertyName): array
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeProperties = [];
        foreach ($nodes as $node) {
            $nodeProperties[] = $node->getProperty($propertyName);
        }

        return $nodeProperties;
    }

    /**
     *
     * @param $string
     * @return array
     */
    public function extractHtmlTags($string): array
    {
        if (!$string || trim($string) === "") {
            return [];
        }

        // prevents concatenated words when stripping tags afterwards
        $string = str_replace(['<', '>'], [' <', '> '], $string);
        // strip all tags except h1-6
        $string = strip_tags($string, '<h1><h2><h3><h4><h5><h6>');

        $parts = [
            'text' => ''
        ];
        while ($string !== '') {
            $matches = [];
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
     * @param string $bucketName
     * @param string $string
     * @return array
     */
    public function extractInto(string $bucketName, $string): array
    {
        return [
            $bucketName => (string)$string
        ];
    }

    /**
     * Extract the asset content and meta data
     *
     * @param AssetInterface|AssetInterface[]|null $value
     * @param string $field
     * @return array|null|string
     * @throws IndexingException
     */
    public function extractAssetContent($value, string $field = 'content')
    {
        if (empty($value)) {
            return null;
        } elseif (is_array($value)) {
            $result = [];
            foreach ($value as $element) {
                $result[] = $this->extractAssetContent($element, $field);
            }
            return $result;
        } elseif ($value instanceof AssetInterface) {
            try {
                $assetContent = $this->assetExtractor->extract($value);
                $getter = 'get' . lcfirst($field);
                $content = $assetContent->$getter();
            } catch (\Throwable $t) {
                $this->logger->error('Value of type ' . gettype($value) . ' - ' . get_class($value) . ' could not be extracted: ' . $t->getMessage(), LogEnvironment::fromMethodName(__METHOD__));
                return null;
            }

            return $content;
        } else {
            $this->logger->error('Value of type ' . gettype($value) . ' - ' . get_class($value) . ' could not be extracted.', LogEnvironment::fromMethodName(__METHOD__));
            return null;
        }
    }

    /**
     * Returns a reliable path based on node aggregate ids to display the position of the node in the hierarchy.
     */
    public function aggregateIdPath(Node $node): string
    {
        $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
        $ancestors = $subgraph->findAncestorNodes(
            $node->aggregateId,
            FindAncestorNodesFilter::create()
        )->reverse();

        return NodeAggregateIdPath::fromNodes($ancestors)->serializeToString();
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
