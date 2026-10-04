<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\WeChatMiniProgramClient;
use App\Exceptions\WeChatAuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\WeChatLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Validation\ValidationException;

#[Group('Authentication', weight: 1)]
class AuthController extends Controller
{
    #[Middleware('throttle:wechat-login')]
    public function login(WeChatLoginRequest $request, WeChatMiniProgramClient $wechat): JsonResponse
    {
        try {
            $session = $wechat->codeToSession($request->string('code')->toString());
        } catch (WeChatAuthException $exception) {
            throw ValidationException::withMessages([
                'code' => [$exception->getMessage()],
            ]);
        }

        $name = $request->filled('name')
            ? $request->string('name')->trim()->toString()
            : 'WeChat User';

        try {
            $user = User::query()->firstOrCreate(
                ['wechat_openid' => $session->openid],
                [
                    'name' => $name,
                    'wechat_unionid' => $session->unionid,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $user = User::query()->where('wechat_openid', $session->openid)->firstOrFail();
        }

        if ($session->unionid !== null && $user->wechat_unionid !== $session->unionid) {
            $user->forceFill(['wechat_unionid' => $session->unionid])->save();
        }

        return response()->json([
            'token' => $user->createToken('wechat-mini-program')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    #[Middleware('auth:sanctum')]
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
