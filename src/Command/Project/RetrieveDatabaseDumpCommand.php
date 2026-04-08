<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Project;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Walibuy\Sweeecli\Core\Helper\FolderHelper;

class RetrieveDatabaseDumpCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('project:database:retrieve-dump')
            ->addArgument('destinationFolder', InputArgument::OPTIONAL, 'The destination folder where the dump will be saved', FolderHelper::getHomeFolder().'/Downloads')
            ->setDescription('Retrieve the database dump from the admin pod')
        ;
    }

    protected function execute($input, $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = 'arn:aws:eks:eu-west-3:096866357657:cluster/wb-web-prod';
        $namespace = 'main-api';
        $remotePath = '/srv/app/data/dump/dump.sql';

        $getPodProcess = new Process(['kubectl', 'get', 'pods', '-n', $namespace, '--context', $context]);
        $getPodProcess->run();

        if (!$getPodProcess->isSuccessful()) {
            $io->error($getPodProcess->getErrorOutput());

            return self::FAILURE;
        }

        $pods = $getPodProcess->getOutput();

        if (!preg_match('/(sweeek-api-master-admin-\S+)/', $pods, $matches)) {
            $io->error('No admin pod found in cluster');
            $io->text($pods);

            return self::FAILURE;
        }

        $adminPodName = $matches[1];

        $io->info('Retrieve dump file from pod '.$adminPodName);

        $getSizeProcess = new Process([
            'kubectl', 'exec', '-n', $namespace, '--context', $context,
            $adminPodName, '--', 'stat', '-c%s', $remotePath,
        ]);
        $getSizeProcess->run();
        $totalSize = (int) trim($getSizeProcess->getOutput());

        if (!$getSizeProcess->isSuccessful()) {
            $io->error($getSizeProcess->getErrorOutput());

            return self::FAILURE;
        }

        $io->text('Dump size: '.$this->convertBytesToMb($totalSize).' Mb');
        $progressBar = new ProgressBar($output, (int) $this->convertBytesToMb($totalSize));
        $progressBar->setFormat('%current%/%max% Mo [%bar%] %percent:3s%% %elapsed:6s%');
        $progressBar->start();

        $destinationFile = preg_replace('/\/$/', '', $input->getArgument('destinationFolder')).'/dump.sql';

        $downloadProcess = new Process([
            'kubectl',
            'cp',
            $namespace.'/'.$adminPodName.':/srv/app/data/dump/dump.sql',
            $destinationFile,
            '--context',
            $context,
        ]);
        $downloadProcess->start();

        while ($downloadProcess->isRunning()) {
            if (file_exists($destinationFile)) {
                clearstatcache();
                $currentSize = filesize($destinationFile);
                $progressBar->setProgress((int) $this->convertBytesToMb($currentSize));
            }
            usleep(250000);
        }

        if ($downloadProcess->isSuccessful()) {
            $progressBar->finish();

            $processSed = new Process(['sed', '-i', '', 's/\\\\-/-/g', $destinationFile]);
            $processSed->run();

            if (!$processSed->isSuccessful()) {
                $io->warning('Backslashes in dump could not be successfully removed');
            }

            $io->success('Transfer done');

            return self::SUCCESS;
        }
        $io->error($downloadProcess->getErrorOutput());

        return self::FAILURE;
    }

    private function convertBytesToMb(int $bytes): float
    {
        return round($bytes / (1024 * 1024), 2);
    }
}
