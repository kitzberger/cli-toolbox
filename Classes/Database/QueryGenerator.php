<?php

declare(strict_types=1);

namespace Kitzberger\CliToolbox\Database;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Duplication of \TYPO3\CMS\Core\Database\QueryGenerator which has been deprecated/removed
 * @extensionScannerIgnoreFile
 */
class QueryGenerator
{
    /**
     * Recursively fetch all descendants of a given record
     *
     * @param int $id uid of the record
     * @param int $depth
     * @param int $begin
     * @param string $table
     * @param string $parentField
     *
     * @return string comma separated list of descendant pages
     */
    public function getTreeList($id, $depth, $begin = 0, $table = 'pages', $parentField = 'pid'): string
    {
        $depth = (int)$depth;
        $begin = (int)$begin;
        $id = (int)$id;
        if ($id < 0) {
            $id = abs($id);
        }
        if ($begin === 0) {
            $theList = $id;
        } else {
            $theList = '';
        }
        if ($id && $depth > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $queryBuilder->select('uid')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq($parentField, $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', 0)
                )
                ->orderBy('uid');

            $statement = $queryBuilder->executeQuery();
            while ($row = $statement->fetchAssociative()) {
                if ($begin <= 0) {
                    $theList .= ',' . $row['uid'];
                }
                if ($depth > 1) {
                    $theSubList = $this->getTreeList($row['uid'], $depth - 1, $begin - 1, $table, $parentField);
                    if (!empty($theList) && !empty($theSubList) && ($theSubList[0] !== ',')) {
                        $theList .= ',';
                    }
                    $theList .= $theSubList;
                }
            }
        }
        return (string)$theList;
    }
}
