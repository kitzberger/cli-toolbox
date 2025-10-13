<?php

namespace Kitzberger\CliToolbox\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MoveFalFolderCommand extends CopyCommand
{
    /**
     * @var SymfonyStyle
     */
    protected $io = null;

    private StorageRepository $storageRepository;

    public function __construct(StorageRepository $storageRepository)
    {
        $this->storageRepository = $storageRepository;
        return parent::__construct();
    }

    /**
     * Configure the command by defining the name
     */
    protected function configure()
    {
        $this->setDescription('Move a FAL folder');

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'Combined identifier of folder to be moved, e.g. 1:/folder-a',
        );

        $this->addArgument(
            'target',
            InputArgument::REQUIRED,
            'Combined identifier of target folder, e.g. 2:/different/folder',
        );

        // $this->addOption(
        //     'source',
        //     null,
        //     InputOption::VALUE_REQUIRED,
        //     'Combined identifier of folder to be moved, e.g. 1:/folder-a',
        // );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $source = $input->getArgument('source');
        $target = $input->getArgument('target');

        if (!str_contains($source, ':') || !str_contains($target, ':')) {
            $this->io->error('Illegal parameters!');
            return self::FAILURE;
        }

        [$sourceStorageUid, $sourcePath] = GeneralUtility::trimExplode(':', $source, true);
        [$targetStorageUid, $targetPath] = GeneralUtility::trimExplode(':', $target, true);

        $sourcePath = '/' . ltrim($sourcePath, '/');
        $targetPath = '/' . ltrim($targetPath, '/');

        $sourceStorage = $this->storageRepository->getStorageObject($sourceStorageUid);
        $targetStorage = $this->storageRepository->getStorageObject($targetStorageUid);

        if (! $sourceStorage instanceof ResourceStorage) {
            $this->io->error('No storage found.');
            return self::FAILURE;
        }

        if (! $targetStorage instanceof ResourceStorage) {
            $this->io->error('No storage found.');
            return self::FAILURE;
        }


        try {
            // Get folders
            $sourceFolder = $sourceStorage->getFolder($sourcePath);
            $targetFolder = $targetStorage->getFolder($targetPath);

            // Create absolute paths
            $sourceStoragePath = trim($sourceStorage->getStorageRecord()['configuration']['basePath'], '/');
            $targetStoragePath = trim($targetStorage->getStorageRecord()['configuration']['basePath'], '/');

            $absSourcePath = realpath(Environment::getPublicPath() . '/' . $sourceStoragePath . $sourceFolder->getIdentifier());
            $absTargetPath = realpath(Environment::getPublicPath() . '/' . $targetStoragePath . $targetFolder->getIdentifier());


            if (is_dir($absSourcePath) && is_dir($absTargetPath)) {
                // Ask user
                $this->io->text('Should we process with moving ...?');
                $this->io->text('- the content of: ' . $absSourcePath);
                $this->io->text('- to this folder: ' . $absTargetPath);

                if ($this->io->confirm('Move it?', false)) {
                    // Step 1: Update sys_file records

                    $this->io->text('- Updating sys_file records');

                    /** @var File[] $files */
                    $files = $sourceStorage->getFilesInFolder($sourceFolder, 0, 0, false, true);
                    foreach ($files as $file) {
                        $this->io->text('  - sys_file:' . $file->getUid());

                        $newIdentifier = str_replace($sourcePath, $targetPath, $file->getIdentifier());
                        $db = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file');
                        $db->update(
                            'sys_file',
                            [
                                'storage' => $targetStorage->getUid(),
                                'identifier' => $newIdentifier,
                            ],
                            [
                                'uid' => $file->getUid(),
                            ]
                        );
                    }

                    // Step 2: Move folder contents

                    $this->io->text('- Moving files');
                    rename($absSourcePath, $absTargetPath);

                    // Step 3: Fix file/folder hashes
                    $this->io->text('- Updating hashes');
                    // This triggers the storage driver to update each sys_file regarding its identifier_hash and folder_hash
                    $files = $targetStorage->getFilesInFolder($targetFolder, 0, 0, false, true);

                    $this->io->success('Successfully moved files/folders!');
                }
            } else {
                $this->io->error('Source or target isn\'t a folder!');
                return self::FAILURE;
            }
        } catch (InsufficientFolderAccessPermissionsException $e) {
            $this->io->error($e->getMessage());
            return self::FAILURE;
            // ... do some exception handling
        }

        return self::SUCCESS;
    }
}
