<?php
namespace Kitzberger\CliToolbox\Command;

use \TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Backend\Utility\BackendUtility;
use \TYPO3\CMS\Core\DataHandling\DataHandler;
use \TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class TceMainCommandController extends CommandController
{
	protected function init($beUser, $uid, $pid, $table, $dryRun, $verbose, $memoryLimit)
	{
		if (is_null($beUser)) {
			echo 'Please specify a backend user!' . PHP_EOL;
			exit;
		}

		if (is_null($uid)) {
			echo 'Please specify source uid!' . PHP_EOL;
			exit;
		}

		if (is_null($pid)) {
			echo 'Please specify target pid!' . PHP_EOL;
			exit;
		}

		if ($memoryLimit) {
			ini_set('memory_limit', $memoryLimit);
			echo 'Set memory_limit to: ' . ini_get('memory_limit') . "\n";
		}

		$this->fakeBeUser = clone $GLOBALS['BE_USER'];
		$this->fakeBeUser->user = BackendUtility::getRecord('be_users', $beUser);

		$this->uid = $uid;
		$this->pid = $pid;
		$this->table = $table;
		$this->dryRun = $dryRun;
		$this->verbose = $verbose;
	}

	protected function executeCmd($cmd)
	{
		if ($this->verbose) {
			echo 'Running command as \'' . $this->fakeBeUser->user['username'] . '\'' . PHP_EOL;
			print_r($cmd);
		}

		$tce = GeneralUtility::makeInstance(DataHandler::class);
		$tce->stripslashes_values = 0;
		$tce->start(array(), $cmd, $this->fakeBeUser);
		$tce->process_cmdmap();

		if ($tce->errorLog) {
			print_r($tce->errorLog);
		}

		return $tce->copyMappingArray_merged[$this->table][$this->uid];
	}

	/**
	 * TCEmain 'copy' command
	 *
	 * @param int $beUser
	 * @param int $uid source record uid
	 * @param int $pid target pid
	 * @param string $table (default: pages)
	 * @param boolean $dryRun (hide at copy?)
	 * @param boolean $verbose
	 * @param string $memoryLimit (e.g. 512M)
	 */
	public function copyCommand($beUser, $uid, $pid, $table = 'pages', $dryRun = false, $verbose = false, $memoryLimit = null)
	{
		$this->init($beUser, $uid, $pid, $table, $dryRun, $verbose, $memoryLimit);

		if ($this->dryRun === false) {
			ExtensionManagementUtility::addPageTSConfig('TCEMAIN.table.' . $table . '.disableHideAtCopy = 1');
			ExtensionManagementUtility::addPageTSConfig('TCEMAIN.table.' . $table . '.disablePrependAtCopy = 1');
		}

		$cmd[$table][$uid]['copy'] = $pid;

		$newUid = $this->executeCmd($cmd);

		echo 'Done! New uid: ' . $newUid . ($this->dryRun ? ' (hidden)' : '') . PHP_EOL;
	}

	/**
	 * TCEmain 'move' command
	 *
	 * @param int $beUser
	 * @param int $uid source record uid
	 * @param int $pid target pid
	 * @param string $table (default: pages)
	 * @param boolean $dryRun (currently without any consequences)
	 * @param boolean $verbose
	 * @param string $memoryLimit (e.g. 512M)
	 */
	public function moveCommand($beUser, $uid, $pid, $table = 'pages', $dryRun = false, $verbose = false, $memoryLimit = null)
	{
		$this->init($beUser, $uid, $pid, $table, $dryRun, $verbose, $memoryLimit);

		$cmd[$table][$uid]['move'] = $pid;

		$this->executeCmd($cmd);

		echo 'Done!' . PHP_EOL;
	}
}
