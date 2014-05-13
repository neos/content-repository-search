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

use TYPO3\Eel\Utility\EelUtility;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeData;

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
	 * @param NodeData $node
	 * @param string $propertyName
	 * @param mixed $value
	 * @param string $persistenceObjectIdentifier
	 * @return mixed The result of the evaluated Eel expression
	 */
	protected function evaluateEelExpression($expression, NodeData $node, $propertyName, $value, $persistenceObjectIdentifier) {
		if ($this->defaultContextVariables === NULL) {
			$this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->settings['defaultContext']);
		}

		$contextVariables = array_merge($this->defaultContextVariables, array(
			'node' => $node,
			'propertyName' => $propertyName,
			'value' => $value,
			'persistenceObjectIdentifier' => $persistenceObjectIdentifier
		));

		return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
	}

	/**
	 * @param NodeData $nodeData
	 * @param string $propertyName
	 * @param string $extractorConfiguration
	 * @param array $fulltextData
	 */
	protected function extractFulltext($nodeData, $propertyName, $extractorConfiguration, array &$fulltextData) {
		$extractedFulltext = $this->evaluateEelExpression($extractorConfiguration, $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), NULL);

		if (is_array($extractedFulltext) && count($extractedFulltext) > 0) {
			foreach ($extractedFulltext as $bucket => $text) {
				$fulltextData[$bucket] = (isset($fulltextData[$bucket]) ? ($fulltextData[$bucket] . $text) : $text);
			}
		}

		if (is_string($extractedFulltext) && !empty($extractedFulltext)) {
			$fulltextData['text'] = (isset($fulltextData['text']) ? ($fulltextData['text'] . $text) : $text);
		}
	}

	/**
	 * Extracts all property values according to configuration and additionally adds to the referenced fulltextData array if needed.
	 *
	 * @param NodeData $nodeData
	 * @param string $persistenceObjectIdentifier
	 * @param array $fulltextData
	 * @return array
	 */
	protected function extractPropertiesAndFulltext(NodeData $nodeData, $persistenceObjectIdentifier, array &$fulltextData) {
		$nodePropertiesToBeStoredInIndex = array();
		$nodeType = $nodeData->getNodeType();
		foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
			if (isset($propertyConfiguration['search']['indexing'])) {
				if ($propertyConfiguration['search']['indexing'] !== '') {
					$valueToStore = $this->evaluateEelExpression($propertyConfiguration['search']['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);

					$nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
				}
			} elseif (isset($propertyConfiguration['type']) && isset($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'])) {
				if ($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'] !== '') {
					$valueToStore = $this->evaluateEelExpression($this->settings['defaultConfigurationPerType'][$propertyConfiguration['type']]['indexing'], $nodeData, $propertyName, ($nodeData->hasProperty($propertyName) ? $nodeData->getProperty($propertyName) : NULL), $persistenceObjectIdentifier);
					$nodePropertiesToBeStoredInIndex[$propertyName] = $valueToStore;
				}
			}

			if (isset($propertyConfiguration['search']['fulltextExtractor'])) {
				$this->extractFulltext($nodeData, $propertyName, $propertyConfiguration['search']['fulltextExtractor'], $fulltextData);
			}
		}

		return $nodePropertiesToBeStoredInIndex;
	}

}