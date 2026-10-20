<?php

declare(strict_types=1);

namespace WordleHitToday\Console;

enum ExitCode: int
{
    case Success = 0;
    case InvalidInputOrConfiguration = 10;
    case SourceOrNetworkBlocked = 20;
    case StorageOrBuildFailure = 30;
}
