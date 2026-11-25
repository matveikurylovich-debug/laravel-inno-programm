<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GatewayController extends Controller
{
    public function verify(Request $request): Response
    {
        abort_if($request->user() === null, 401);

        return response()->noContent()
            ->header('X-Access-Token', (string) $request->bearerToken());
    }
}
