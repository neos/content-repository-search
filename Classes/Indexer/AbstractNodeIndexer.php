<?php

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

use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Search\Exception\IndexingException;

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
     * @param NodeInterface $node
     * @param string $propertyName
     * @param mixed $value
     * @return mixed The result of the evaluated Eel expression
     * @throws \Neos\Eel\Exception
     */
    protected function evaluateEelExpression($expression, NodeInterface $node, $propertyName, $value)
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
     * @param NodeInterface $node
     * @param string $propertyName
     * @param string $fulltextExtractionExpression
     * @param array $fulltextIndexOfNode
     * @throws IndexingException
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     */
    protected function extractFulltext(NodeInterface $node, $propertyName, $fulltextExtractionExpression, array &$fulltextIndexOfNode): void
    {
        if ($fulltextExtractionExpression !== '') {
            $extractedFulltext = $this->evaluateEelExpression($fulltextExtractionExpression, $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : null));

            if (!is_array($extractedFulltext)) {
                throw new IndexingException('The fulltext index for property "' . $propertyName . '" of node "' . $node->getPath() . '" could not be retrieved; the Eel expression "' . $fulltextExtractionExpression . '" is no valid fulltext extraction expression.');
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
     * @param NodeInterface $node
     * @param array $fulltextData
     * @param \Closure $nonIndexedPropertyErrorHandler
     * @return array
     * @throws IndexingException
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     */
    protected function extractPropertiesAndFulltext(NodeInterface $node, array &$fulltextData, \Closure $nonIndexedPropertyErrorHandler = null)
    {
        $nodePropertiesToBeStoredInIndex = [];
        $nodeType = $node->getNodeType();
        $fulltextIndexingEnabledForNode = $this->isFulltextEnabled($node);

        foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
            if (isset($propertyConfiguration['search']['indexing'])) {
                if ($propertyConfiguration['search']['indexing'] !== '') {
                    $valueToStore = $this->evaluateEelExpression($propertyConfiguration['search']['indexing'], $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : null));

                    $nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
                }
            } elseif (isset($propertyConfiguration['type']) && isset($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'])) {
                if ($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'] !== '') {
                    $valueToStore = $this->evaluateEelExpression($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'], $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : null));
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

    /**
     * Whether the node has fulltext indexing enabled.
     *
     * @param NodeInterface $node
     * @return boolean
     */
    protected function isFulltextEnabled(NodeInterface $node)
    {
        if ($node->getNodeType()->hasConfiguration('search')) {
            $searchSettingsForNode = $node->getNodeType()->getConfiguration('search');
            if (isset($searchSettingsForNode['fulltext']['enable']) && $searchSettingsForNode['fulltext']['enable'] === true) {
                return true;
            }
        }

        return false;
    }
}
