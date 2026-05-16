<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Concerns\RespondsWithJson;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

abstract class BaseApiController extends Controller
{
    use AuthorizesRequests;
    use RespondsWithJson;
}
