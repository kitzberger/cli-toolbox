<?php
namespace Kitzberger\CliToolbox\Command;

use Kitzberger\CliToolbox\Database\QueryGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeleteCommand extends AbstractCommand
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
        $this->setDescription('Recursive delete command, use with caution!');

        $this->addOption(
            'pid',
            null,
            InputOption::VALUE_REQUIRED,
            'Pid of pagetree to be deleted recursively',
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

        $pid = $input->getOption('pid');

        if (empty($pid)) {
            $this->outputLine('<error>Please specify pid!</>');
            return self::FAILURE;
        }

        $this->outputLine('memory_limit: ' . ini_get('memory_limit') . '</>');
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $this->outputLine('<bg=bright-blue>memory_limit (override!): ' . ini_get('memory_limit') . '</>');
        }

        // Get all page ids
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $list = $queryGenerator->getTreeList($pid, 99);

        $this->total = count(explode(',', $list));
        $this->count = 0;

        if ($output->isVerbose()) {
            $this->outputLine('');
            $this->outputLine('These are the pages that would be deleted:');
            $this->outputLine($list);
            $this->outputLine('');
            $this->outputLine('That\'s a total of ' . $this->total . ' pages!');
        }

        if ($this->io->confirm('Continue?', false)) {
            $this->outputLine('NOT IMPLEMENTED YET !!');
            // TODO: implement TYPO3 11 compatible recursive delete
            #$this->deleteNodeRecursively($)
        }

        return self::SUCCESS;
    }

    /**
     * Deletes a page and all of it's subpages and records on it.
     *
     * @param \TYPO3\CMS\Backend\Tree\Pagetree\PagetreeNode|int $node
     */
    private function deleteNodeRecursively($node)
    {
        if (is_integer($node)) {
            $node = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\Pagetree\PagetreeNode::class);
            $node->setId($node);
        }
        if (is_object($node)) {
            $childnodes = $this->dataProvider->getNodes($node);
            foreach ($childnodes as $childNode) {
                $this->deleteNodeRecursively($childNode);
            }
            \TYPO3\CMS\Backend\Tree\Pagetree\Commands::deleteNode($node);
        } else {
            throw new \Exception('Not a valid node!');
        }

        $this->count++;
        echo 'Status: ' . $this->count . '/' . $this->total . " deleted\n";
    }
}
