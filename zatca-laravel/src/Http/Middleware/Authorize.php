<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::has('viewZatcaDashboard') || Gate::allows('viewZatcaDashboard')) {
            return $next($request);
        }

        abort(403);
    }
}
