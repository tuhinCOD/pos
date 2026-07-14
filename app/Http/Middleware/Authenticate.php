<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class Authenticate extends Middleware
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle($request, Closure $next, ...$guards): Response
  {
    
    $jwt = $request->cookie(env('COOKIE_NAME'));
    if (is_string($jwt)) {
      $request->headers->set('Accept', 'application/json');
      $request->headers->set("Authorization", "Bearer " . $jwt);

      // for testing purpose to check how many time I set the expiration
      /**
       * $token = JWTAuth::parseToken();
       * $iat = $token->getPayload()->get('iat');
       * $exp = $token->getPayload()->get('exp');
       * echo "iat:" . date("Y-m-d H:m:s A", $iat) . " exp:" . date("Y-m-d H:m:s A", $exp);
       */
    } else {
      // $request->headers->set('Accept', 'application/json');
      $request->headers->set("Authorization", "Bearer ");
    }

    $this->authenticate($request, $guards);
    return $next($request);
  }
}