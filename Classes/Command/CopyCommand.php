<?php

namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CopyCommand extends Command
{
    protected $table = 'pages';
    protected $action = 'copy';

    /**
     * @var SymfonyStyle
     */
    protected $io = null;

    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('DataHandler ' . $this->action . ' command');

        $this->addArgument(
            'be_user',
            InputArgument::REQUIRED,
            'uid of be_user that performs this operation',
        );

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'pid of source page in pagetree',
        );

        $this->addArgument(
            'target',
            InputArgument::REQUIRED,
            'pid of target page in pagetree',
        );

        $this->addArgument(
            'memory_limit',
            InputArgument::OPTIONAL,
            'Override PHP memory_limit (e.g. 512M)',
        );

        // $this->addOption(
        //     'dry-run',
        //     'n',
        //     InputOption::VALUE_OPTIONAL,
        //     'Dry-run?'
        // );
    }

    /**
     * Executes the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $source = $input->getArgument('source');
        $target = $input->getArgument('target');

        $memoryLimit = $input->getArgument('memory_limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $this->outputLine('Set memory_limit to: ' . ini_get('memory_limit'));
        }

        #$dryRun = $input->getOption('dry-run');

        // Make sure result is pretty ;-)
        ExtensionManagementUtility::addPageTSConfig('TCEMAIN.table.' . $table . '.disableHideAtCopy = 1');
        ExtensionManagementUtility::addPageTSConfig('TCEMAIN.table.' . $table . '.disablePrependAtCopy = 1');

        $fakeBeUser = clone $GLOBALS['BE_USER'];
        $fakeBeUser->user = BackendUtility::getRecord('be_users', $beUser);

        $cmd = [
            $table => [
                $uid => [
                    $this->action => $pid
                ],
            ],
        ];

        if ($output->isVerbose()) {
            $this->outputLine('Running command as \'' . $fakeBeUser->user['username'] . '\'');
            $this->outputLine(print_r($cmd, true));
        }

        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->stripslashes_values = 0;
        $tce->start([], $cmd, $fakeBeUser);
        $tce->process_cmdmap();

        if ($tce->errorLog) {
            print_r($tce->errorLog);
        }

        return $tce->copyMappingArray_merged[$this->table][$uid];
    }
}
