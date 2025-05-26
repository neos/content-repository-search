<?php
declare(strict_types=1);

namespace Neos\ContentRepository\Search\Indexer;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Search\Exception\IndexingException;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractNodeIndexer implements NodeIndexerInterface
{
    /**
     * @Flow\Inject(lazy=FALSE)
     * @var \Neos\Eel\CompilingEvaluator
     */
    protected $eelEvaluator;

    /**
     * @Flow\Inject
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $settings;

    /**
     * the default context variables available inside Eel
     *
     * @var array
     */
    protected $defaultContextVariables;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Called by the Flow object framework after creating the object and resolving all dependencies.
     *
     * @param integer $cause Creation cause
     * @throws InvalidConfigurationTypeException
     */
    public function initializeObject($cause)
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->settings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepository.Search');
        }
    }

    /**
     * Evaluate an Eel expression.
     *
     * @param string $expression The Eel expression to evaluate
     * @param Node $node
     * @param string $propertyName
     * @param mixed $value
     * @return mixed The result of the evaluated Eel expression
     * @throws \Neos\Eel\Exception
     */
    protected function evaluateEelExpression(string $expression, Node $node, string $propertyName, $value)
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->settings['defaultContext']);
        }

        $contextVariables = array_merge($this->defaultContextVariables, [
            'node' => $node,
            'propertyName' => $propertyName,
            'value' => $value,
        ]);

        return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
    }

    /**
     * @param Node $node
     * @param string $propertyName
     * @param string $fulltextExtractionExpression
     * @param array $fulltextIndexOfNode
     * @throws IndexingException
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     */
    protected function extractFulltext(Node $node, $propertyName, $fulltextExtractionExpression, array &$fulltextIndexOfNode): void
    {
        if ($fulltextExtractionExpression !== '') {
            $extractedFulltext = $this->evaluateEelExpression($fulltextExtractionExpression, $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : null));

            if (!is_array($extractedFulltext)) {
                throw new IndexingException('The fulltext index for property "' . $propertyName . '" of node "' . $node->aggregateId->value . '" could not be retrieved; the Eel expression "' . $fulltextExtractionExpression . '" is no valid fulltext extraction expression.');
            }

            foreach ($extractedFulltext as $bucket => $value) {
                if (!isset($fulltextIndexOfNode[$bucket])) {
                    $fulltextIndexOfNode[$bucket] = '';
                }

                $value = trim($value);
                if ($value !== '') {
                    $fulltextIndexOfNode[$bucket] .= ' ' . $value;
                }
            }
        }
        // TODO: also allow fulltextExtractor in settings!!
    }

    /**
     * Extracts all property values according to configuration and additionally adds to the referenced fulltextData array if needed.
     *
     * @param Node $node
     * @param array $fulltextData
     * @param \Closure $nonIndexedPropertyErrorHandler
     * @return array
     * @throws IndexingException
     * @throws \Neos\Eel\Exception
     */
    protected function extractPropertiesAndFulltext(Node $node, array &$fulltextData, \Closure $nonIndexedPropertyErrorHandler = null): array
    {
        $nodePropertiesToBeStoredInIndex = [];
        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName);
        $fulltextIndexingEnabledForNode = $this->isFulltextEnabled($node);

        $propertiesAndReferences = array_merge(
            $nodeType->getProperties(),
            array_map(function ($reference) {
                $reference['type'] = 'references';
                return $reference;
            }, $nodeType->getReferences())
        );

        foreach ($propertiesAndReferences as $propertyName => $propertyConfiguration) {
            if (isset($propertyConfiguration['search']) && array_key_exists('indexing', $propertyConfiguration['search'])) {
                // This property is configured to not be indexed, so do not add a mapping for it
                if ($propertyConfiguration['search']['indexing'] === false) {
                    continue;
                }

                if (!empty($propertyConfiguration['search']['indexing'])) {
                    $valueToStore = $this->evaluateEelExpression($propertyConfiguration['search']['indexing'], $node, $propertyName, $this->getNodePropertyValue($node, $propertyName));
                    $nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
                }
            } elseif (isset($propertyConfiguration['type'], $this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'])) {
                if ($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'] !== '') {
                    $valueToStore = $this->evaluateEelExpression($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'], $node, $propertyName, $this->getNodePropertyValue($node, $propertyName));

                    $nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
                }
            } else {
                // error handling if configured
                if ($nonIndexedPropertyErrorHandler !== null) {
                    $nonIndexedPropertyErrorHandler($propertyName);
                }
            }

            if ($fulltextIndexingEnabledForNode === true && isset($propertyConfiguration['search']['fulltextExtractor'])) {
                $this->extractFulltext($node, $propertyName, $propertyConfiguration['search']['fulltextExtractor'], $fulltextData);
            }
        }

        return $nodePropertiesToBeStoredInIndex;
    }

    private function getNodePropertyValue(Node $node, string $propertyOrReferenceName)
    {
        // Copied from \Neos\ContentRepository\NodeAccess\FlowQueryOperations\PropertyOperation
        if ($node->hasProperty($propertyOrReferenceName)) {
            return $node->getProperty($propertyOrReferenceName);
        }

        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        $nodeTypeManager = $contentRepository->getNodeTypeManager();

        if ($nodeTypeManager->getNodeType($node->nodeTypeName)?->hasReference($propertyOrReferenceName)) {
            // legacy access layer for references
            $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
            $references = $subgraph->findReferences(
                $node->aggregateId,
                FindReferencesFilter::create(referenceName: $propertyOrReferenceName)
            )->getNodes();

            $maxItems = $nodeTypeManager->getNodeType($node->nodeTypeName)->getReferences()[$propertyOrReferenceName]['constraints']['maxItems'] ?? null;
            if ($maxItems === 1) {
                // legacy layer references with only one item like the previous `type: reference`
                // (the node type transforms that to constraints.maxItems = 1)
                // users still expect the property operation to return a single node instead of an array.
                return $references->first();
            }

            return $references;
        }

        return null;
    }


    /**
     * Whether the node has fulltext indexing enabled.
     *
     * @param Node $node
     * @return bool
     */
    protected function isFulltextEnabled(Node $node): bool
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        if ($contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName)->hasConfiguration('search')) {
            $searchSettingsForNode = $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName)->getConfiguration('search');
            if (isset($searchSettingsForNode['fulltext']['enable']) && $searchSettingsForNode['fulltext']['enable'] === true) {
                return true;
            }
        }

        return false;
    }
}
