<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\TaskAttachment;
use App\Models\User;

final class TaskAttachmentPolicy
{
    public function delete(User $user, TaskAttachment $attachment): bool
    {
        if (! $user->can(PermissionName::TaskView->value)) {
            return false;
        }

        if ($attachment->task->isProjectLocked()) {
            return false;
        }

        return $attachment->uploader_id === $user->id
            || $user->can(PermissionName::TaskUpdate->value);
    }
}
