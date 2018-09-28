<?php
namespace Kitzberger\CliToolbox\Command;

use \TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

class CleanupCommandController extends CommandController
{

	/**
	 * Recursive delete command, use with caution!
	 *
	 * @param int $pid
	 * @param boolean $dryRun
	 */
	public function deleteCommand($pid = null, $dryRun = false)
	{
		if (is_null($pid)) {
			echo 'Please specify pid!' . "\n";
			exit;
		}

		// Get all page ids
		$queryGenerator = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\QueryGenerator::class);
		$list = $queryGenerator->getTreeList($pid, 99, 0, '1=1');

		$this->total = count(explode(',', $list));
		$this->count = 0;

		if ($dryRun) {
			echo 'These are the pages that would be deleted:' . "\n";
			echo $list . "\n";
			echo 'That\'s a total of ' . $this->total . ' pages!' . "\n";
			exit;
		}

		// Make CLI user an admin, so we're allowed to delete everything!
		$GLOBALS['BE_USER']->user['admin'] = 1;
		$GLOBALS['BE_USER']->setWorkspace(0);

		$this->dataProvider = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Tree\Pagetree\DataProvider::class);

		// Start deleting page(s)
		$this->deleteNodeRecursively($pid);

		echo 'Deleted page ' . $pid . ' and all of the subpages and records on them!' . "\n";
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
