<?php

namespace App\Middleware;

use App\Models\Guest;
use App\Models\User;
use App\Response\JsonResponse;
use Closure;
use Core\Auth\Auth;
use Core\Http\Request;
use Core\Middleware\MiddlewareInterface;
use Core\Valid\Validator;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->bearerToken()) {
            try {
                if (!env('JWT_KEY')) {
                    throw new Exception('JWT Key not found!.');
                }

                $token = JWT::decode(
                    $request->bearerToken(),
                    new Key(env('JWT_KEY'), env('JWT_ALGO', 'HS256'))
                );

                $user = User::find(intval($token->sub));
                if (!$user->exist()) {
                    throw new Exception('user not found');
                }

                if (!$user->isActive()) {
                    throw new Exception('user not active');
                }

                $user->setAsAdmin();

                Auth::login($user);
            } catch (Exception $e) {
                return (new JsonResponse)->errorBadRequest([$e->getMessage()]);
            }

            return $next($request);
        }

        $key = $request->server->get('HTTP_X_ACCESS_KEY');

        $valid = Validator::make(
            [
                'key' => $key
            ],
            [
                'key' => ['required', 'str', 'trim', 'alpha_num', 'min:49', 'max:50']
            ]
        );

        if (!$valid->fails()) {
            $user = User::where('access_key', $valid->key)->limit(1)->first();
            if ($user->exist()) {
                if (!$user->isActive()) {
                    return (new JsonResponse)->errorBadRequest(['user not active.']);
                }

                $user->setAsNonAdmin();

                Auth::login($user);
                return $next($request);
            }
        }

        $gvalid = Validator::make(
            [
                'key' => $key
            ],
            [
                'key' => ['required', 'str', 'trim', 'alpha_num', 'min:6', 'max:64']
            ]
        );

        if ($gvalid->fails()) {
            return (new JsonResponse)->errorBadRequest($gvalid->messages());
        }

        $guest = Guest::where('token', $gvalid->key)->limit(1)->first();
        if (!$guest->exist()) {
            return (new JsonResponse)->errorBadRequest(['user not found.']);
        }

        $user = User::find(intval($guest->user_id));
        if (!$user->exist()) {
            return (new JsonResponse)->errorBadRequest(['user not found.']);
        }

        if (!$user->isActive()) {
            return (new JsonResponse)->errorBadRequest(['user not active.']);
        }

        $user->setAsNonAdmin();
        $user->setGuest($guest);

        Auth::login($user);
        return $next($request);
    }
}
