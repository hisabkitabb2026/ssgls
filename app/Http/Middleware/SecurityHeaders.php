<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**

Adds security headers to all responses
*/
class SecurityHeaders +{
public function handle(Request $request, Closure $next): Response
{
  $response = $next($request);

  // Prevent clickjacking
  $response->header('X-Frame-Options', 'SAMEORIGIN');

  // Prevent MIME type sniffing
  $response->header('X-Content-Type-Options', 'nosniff');

  // Enable XSS protection
  $response->header('X-XSS-Protection', '1; mode=block');

  // Referrer policy
  $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

  // Content Security Policy
  $csp = "default-src 'self'; ";
  $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ";
  $csp .= "style-src 'self' 'unsafe-inline'; ";
  $csp .= "img-src 'self' data: https:; ";
  $csp .= "font-src 'self' data:; ";
  $csp .= "connect-src 'self'; ";
  $response->header('Content-Security-Policy', $csp);

  // Strict Transport Security
  if (config('app.env') === 'production') {
      $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
  }

  return $response;
}
}