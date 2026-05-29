<?php

declare(strict_types=1);

namespace App\Enums;

enum LevelOfOperations: string
{
    case Local = 'local';
    case Regional = 'regional';
    case International = 'international';
}
