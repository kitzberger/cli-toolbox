<?php

namespace Kitzberger\CliToolbox\Command;

use Kitzberger\CliToolbox\Database\QueryGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FindCommand extends AbstractCommand
{
    private const EXTRA_COLUMNS = [
        'pages' => [],
        'tt_content' => [],
        'tx_powermail_domain_model_form' => [],
        'tx_powermail_domain_model_page' => [
            'form',
            'css',
        ],
        'tx_powermail_domain_model_field' => [
            'page',
            'mandatory',
            'css',
        ],
    ];

    private const TABLE_ALIASES = [
        'news' => 'tx_news_domain_model_news',
        'content' => 'tt_content',
    ];

    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('Find all records within a pagetree of a given root uid');

        $this->addArgument(
            'table',
            InputArgument::OPTIONAL,
            'table',
            'tt_content'
        );

        $this->addArgument(
            'type',
            InputArgument::OPTIONAL,
            'Type? (e.g. text or list)',
        );

        $this->addArgument(
            'subtype',
            InputArgument::OPTIONAL,
            'Subtype? (e.g. powermail_pi1)',
        );

        $this->addOption(
            'root',
            0,
            InputOption::VALUE_OPTIONAL,
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
            'languages',
            null,
            InputOption::VALUE_OPTIONAL,
            'Comma separated list of sys_language_uids',
            '0'
        );

        $this->addOption(
            'count',
            'c',
            InputOption::VALUE_NONE,
            'Count instead of select?',
        );

        $this->addOption(
            'columns',
            null,
            InputOption::VALUE_OPTIONAL,
            'Columns to select',
            null
        );

        $this->addOption(
            'enable-columns',
            'e',
            InputOption::VALUE_NONE,
            'Show enable columns?',
            null
        );

        $this->addOption(
            'site-column',
            's',
            InputOption::VALUE_NONE,
            'Add a "Site" column as first column showing each record\'s site identifier (sorted by site)',
        );

        $this->addOption(
            'online-only',
            'o',
            InputOption::VALUE_NONE,
            'Show only online records?',
            null
        );

        $this->addOption(
            'url',
            'u',
            InputOption::VALUE_NONE,
            'Render frontend URL as column?',
            null
        );

        $this->addOption(
            'group-by',
            null,
            InputOption::VALUE_OPTIONAL,
            'Group by column(s)',
            null
        );

        $this->addOption(
            'order-by',
            null,
            InputOption::VALUE_OPTIONAL,
            'Order by column(s)',
            null
        );

        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Max. number of rows',
            null
        );

        $this->addOption(
            'extract',
            null,
            InputOption::VALUE_OPTIONAL,
            'ExtractValue from XML field, e.g. pi_flexform/sDEF/switchableControllerActions',
            null
        );

        $this->addOption(
            'where',
            'w',
            InputOption::VALUE_REQUIRED,
            'Additional SQL-like WHERE clause, e.g. "sys_language_uid=0 AND doktype=254"',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $table = $input->getArgument('table');
        $type = $input->getArgument('type');
        $subtype = $input->getArgument('subtype');
        $root = $input->getOption('root');
        $depth = $input->getOption('depth');
        $count = $input->getOption('count');
        $columns = $input->getOption('columns');
        $enableColumns = $input->getOption('enable-columns');
        $addUrlColumn = $input->getOption('url');
        $onlineOnly = $input->getOption('online-only');
        $group = $input->getOption('group-by');
        $order = $input->getOption('order-by');
        $limit = $input->getOption('limit');
        $extract = $input->getOption('extract');
        $siteColumn = $input->getOption('site-column');
        $whereClause = $input->getOption('where');

        $languages = GeneralUtility::intExplode(',', $input->getOption('languages'), true);

        $table = self::TABLE_ALIASES[$table] ?? $table;

        if (!isset($GLOBALS['TCA'][$table])) {
            $output->writeln('<warning>Table ' . $table . ' doesn\'t exist!</>');

            $possibleTableNames = array_keys($GLOBALS['TCA']);
            $possibleTableNames = array_filter($possibleTableNames, fn($tableName) => preg_match('/' . $table . '/', $tableName));

            if (count($possibleTableNames)) {
                $io = new SymfonyStyle($input, $output);
                $table = $io->choice('Choose one of these', $possibleTableNames);
            } else {
                return self::FAILURE;
            }
        }

        if (empty($root)) {
            $pids = null;
        } else {
            // determining pids by looking at page tree of root parameter
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

            if (!is_numeric($root)) {
                $output->writeln('<error>Root node id should be numeric!</>');
                return self::FAILURE;
            }

            $output->writeln('Determining page tree of root id: ' . $root, OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln(
                sprintf('Language restriction: %s', empty($languages) ? 'none' : join(', ', $languages)),
                OutputInterface::VERBOSITY_VERBOSE
            );

            $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
            $pidList = $queryGenerator->getTreeList($root, $depth, 0, 'pages', 'pid', $languages);
            $pids = GeneralUtility::intExplode(',', $pidList, true);
        }

        $typeField = $GLOBALS['TCA'][$table]['ctrl']['type'] ?? null;
        $subtypeField = $GLOBALS['TCA'][$table]['types'][$type]['subtype_value_field'] ?? null;
        #dd($type, $subtype, $typeField, $subtypeField, $columns);

        if ($output->isVeryVerbose()) {
            $output->writeln('- Table: ' . $table);
            if ($typeField) {
                $output->writeln('- ' . $typeField . ': ' . $type);
            }
            if ($subtypeField) {
                $output->writeln('- ' . $subtypeField . ': ' . $subtype);
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        if ($onlineOnly === false) {
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        }

        if ($count) {
            $query = $queryBuilder
                ->count('*')
                ->from($table);
            if ($onlineOnly) {
                $query->join(
                    $table,
                    'pages',
                    'parentPage',
                    $queryBuilder->expr()->eq('parentPage.uid', $queryBuilder->quoteIdentifier($table . '.pid'))
                );
            }
        } else {
            if (empty($columns)) {
                $columns = [
                    'uid',
                    'pid',
                    $typeField ?? null,
                    $subtypeField ?? null,
                    $GLOBALS['TCA'][$table]['ctrl']['label'] ?? null,
                ];
                $columns = array_merge($columns, self::EXTRA_COLUMNS[$table] ?? []);
            } else {
                $columns = GeneralUtility::trimExplode(',', $columns, true);
                if ($addUrlColumn) {
                    if ($table === 'pages' && !in_array('uid', $columns)) {
                        $columns[] = 'uid'; // necessary to render typolinks
                    } elseif (!in_array('pid', $columns)) {
                        $columns[] = 'pid'; // necessary to render typolinks
                    }
                }
            }
            if ($enableColumns) {
                $columns = array_merge($columns, array_values($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'] ?? []));
            }
            $columns = array_filter($columns);
            $columns = array_map(fn($item) => $table . '.' . $item, $columns);

            $query = $queryBuilder;

            if ($group) {
                $query->selectLiteral('COUNT(*)');
            }

            if ($onlineOnly) {
                $columns[] = 'parentPage.title AS parentPageTitle';
                if ($enableColumns) {
                    $columns[] = 'parentPage.hidden AS parentPageHidden';
                    $columns[] = 'parentPage.deleted AS parentPageDeleted';
                    $columns[] = 'parentPage.starttime AS parentPageStarttime';
                    $columns[] = 'parentPage.endtime AS parentPageEndtime';
                    $columns[] = 'parentPage.fe_group AS parentPageFegroup';
                }
                $query->join(
                    $table,
                    'pages',
                    'parentPage',
                    $queryBuilder->expr()->eq('parentPage.uid', $queryBuilder->quoteIdentifier($table . '.pid'))
                );
            }

            $query
                ->addSelect(...$columns)
                ->from($table);

            if (!empty($extract)) {
                $extractConfigs = GeneralUtility::trimExplode(',', $extract, true);
                foreach ($extractConfigs as $extractConfig) {
                    $extractConfig = GeneralUtility::trimExplode('/', $extractConfig, true);
                    $query->addSelectLiteral(sprintf(
                        'ExtractValue(%s, \'//T3FlexForms/data/sheet[@index="%s"]/language/field[@index="%s"]/value\')',
                        $table . '.' . ($extractConfig[0] ?? 'pi_flexform'),
                        $extractConfig[1] ?? 'sDEF',
                        $extractConfig[2] ?? ''
                    ));
                }
            }

            if ($group) {
                $group = GeneralUtility::trimExplode(',', $group, true);
                foreach ($group as $column) {
                    $query->addGroupBy($table . '.' . $column);
                }
            }

            if ($order) {
                $order = GeneralUtility::trimExplode(',', $order, true);
                foreach ($order as $column) {
                    $query->addOrderBy($table . '.' . $column);
                }
            }

            if ($limit) {
                $query->setMaxResults($limit);
            }
        }

        $constraints = [];

        if ($pids === null) {
            $output->writeln('Performing a global search!', OutputInterface::VERBOSITY_VERBOSE);
        } else {
            $output->writeln('Performing a search on ' . $root . ' only!', OutputInterface::VERBOSITY_VERBOSE);
            $constraints[] = $queryBuilder->expr()->in($table . '.pid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY));
        }

        if ($typeField && !is_null($type)) {
            $constraints[] = $queryBuilder->expr()->like($table . '.' . $typeField, $queryBuilder->createNamedParameter($type));
        }
        if ($subtypeField && !is_null($subtype)) {
            $constraints[] = $queryBuilder->expr()->like($table . '.' . $subtypeField, $queryBuilder->createNamedParameter($subtype));
        }

        $query->where(...$constraints);

        if (!empty($whereClause)) {
            $conditions = $this->parseWhereConditions($whereClause);

            if (empty($conditions)) {
                throw new \InvalidArgumentException('Unparsable where clause: ' . $whereClause);
            }

            $andExpressions = [];
            $orExpressions = [];

            foreach ($conditions as $condition) {
                $column = $table . '.' . $condition['column'];
                $operator = strtoupper($condition['operator']);
                $value = $condition['value'];

                $expression = match ($operator) {
                    '=', 'EQ' => $queryBuilder->expr()->eq($column, $queryBuilder->createNamedParameter($value)),
                    '!=', '<>', 'NE' => $queryBuilder->expr()->neq($column, $queryBuilder->createNamedParameter($value)),
                    'LIKE' => $queryBuilder->expr()->like($column, $queryBuilder->createNamedParameter($value)),
                    'NOT LIKE' => $queryBuilder->expr()->notLike($column, $queryBuilder->createNamedParameter($value)),
                    '>', 'GT' => $queryBuilder->expr()->gt($column, $queryBuilder->createNamedParameter($value)),
                    '>=', 'GE' => $queryBuilder->expr()->gte($column, $queryBuilder->createNamedParameter($value)),
                    '<', 'LT' => $queryBuilder->expr()->lt($column, $queryBuilder->createNamedParameter($value)),
                    '<=', 'LE' => $queryBuilder->expr()->lte($column, $queryBuilder->createNamedParameter($value)),
                    'IN' => $queryBuilder->expr()->in($column, $queryBuilder->createNamedParameter($value, Connection::PARAM_INT_ARRAY)),
                    'NOT IN' => $queryBuilder->expr()->notIn($column, $queryBuilder->createNamedParameter($value, Connection::PARAM_INT_ARRAY)),
                    'IS NULL' => $queryBuilder->expr()->isNull($column),
                    'IS NOT NULL' => $queryBuilder->expr()->isNotNull($column),
                    default => null,
                };

                if ($expression !== null) {
                    if (strtoupper($condition['junction']) === 'OR') {
                        $orExpressions[] = $expression;
                    } else {
                        $andExpressions[] = $expression;
                    }
                }
            }

            if (!empty($orExpressions)) {
                $andExpressions[] = $queryBuilder->expr()->orX(...$orExpressions);
            }

            if (!empty($andExpressions)) {
                $query->andWhere(...$andExpressions);
            }
        }

        $output->writeln($query->getSQL(), OutputInterface::VERBOSITY_VERY_VERBOSE);

        if ($count) {
            $number = $query->executeQuery()->fetchOne();
            $output->writeln($number);
            return self::SUCCESS;
        } else {
            $records = $query->executeQuery()->fetchAllAssociative();
            if (count($records)) {
                if (in_array($table . '.*', $columns)) {
                    $columns = array_keys($records[0]);
                }
                if ($group) {
                    $columns = array_merge(['COUNT(*)'], $columns);
                }
                if (!empty($extract)) {
                    $extractConfigs = GeneralUtility::trimExplode(',', $extract, true);
                    foreach ($extractConfigs as $extractConfig) {
                        $columns[] = $extractConfig;
                    }
                }
                $columns = array_map(fn($item) => str_replace($table . '.', '', $item), $columns);
                $columns = array_map(fn($item) => preg_replace('/ AS parentPage.*/', '', $item), $columns);
                if ($addUrlColumn) {
                    $columns[] = '[URL]';
                    foreach ($records as &$record) {
                        $record['url'] = $this->typolink(
                            $table === 'pages' ? $record['uid'] : $record['pid']
                        );
                    }
                    unset($record);
                }
                if ($siteColumn) {
                    $columns = array_merge(['Site'], $columns);

                    $pidToSite = [];
                    $uniquePids = array_unique(array_map(fn($record) => (int)($record['pid'] ?? 0), $records));
                    foreach ($uniquePids as $pid) {
                        try {
                            $pidToSite[$pid] = $siteFinder->getSiteByPageId($pid)->getIdentifier();
                        } catch (\Throwable $e) {
                            $pidToSite[$pid] = 'n/a';
                        }
                    }

                    foreach ($records as &$record) {
                        $record['site'] = $pidToSite[(int)($record['pid'] ?? 0)] ?? 'n/a';
                    }
                    unset($record);

                    usort($records, fn($a, $b) => strnatcasecmp($a['site'], $b['site']));

                    foreach ($records as &$record) {
                        $record = array_merge(['Site' => $record['site']], $record);
                        unset($record['site']);
                    }
                    unset($record);
                }
                $this->renderTable($output, $columns, $records);
                $output->writeln(count($records) . ' records found.');
            } else {
                $output->writeln('<warning>No records found.</warning>');
            }
        }

        return self::SUCCESS;
    }

    protected function renderTable(OutputInterface $output, array $headers, array $rows)
    {
        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render();
    }

    /**
     * For rendering typolinks in PHP
     */
    protected function typolink($pageId, $arguments = []): string
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        } catch (SiteNotFoundException $e) {
            return $e->getMessage();
        }

        try {
            $url = $site->getRouter()->generateUri($pageId, $arguments);
        } catch (InvalidRouteArgumentsException $e) {
            return $e->getMessage();
        }

        return $url;
    }

    /**
     * Parse a SQL-like WHERE clause into an array of conditions.
     *
     * Each condition has: column, operator, value, junction (AND/OR).
     * Respects quoted strings when splitting by AND/OR.
     *
     * @return array<array{column: string, operator: string, value: mixed, junction: string}>
     */
    private function parseWhereConditions(string $whereClause): array
    {
        $conditions = [];
        $parts = preg_split('/\b(AND|OR)\b/i', $whereClause, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $currentJunction = 'AND';
        $supportedOperators = ['NOT LIKE', 'IS NOT NULL', 'IS NULL', '>=', '<=', '!=', '<>', 'LIKE', 'NOT IN', 'IN', '>', '<', '='];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $upperPart = strtoupper($part);
            if ($upperPart === 'AND' || $upperPart === 'OR') {
                $currentJunction = $upperPart;
                continue;
            }

            foreach ($supportedOperators as $operator) {
                // Matches operator surrounded by optional whitespace
                $pattern = '/\s*' . preg_quote($operator, '/') . '\s*/';
                if (preg_match($pattern, $part, $matches, PREG_OFFSET_CAPTURE)) {
                    $column = trim(substr($part, 0, $matches[0][1]));
                    $value = trim(substr($part, $matches[0][1] + strlen($matches[0][0])));

                    $conditions[] = [
                        'column' => $column,
                        'operator' => $operator,
                        'value' => $this->parseWhereValue($value),
                        'junction' => $currentJunction,
                    ];
                    break;
                }
            }
        }

        return $conditions;
    }

    /**
     * Parse a WHERE value into an appropriate PHP type.
     *
     * - 'NULL' (unquoted) → null
     * - '1,2,3' (after stripping parentheses) → array of ints
     * - 'string' (quoted) → unquoted string
     * - numeric string → int
     * - everything else → string
     */
    private function parseWhereValue(string $value): mixed
    {
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if ($value[0] === '(' && $value[strlen($value) - 1] === ')') {
            $inner = substr($value, 1, -1);
            return array_map('intval', array_map('trim', explode(',', $inner)));
        }

        if (preg_match('/^(\'(.*)\'|"(.*))$/', $value, $matches)) {
            return $matches[2] ?? $matches[3];
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return $value;
    }
}
