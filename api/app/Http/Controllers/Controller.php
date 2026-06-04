<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SolarIncentives API',
    description: 'Clean-energy incentive platform — projects, multi-step applications, status workflows, documents, notifications.'
)]
#[OA\Server(url: 'http://localhost:8000/api', description: 'Local')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer')]
abstract class Controller
{
    use AuthorizesRequests;
}
