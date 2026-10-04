<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'wechat_openid' => $this->wechat_openid,
            'wechat_unionid' => $this->wechat_unionid,
            'is_admin' => $this->is_admin,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
