<?php

namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UpdateCommand extends AbstractCommand
{
    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('DataHandler update command, use with caution!');

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
            'Uid of record to be updated',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $source = $input->getOption('source');
        $table = $input->getOption('table');
        $noInteraction = $input->getOption('no-interaction');

        if (empty($source)) {
            $this->outputLine('<error>Please specify source!</>');
            return self::FAILURE;
        }

        $data = [
            $table => [
                $source => [
                    $GLOBALS['TCA'][$table]['ctrl']['tstamp'] => time(),
                ],
            ],
        ];

        if ($output->isVerbose()) {
            $this->outputLine(print_r($data, true));
        }

        if ($noInteraction || $this->io->confirm('Continue?', false)) {
            // Log in user!
            $GLOBALS['BE_USER']->backendCheckLogin();

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($data, [], $GLOBALS['BE_USER']);
            $dataHandler->process_datamap();

            // Any problems?
            if (!empty($dataHandler->errorLog)) {
                $this->io->writeln('<error>Errors while updating page tree!</>');
                $this->logger->error('Errors while updating page tree!');
                foreach ($dataHandler->errorLog as $log) {
                    $this->io->writeln('<error>' . $log . '</>');
                    $this->logger->error($log);
                }
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
