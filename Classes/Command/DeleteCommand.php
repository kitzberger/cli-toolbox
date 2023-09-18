<?php
namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeleteCommand extends Command
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

        $this->addArgument(
            'pid',
            InputArgument::REQUIRED,
            'Pid of pagetree to be deleted recursively',
        );

        $this->addArgument(
            'memory_limit',
            InputArgument::OPTIONAL,
            'Override PHP memory_limit (e.g. 512M)',
        );

        $this->addOption(
            'dry-run',
            'n',
            InputOption::VALUE_OPTIONAL,
            'Dry-run?'
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

    	$pid = $input->getArgument('pid');

    	$memoryLimit = $input->getArgument('memory_limit');
		if ($memoryLimit) {
			ini_set('memory_limit', $memoryLimit);
			$this->outputLine('Set memory_limit to: ' . ini_get('memory_limit'));
		}

		$dryRun = $input->getOption('dry-run');

		// Get all page ids
		$queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
		$list = $queryGenerator->getTreeList($pid, 99);

		$this->total = count(explode(',', $list));
		$this->count = 0;

		if ($dryRun) {
			$this->writeln('These are the pages that would be deleted:');
			$this->writeln($list);
			$this->writeln('That\'s a total of ' . $this->total . ' pages!');
			return self::SUCCESS;
		}

		// TODO: implement TYPO3 11 compatible recursive delete
		#$this->deleteNodeRecursively($)
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
