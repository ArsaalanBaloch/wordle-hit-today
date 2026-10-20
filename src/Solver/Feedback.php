<?php

declare(strict_types=1);

namespace WordleHitToday\Solver;

enum Feedback: string
{
    case Unset = 'unset';
    case Absent = 'absent';
    case Present = 'present';
    case Correct = 'correct';
}
