<?php

if (TYPO3_MODE === 'BE' || TYPO3_MODE === 'CLI') {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers']['CLI-Toolbox-Cleanup'] =
        \Kitzberger\CliToolbox\Command\CleanupCommandController::class;
}
