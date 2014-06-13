<?php
namespace TYPO3\TYPO3CR\Search\Eel;

	/*                                                                        *
	 * This script belongs to the TYPO3 Flow package "TYPO3.Eel".             *
	 *                                                                        *
	 * It is free software; you can redistribute it and/or modify it under    *
	 * the terms of the GNU Lesser General Public License, either version 3   *
	 * of the License, or (at your option) any later version.                 *
	 *                                                                        *
	 * The TYPO3 project - inspiring people to share!                         *
	 *                                                                        */
use TYPO3\Eel\EelEvaluatorInterface;
use TYPO3\Eel\Exception;
use TYPO3\Eel\Package;

/**
 * Utility to reduce boilerplate code needed to set default context variables and evaluate a string that possibly is an EEL expression.
 *
 */
class EelUtility {

	/**
	 * Get variables from configuration that should be set in the context by default.
	 * For example Eel helpers are made available by this.
	 *
	 * @param array $configuration An one dimensonal assocative array of context variable paths mapping to object names
	 * @return array Array with default context variable objects.
	 */
	static public function getDefaultContextVariables(array $configuration) {
		$defaultContextVariables = array();
		foreach ($configuration as $variableName => $objectType) {
			$currentPathBase = & $defaultContextVariables;
			$variablePathNames = explode('.', $variableName);
			foreach ($variablePathNames as $pathName) {
				if (!isset($currentPathBase[$pathName])) {
					$currentPathBase[$pathName] = array();
				}
				$currentPathBase = & $currentPathBase[$pathName];
			}
			$currentPathBase = new $objectType();
		}

		return $defaultContextVariables;
	}

	/**
	 * @param string $expression
	 * @param EelEvaluatorInterface $eelEvaluator
	 * @param array $contextVariables
	 * @param array $defaultContextConfiguration
	 * @return mixed
	 * @throws Exception
	 */
	static public function evaluateEelExpression($expression, EelEvaluatorInterface $eelEvaluator, array $contextVariables, array $defaultContextConfiguration = array()) {
		$matches = NULL;
		if (preg_match(Package::EelExpressionRecognizer, $expression, $matches)) {
			$defaultContextVariables = self::getDefaultContextVariables($defaultContextConfiguration);
			$contextVariables = array_merge($defaultContextVariables, $contextVariables);
			$context = new \TYPO3\Eel\Context($contextVariables);

			$value = $eelEvaluator->evaluate($matches['exp'], $context);

			return $value;
		} else {
			throw new Exception('The EEL expression "' . $expression . '" was not a valid EEL expression. Perhaps you forgot to wrap it in ${...}?', 1400008567);
		}
	}
}
