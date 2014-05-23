<?php
namespace TYPO3\TYPO3CR\SearchCommons\Indexer;

/*                                                                              *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR.SearchCommons". *
 *                                                                              *
 * It is free software; you can redistribute it and/or modify it under          *
 * the terms of the GNU General Public License, either version 3                *
 *  of the License, or (at your option) any later version.                      *
 *                                                                              *
 * The TYPO3 project - inspiring people to share!                               *
 *                                                                              */

use TYPO3\TYPO3CR\Domain\Model\Node;
use TYPO3\TYPO3CR\SearchCommons\Eel\EelUtility;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\SearchCommons\Exception\IndexingException;

/**
 *
 * @Flow\Scope("singleton")
 */
abstract class AbstractNodeIndexer implements NodeIndexerInterface {

	/**
	 * @Flow\Inject(lazy=FALSE)
	 * @var \TYPO3\Eel\CompilingEvaluator
	 */
	protected $eelEvaluator;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Configuration\ConfigurationManager
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
	 */
	public function initializeObject($cause) {
		if ($cause === \TYPO3\Flow\Object\ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
			$this->settings = $this->configurationManager->getConfiguration(\TYPO3\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.TYPO3CR.SearchCommons');
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
	 * @throws Exception
	 */
	protected function evaluateEelExpression($expression, Node $node, $propertyName, $value) {
		if ($this->defaultContextVariables === NULL) {
			$this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->settings['defaultContext']);
		}

		$contextVariables = array_merge($this->defaultContextVariables, array(
			'node' => $node,
			'propertyName' => $propertyName,
			'value' => $value,
		));

		return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
	}

	/**
	 * @param Node $node
	 * @param string $propertyName
	 * @param string $fulltextExtractionExpression
	 * @param array $fulltextIndexOfNode
	 * @throws IndexingException
	 */
	protected function extractFulltext(Node $node, $propertyName, $fulltextExtractionExpression, array &$fulltextIndexOfNode) {
		if ($fulltextExtractionExpression !== '') {
			$extractedFulltext = $this->evaluateEelExpression($fulltextExtractionExpression, $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : NULL));

			if (!is_array($extractedFulltext)) {
				throw new IndexingException('The fulltext index for property "' . $propertyName . '" of node "' . $node->getPath() . '" could not be retrieved; the Eel expression "' . $fulltextExtractionExpression . '" is no valid fulltext extraction expression.');
			}

			foreach ($extractedFulltext as $bucket => $value) {
				if (!isset($fulltextIndexOfNode[$bucket])) {
					$fulltextIndexOfNode[$bucket] = '';
				}
				$fulltextIndexOfNode[$bucket] .= ' ' . $value;
			}
		}
		// TODO: also allow fulltextExtractor in settings!!
	}

	/**
	 * Extracts all property values according to configuration and additionally adds to the referenced fulltextData array if needed.
	 *
	 * @param Node $node
	 * @param array $fulltextData
	 * @return array
	 */
	protected function extractPropertiesAndFulltext(Node $node, array &$fulltextData, \Closure $nonIndexedPropertyErrorHandler = NULL) {
		$nodePropertiesToBeStoredInIndex = array();
		$nodeType = $node->getNodeType();
		$fulltextIndexingEnabledForNode = $this->isFulltextEnabled($node);

		foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
			if (isset($propertyConfiguration['search']['indexing'])) {
				if ($propertyConfiguration['search']['indexing'] !== '') {
					$valueToStore = $this->evaluateEelExpression($propertyConfiguration['search']['indexing'], $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : NULL));

					$nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
				}
			} elseif (isset($propertyConfiguration['type']) && isset($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'])) {
				if ($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'] !== '') {
					$valueToStore = $this->evaluateEelExpression($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'], $node, $propertyName, ($node->hasProperty($propertyName) ? $node->getProperty($propertyName) : NULL));
					$nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
				}
			} else {
				// error handling if configured
				if ($nonIndexedPropertyErrorHandler !== NULL) {
					$nonIndexedPropertyErrorHandler($propertyName);
				}
			}

			if ($fulltextIndexingEnabledForNode === TRUE && isset($propertyConfiguration['search']['fulltextExtractor'])) {
				$this->extractFulltext($node, $propertyName, $propertyConfiguration['search']['fulltextExtractor'], $fulltextData);
			}

		}

		return $nodePropertiesToBeStoredInIndex;
	}

	/**
	 * Whether the node has fulltext indexing enabled.
	 *
	 * @param Node $node
	 * @return boolean
	 */
	protected function isFulltextEnabled(Node $node) {
		if ($node->getNodeType()->hasConfiguration('search')) {
			$searchSettingsForNode = $node->getNodeType()->getConfiguration('search');
			if (isset($searchSettingsForNode['fulltext']['enable']) && $searchSettingsForNode['fulltext']['enable'] === TRUE) {
				return TRUE;
			}
		}

		return FALSE;
	}
}