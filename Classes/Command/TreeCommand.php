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

        $this->addOption(
            'languages',
            null,
            InputOption::VALUE_OPTIONAL,
            'Comma separated list of sys_language_uids',
            '0'
        );

        $this->addOption(
            'separator',
            null,
            InputOption::VALUE_OPTIONAL,
            'Separator used in result list?',
            ','
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $root = $input->getArgument('root');
        $depth = $input->getOption('depth');
        $table = $input->getOption('table');
        $languages = GeneralUtility::intExplode(',', $input->getOption('languages'), true);
        $separator = $input->getOption('separator');

        if (!in_array($table, array_keys(self::PARENT_FIELDS))) {
            $output->writeln('<error>Table not supported yet!</>');
            return self::FAILURE;
        }

        if ($table === 'pages') {
            if (!is_numeric($root)) {
                $identifier = $root;
                try {
                    $site = $siteFinder->getSiteByIdentifier($identifier);
                } catch (SiteNotFoundException $e) {
                    $output->writeln('<error>No site found!</>');
                    return self::FAILURE;
                }

                $root = $site->getRootPageId();
                $output->writeln('Determining root pid of site: ' . $identifier, OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        if (!is_numeric($root)) {
            $output->writeln('<error>Root node id should be numeric!</>');
            return self::FAILURE;
        }

        $output->writeln('Determining ' . $table . ' tree of root id: ' . $root, OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln(
            sprintf('Language restriction: %s', empty($languages) ? 'none' : join(', ', $languages)),
            OutputInterface::VERBOSITY_VERBOSE
        );

        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $pidList = $queryGenerator->getTreeList($root, $depth, 0, $table, self::PARENT_FIELDS[$table], $languages);

        if ($separator !== ',') {
            switch ($separator) {
                case '\n':
                    $separator = PHP_EOL;
                    break;
                case '\t':
                    $separator = chr(9);
                    break;
            }
            $pidList = GeneralUtility::intExplode(',', $pidList, true);
            $pidList = join($separator, $pidList);
        }

        $output->write($pidList);

        return self::SUCCESS;
    }
}
