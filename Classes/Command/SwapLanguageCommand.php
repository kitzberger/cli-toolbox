<?php

namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SwapLanguageCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setDescription('Swap the default language of records on a given page');

        $this->addOption(
            'table',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of DB table?',
            'tt_content'
        );

        $this->addOption(
            'pid',
            null,
            InputOption::VALUE_REQUIRED,
            'Pid of the page to process',
        );

        $this->addOption(
            'language',
            null,
            InputOption::VALUE_REQUIRED,
            'sys_language_uid to promote to default (swaps 0 <-> L)',
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Print the planned UPDATEs without executing them',
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

        $table = $input->getOption('table');
        $pid = $input->getOption('pid');
        $language = (int)$input->getOption('language');
        $dryRun = $input->getOption('dry-run');

        if (empty($pid)) {
            $this->io->error('Please specify --pid!');
            return self::FAILURE;
        }
        if (empty($language)) {
            $this->io->error('Please specify --language (greater than 0)!');
            return self::FAILURE;
        }

        if (!isset($GLOBALS['TCA'][$table])) {
            $this->io->error('Table ' . $table . ' is not configured in TCA!');
            return self::FAILURE;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null;
        $origPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null;
        $sourceField = $GLOBALS['TCA'][$table]['ctrl']['translationSource'] ?? null;
        $diffSourceField = $GLOBALS['TCA'][$table]['ctrl']['transOrigDiffField'] ?? null;

        if (!$languageField || !$origPointerField || !$sourceField) {
            $this->io->error(sprintf(
                'Table %s does not support translations (missing %s/%s/%s in TCA ctrl)!',
                $table,
                'languageField',
                'transOrigPointerField',
                'translationSource'
            ));
            return self::FAILURE;
        }

        $this->outputLine('memory_limit: ' . ini_get('memory_limit'));
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $this->outputLine('<bg=bright-blue>memory_limit (override!): ' . ini_get('memory_limit') . '</>');
        }

        $this->outputLine('');
        $this->outputLine(sprintf(
            '<info>Swapping default language with language %d on %s:%d</info>',
            $language,
            $table,
            $pid
        ));
        if ($dryRun) {
            $this->io->note('Dry-run mode: no changes will be made.');
        }
        $this->outputLine('');

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $defaultRecords = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq($languageField, 0),
                $queryBuilder->expr()->eq($origPointerField, 0)
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        if (empty($defaultRecords)) {
            $this->io->warning('No default-language records found on pid ' . $pid . '.');
            return self::SUCCESS;
        }

        $this->outputLine(sprintf('Found %d default-language record(s).', count($defaultRecords)));
        $this->outputLine('');

        $plan = [];
        $skipped = 0;

        foreach ($defaultRecords as $defaultRecord) {
            $dUid = (int)$defaultRecord['uid'];

            $translationBuilder = $connection->createQueryBuilder();
            $translationBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $translation = $translationBuilder
                ->select('uid')
                ->from($table)
                ->where(
                    $translationBuilder->expr()->eq($origPointerField, $translationBuilder->createNamedParameter($dUid, Connection::PARAM_INT)),
                    $translationBuilder->expr()->eq($languageField, $translationBuilder->createNamedParameter($language, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!$translation) {
                $this->outputLine(sprintf('  - %s:%d: no translation in language %d, skipping.', $table, $dUid, $language), [], OutputInterface::VERBOSITY_VERBOSE);
                $skipped++;
                continue;
            }

            $tUid = (int)$translation['uid'];

            $siblingsBuilder = $connection->createQueryBuilder();
            $siblingsBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $siblings = $siblingsBuilder
                ->select('uid')
                ->from($table)
                ->where(
                    $siblingsBuilder->expr()->eq($origPointerField, $siblingsBuilder->createNamedParameter($dUid, Connection::PARAM_INT)),
                    $siblingsBuilder->expr()->neq($languageField, 0),
                    $siblingsBuilder->expr()->neq($languageField, $siblingsBuilder->createNamedParameter($language, Connection::PARAM_INT)),
                    $siblingsBuilder->expr()->neq('uid', $siblingsBuilder->createNamedParameter($tUid, Connection::PARAM_INT))
                )
                ->executeQuery()
                ->fetchFirstColumn();

            $plan[] = [
                'd' => $dUid,
                't' => $tUid,
                'siblings' => array_map('intval', $siblings),
            ];
        }

        if (empty($plan)) {
            $this->io->warning('No translatable groups found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->outputLine(sprintf('Planned swaps: %d (skipped: %d)', count($plan), $skipped));
        $this->outputLine('');

        $diffPart = $diffSourceField ? ', ' . $diffSourceField . '=\'\'' : '';

        foreach ($plan as $group) {
            $dUid = $group['d'];
            $tUid = $group['t'];
            $siblings = $group['siblings'];

            $this->outputLine(sprintf('<info>%s:%d</info> (default)  <->  <info>%s:%d</info> (language %d)', $table, $dUid, $table, $tUid, $language));
            if (!empty($siblings)) {
                $this->outputLine(sprintf('  siblings: %s', implode(', ', $siblings)));
            }

            if ($output->isVerbose()) {
                $this->outputLine(sprintf(
                    '  UPDATE %s SET %s=0, %s=0, %s=0%s WHERE uid=%d',
                    $table, $languageField, $origPointerField, $sourceField, $diffPart, $tUid
                ));
                $this->outputLine(sprintf(
                    '  UPDATE %s SET %s=%d, %s=%d, %s=%d%s WHERE uid=%d',
                    $table, $languageField, $language, $origPointerField, $tUid, $sourceField, $tUid, $diffPart, $dUid
                ));
                if (!empty($siblings)) {
                    $this->outputLine(sprintf(
                        '  UPDATE %s SET %s=%d, %s=%d%s WHERE uid IN (%s)',
                        $table, $origPointerField, $tUid, $sourceField, $tUid, $diffPart, implode(',', $siblings)
                    ));
                }
            }
            $this->outputLine('');
        }

        if ($dryRun) {
            $this->io->success('Dry run complete. No changes were made.');
            return self::SUCCESS;
        }

        $connection->beginTransaction();
        try {
            foreach ($plan as $group) {
                $dUid = $group['d'];
                $tUid = $group['t'];
                $siblings = $group['siblings'];

                $connection->update(
                    $table,
                    array_merge(
                        [$languageField => 0, $origPointerField => 0, $sourceField => 0],
                        $diffSourceField ? [$diffSourceField => ''] : []
                    ),
                    ['uid' => $tUid]
                );

                $connection->update(
                    $table,
                    array_merge(
                        [$languageField => $language, $origPointerField => $tUid, $sourceField => $tUid],
                        $diffSourceField ? [$diffSourceField => ''] : []
                    ),
                    ['uid' => $dUid]
                );

                if (!empty($siblings)) {
                    foreach ($siblings as $siblingUid) {
                        $connection->update(
                            $table,
                            array_merge(
                                [$origPointerField => $tUid, $sourceField => $tUid],
                                $diffSourceField ? [$diffSourceField => ''] : []
                            ),
                            ['uid' => $siblingUid]
                        );
                    }
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->logger->error('Swap-language failed: ' . $e->getMessage());
            $this->io->error('Swap failed, transaction rolled back: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->io->success(sprintf('Done. Swapped %d group(s).', count($plan)));

        return self::SUCCESS;
    }
}
