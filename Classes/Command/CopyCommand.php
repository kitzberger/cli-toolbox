<?php

namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CopyCommand extends AbstractCommand
{
    protected const DEPTH = 99;

    protected $action = 'copy';

    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('DataHandler ' . $this->action . ' command');

        $this->addOption(
            'table',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of DB table?',
            'pages'
        );

        $this->addOption(
            'source',
            null,
            InputOption::VALUE_REQUIRED,
            'pid of source page in pagetree',
        );

        $this->addOption(
            'target',
            null,
            InputOption::VALUE_REQUIRED,
            'pid of target page in pagetree',
        );

        $this->addOption(
            'be-user',
            null,
            InputOption::VALUE_OPTIONAL,
            'uid of be_user that performs this operation',
        );

        $this->addOption(
            'allowed-tables',
            null,
            InputOption::VALUE_OPTIONAL,
            'Allowed DB table?',
            '*'
        );

        $this->addOption(
            'memory-limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'Override PHP memory_limit (e.g. 512M)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $table = $input->getOption('table');
        $source = $input->getOption('source');
        $target = $input->getOption('target');
        $allowedTables = $input->getOption('allowed-tables');

        if (empty($source) || empty($target)) {
            $this->outputLine('<error>Please specify source and target!</>');
            return self::FAILURE;
        }

        $beUserOverride = $input->getOption('be-user');
        if ($beUserOverride) {
            $beUser = BackendUtility::getRecord('be_users', $beUserOverride);
            if ($beUser) {
                $GLOBALS['BE_USER']->user = $beUser;
                $GLOBALS['BE_USER']->username = $beUser['username'];
            } else {
                $output->writeln('<error>No user found with uid ' . $beUserOverride . '</>');
                return self::FAILURE;
            }
        }

        $this->outputLine('memory_limit: ' . ini_get('memory_limit') . '</>');
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $this->outputLine('<bg=bright-blue>memory_limit (override!): ' . ini_get('memory_limit') . '</>');
        }

        $this->outputLine('');

        $cmd = [
            $table => [
                $source => [
                    $this->action => $target
                ],
            ],
        ];

        // Log in user!
        $GLOBALS['BE_USER']->backendCheckLogin();

        $this->outputLine(
            '<info>Running command as \'%s\' (%sadmin)</>',
            [
                $GLOBALS['BE_USER']->getUserName(),
                $GLOBALS['BE_USER']->isAdmin() ? '' : 'no '
            ]
        );

        if ($output->isVerbose()) {
            $this->outputLine(print_r($cmd, true));
            if ($allowedTables !== '*') {
                $this->outputLine('Allowed tables: ' . $allowedTables . '</>');
            }
        }

        if ($this->io->confirm('Continue?', true)) {
            // Make sure result is pretty ;-)
            ExtensionManagementUtility::addPageTSConfig('TCEMAIN.table.' . $table . '.disableHideAtCopy = 1');
            ExtensionManagementUtility::addPageTSConfig('TCEMAIN.table.' . $table . '.disablePrependAtCopy = 1');

            // Perform operation
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->copyTree = self::DEPTH;
            $dataHandler->bypassAccessCheckForRecords = true;
            $dataHandler->copyWhichTables = $allowedTables;
            $dataHandler->start([], $cmd, $GLOBALS['BE_USER']);
            $dataHandler->process_cmdmap();

            // Any problems?
            if (!empty($dataHandler->errorLog)) {
                $this->io->writeln('<error>Errors while ' . $this->action . 'ing page tree!</>');
                $this->logger->error('Errors while ' . $this->action . 'ing page tree!');
                foreach ($dataHandler->errorLog as $log) {
                    $this->io->writeln('<error>' . $log . '</>');
                    $this->logger->error($log);
                }
                return self::FAILURE;
            }

            if ($this->action === 'copy') {
                $this->outputLine('New uid: ' . $dataHandler->copyMappingArray_merged[$table][$source]);
            }
        }

        return self::SUCCESS;
    }
}
