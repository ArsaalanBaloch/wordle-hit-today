<?php

declare(strict_types=1);

namespace WordleHitToday\Console;

use Symfony\Component\Console\Application;
use WordleHitToday\Console\Command\BackfillCommand;
use WordleHitToday\Console\Command\BackupCommand;
use WordleHitToday\Console\Command\BuildCommand;
use WordleHitToday\Console\Command\BuildFixturesCommand;
use WordleHitToday\Console\Command\CorrectCommand;
use WordleHitToday\Console\Command\DoctorCommand;
use WordleHitToday\Console\Command\FetchDateCommand;
use WordleHitToday\Console\Command\FetchPreviewDateCommand;
use WordleHitToday\Console\Command\ImportDefinitionsCommand;
use WordleHitToday\Console\Command\ImportVocabularyCommand;
use WordleHitToday\Console\Command\PreviewCommand;
use WordleHitToday\Console\Command\ReconcileCommand;
use WordleHitToday\Console\Command\RestoreCommand;
use WordleHitToday\Console\Command\RollbackCommand;
use WordleHitToday\Console\Command\StatusCommand;
use WordleHitToday\Console\Command\TickCommand;

final class ApplicationFactory
{
    public static function create(string $projectRoot): Application
    {
        $application = new Application('Wordle Hit Today publisher', '0.1.0');
        $application->addCommands([
            new DoctorCommand($projectRoot),
            new BuildFixturesCommand($projectRoot),
            new BuildCommand($projectRoot),
            new PreviewCommand($projectRoot),
            new ImportVocabularyCommand($projectRoot),
            new ImportDefinitionsCommand($projectRoot),
            new FetchDateCommand($projectRoot),
            new FetchPreviewDateCommand($projectRoot),
            new TickCommand($projectRoot),
            new StatusCommand($projectRoot),
            new BackfillCommand($projectRoot),
            new CorrectCommand($projectRoot),
            new ReconcileCommand($projectRoot),
            new BackupCommand($projectRoot),
            new RestoreCommand($projectRoot),
            new RollbackCommand($projectRoot),
        ]);

        return $application;
    }
}
