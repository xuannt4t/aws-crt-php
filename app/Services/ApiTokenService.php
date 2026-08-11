<?php

namespace App\Services;

use App\Models\ApiRefreshSession;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class ApiTokenService
{
    /**
     * @return array{token_type: string, access_token: string, access_token_expires_in: int, refresh_token: string, refresh_token_expires_in: int}
     */
    public function issue(User $user, string $deviceName): array
    {
        return DB::transaction(fn (): array => $this->issueWithinTransaction(
            $user,
            $deviceName,
            (string) Str::uuid(),
        ));
    }

    /**
     * @return array{token_type: string, access_token: string, access_token_expires_in: int, refresh_token: string, refresh_token_expires_in: int}
     */
    public function rotate(string $plainRefreshToken): array
    {
        $result = DB::transaction(function () use ($plainRefreshToken): ?array {
            $session = ApiRefreshSession::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $plainRefreshToken))
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return null;
            }

            if ($session->revoked_at !== null) {
                $this->revokeFamily($session->family_id);

                return null;
            }

            if ($session->expires_at->isPast() || ! $session->user || ! $session->user->is_active) {
                $this->revokeFamily($session->family_id);

                return null;
            }

            $session->update([
                'last_used_at' => now(),
                'revoked_at' => now(),
            ]);
            $session->accessToken?->delete();

            return $this->issueWithinTransaction(
                $session->user,
                $session->name,
                $session->family_id,
            );
        });

        if ($result === null) {
            throw new AuthenticationException;
        }

        return $result;
    }

    public function revokeCurrent(Request $request): void
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        DB::transaction(function () use ($token): void {
            ApiRefreshSession::query()
                ->where('personal_access_token_id', $token->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $token->delete();
        });

        Auth::forgetGuards();
    }

    public function revokeAll(User $user): void
    {
        DB::transaction(function () use ($user): void {
            ApiRefreshSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $user->tokens()->delete();
        });

        Auth::forgetGuards();
    }

    public function revokeOthers(Request $request): void
    {
        $currentToken = $request->user()?->currentAccessToken();

        if (! $currentToken instanceof PersonalAccessToken) {
            return;
        }

        DB::transaction(function () use ($request, $currentToken): void {
            $otherIds = $request->user()->tokens()->whereKeyNot($currentToken->id)->pluck('id');

            ApiRefreshSession::query()
                ->whereIn('personal_access_token_id', $otherIds)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            PersonalAccessToken::query()->whereIn('id', $otherIds)->delete();
        });
    }

    /**
     * @return array{token_type: string, access_token: string, access_token_expires_in: int, refresh_token: string, refresh_token_expires_in: int}
     */
    private function issueWithinTransaction(User $user, string $deviceName, string $familyId): array
    {
        $accessMinutes = (int) config('api.tokens.access_ttl_minutes');
        $refreshDays = (int) config('api.tokens.refresh_ttl_days');
        $accessToken = $user->createToken(
            $deviceName,
            ['*'],
            now()->addMinutes($accessMinutes),
        );
        $plainRefreshToken = bin2hex(random_bytes(64));

        ApiRefreshSession::query()->create([
            'user_id' => $user->id,
            'personal_access_token_id' => $accessToken->accessToken->id,
            'family_id' => $familyId,
            'name' => $deviceName,
            'token_hash' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays($refreshDays),
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $accessToken->plainTextToken,
            'access_token_expires_in' => $accessMinutes * 60,
            'refresh_token' => $plainRefreshToken,
            'refresh_token_expires_in' => $refreshDays * 86400,
        ];
    }

    private function revokeFamily(string $familyId): void
    {
        $sessions = ApiRefreshSession::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->get();

        $accessTokenIds = $sessions->pluck('personal_access_token_id')->filter()->all();

        ApiRefreshSession::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        PersonalAccessToken::query()->whereIn('id', $accessTokenIds)->delete();
    }
}
