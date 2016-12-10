<?php
namespace Neos\Flow\Core\Migrations;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Configuration\ConfigurationManager;

/**
 * Adjusts code to package renaming from "TYPO3.TYPO3CR.Search" to "Neos.ContentRepository.Search"
 */
class Version20161210231100 extends AbstractMigration
{

    public function getIdentifier()
    {
        return 'Neos.ContentRepository.Search-20161210231100';
    }

    /**
     * @return void
     */
    public function up()
    {
        $this->searchAndReplace('TYPO3\TYPO3CR\Search', 'Neos\Flow');
        $this->searchAndReplace('TYPO3.TYPO3CR', 'Neos.ContentRepository.Search');
        $this->searchAndReplace('typo3/typo3cr-search', 'neos/content-repository-search');

        $this->moveSettingsPaths('TYPO3.TYPO3CR.Search', 'Neos.ContentRepository.Search');
    }
}
