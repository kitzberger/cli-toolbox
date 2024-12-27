<?php

namespace Kitzberger\CliToolbox\Command;

use Kitzberger\CliToolbox\Database\QueryGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeleteCommand extends AbstractCommand
{
    /**
     * @var SymfonyStyle
     */
    protected $io = null;

    protected $action = 'delete';

    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('Recursive ' . $this->action . ' command, use with caution!');

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
            'Uid of node to be ' . $this->action . 'd recursively',
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

        $source = $input->getOption('source');
        $table = $input->getOption('table');

        if (empty($source)) {
            $this->outputLine('<error>Please specify source!</>');
            return self::FAILURE;
        }

        $this->outputLine('memory_limit: ' . ini_get('memory_limit') . '</>');
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $this->outputLine('<bg=bright-blue>memory_limit (override!): ' . ini_get('memory_limit') . '</>');
        }

        $cmd = [
            $table => [
                $source => [
                    $this->action => 1,
                ],
            ],
        ];


        if ($output->isVerbose()) {
            // Get all page ids
            $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
            $list = $queryGenerator->getTreeList($source, 99, 0, $table);
            $total = count(explode(',', $list));

            $this->outputLine('');
            $this->outputLine('These are the records that would be ' . $this->action . 'd:');
            $this->outputLine($list);
            $this->outputLine('');
            $this->outputLine('That\'s a total of ' . $total . ' ' . $table . ' records!');

            $this->outputLine(print_r($cmd, true));
        }

        if ($this->io->confirm('Continue?', false)) {
            // Log in user!
            $GLOBALS['BE_USER']->backendCheckLogin();

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
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
        }

        return self::SUCCESS;
    }
}
