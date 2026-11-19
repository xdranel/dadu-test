<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalSidangTaUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only redirect after the original controller has authorized this record.
        $response = $next($request);
        $route = $request->route();
        if ($request->isMethod('GET') && $request->attributes->get('sidang_ta_legacy_url')
            && $response->isSuccessful() && $route?->getName()) {
            $parameters = $request->query();
            foreach ($route->parameterNames() as $name) {
                $parameters[$name] = $route->parameter($name);
            }
            $url = route($route->getName(), $parameters);
            if ($url !== $request->fullUrl()) {
                return redirect()->to($url)->header('Cache-Control', 'private, no-store');
            }
        }

        return $response;
    }
}
