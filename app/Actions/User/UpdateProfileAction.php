<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdateProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): void
    {
        $avatar = $data['avatar'] ?? null;
        unset($data['avatar']);

        $newAvatarPath = $avatar instanceof UploadedFile
            ? $avatar->store('avatars', 'public')
            : null;

        if ($avatar instanceof UploadedFile && $newAvatarPath === false) {
            throw ValidationException::withMessages([
                'avatar' => 'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
            ]);
        }

        $oldAvatarPath = $user->avatar_path;

        try {
            $user->fill($data);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if (is_string($newAvatarPath)) {
                $user->avatar_path = $newAvatarPath;
            }

            $user->save();
        } catch (Throwable $exception) {
            if (is_string($newAvatarPath)) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if (is_string($newAvatarPath) && $oldAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }
    }
}
