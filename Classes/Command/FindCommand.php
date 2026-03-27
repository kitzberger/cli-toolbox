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
        $group = $input->getOption('group-by');
        $order = $input->getOption('order-by');
        $limit = $input->getOption('limit');
        $languages = GeneralUtility::intExplode(',', $input->getOption('languages'), true);

        $table = self::TABLE_ALIASES[$table] ?? $table;

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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if ($count) {
            $query = $queryBuilder
                ->count('*')
                ->from($table);
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
            }
            if ($enableColumns) {
                $columns = array_merge($columns, array_values($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'] ?? []));
            }
            $columns = array_filter($columns);
            $query = $queryBuilder;

            if ($group) {
                $query->selectLiteral('COUNT(*)');
            }

            $query
                ->addSelect(...$columns)
                ->from($table);

            if ($group) {
                $group = GeneralUtility::trimExplode(',', $group, true);
                foreach ($group as $column) {
                    $query->addGroupBy($column);
                }
            }

            if ($order) {
                $order = GeneralUtility::trimExplode(',', $order, true);
                foreach ($order as $column) {
                    $query->addOrderBy($column);
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
            $constraints[] = $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY));
        }

        if ($typeField && !is_null($type)) {
            $constraints[] = $queryBuilder->expr()->like($typeField, $queryBuilder->createNamedParameter($type));
        }
        if ($subtypeField && !is_null($subtype)) {
            $constraints[] = $queryBuilder->expr()->like($subtypeField, $queryBuilder->createNamedParameter($subtype));
        }

        $query->where(...$constraints);

        if ($count) {
            $number = $query->executeQuery()->fetchOne();
            $output->writeln($number);
            return self::SUCCESS;
        } else {
            $records = $query->executeQuery()->fetchAllAssociative();
            if (count($records)) {
                if (in_array('*', $columns)) {
                    $columns = array_keys($records[0]);
                }
                if ($group) {
                    $columns = array_merge(['COUNT(*)'], $columns);
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
}
