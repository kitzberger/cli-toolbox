<?php

namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TreeCommand extends AbstractCommand
{
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
            'Site identifier or uid',
        );

        $this->addArgument(
            'depth',
            InputArgument::OPTIONAL,
            'Depth of recursive pagetree lookup',
            10
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
        $depth = $input->getArgument('depth');

        if (is_numeric($root)) {
            $pid = $root;
        } else {
            $identifier = $root;
            try {
                $site = $siteFinder->getSiteByIdentifier($identifier);
            } catch (SiteNotFoundException $e) {
                $this->outputLine('<error>No site found!</>');
                return self::FAILURE;
            }

            $pid = $site->getRootPageId();
            $this->outputLine('Determining pagetree of site: ' . $identifier);
        }

        $this->outputLine('Determining pagetree of pid: ' . $pid);

        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $pidList = $queryGenerator->getTreeList($pid, $depth);

        $this->outputLine($pidList);

        return self::SUCCESS;
    }
}
