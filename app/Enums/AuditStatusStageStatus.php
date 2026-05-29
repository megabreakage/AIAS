<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditStatusStageStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
}
