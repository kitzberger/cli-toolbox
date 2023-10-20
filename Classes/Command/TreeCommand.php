<?php

namespace Kitzberger\CliToolbox\Command;

use Kitzberger\CliToolbox\Database\QueryGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TreeCommand extends AbstractCommand
{
    private const PARENT_FIELDS = [
        'pages' => 'pid',
        'sys_category' => 'parent',
    ];

    /**
     * @var SymfonyStyle
     */
    protected $io = null;

    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('Determine all uids of a pagetree of a given root uid');

        $this->addArgument(
            'root',
            InputArgument::REQUIRED,
            'root node uid (or site identifier)',
        );

        $this->addOption(
            'depth',
            null,
            InputOption::VALUE_OPTIONAL,
            'Depth of recursive pagetree lookup',
            10
        );

        $this->addOption(
            'table',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of DB table?',
            'pages'
        );
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

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $root = $input->getArgument('root');
        $depth = $input->getOption('depth');
        $table = $input->getOption('table');

        if (!in_array($table, array_keys(self::PARENT_FIELDS))) {
            $this->outputLine('<error>Table not supported yet!</>');
            return self::FAILURE;
        }

        if ($table === 'pages') {
            if (!is_numeric($root)) {
                $identifier = $root;
                try {
                    $site = $siteFinder->getSiteByIdentifier($identifier);
                } catch (SiteNotFoundException $e) {
                    $this->outputLine('<error>No site found!</>');
                    return self::FAILURE;
                }

                $root = $site->getRootPageId();
                $this->outputLine('Determining root pid of site: ' . $identifier);
            }
        }

        if (!is_numeric($root)) {
            $this->outputLine('<error>Root node id should be numeric!</>');
            return self::FAILURE;
        }

        $this->outputLine('Determining ' . $table . ' tree of root id: ' . $root);

        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $pidList = $queryGenerator->getTreeList($root, $depth, 0, $table, self::PARENT_FIELDS[$table]);

        $this->outputLine($pidList);

        return self::SUCCESS;
    }
}
