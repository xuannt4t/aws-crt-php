<?php

namespace App\Support;

use App\Models\User;

/**
 * Nguồn dữ liệu duy nhất cho các ô chọn người: người phụ trách công việc, chủ dự
 * án, thành viên việc dự án, bộ lọc theo người.
 *
 * Trước đây mỗi controller tự viết một hàm `activeUsers()` giống hệt nhau và chỉ
 * trả về `id` + `name`. Ô chọn vì thế chỉ là một danh sách tên phẳng — trùng tên
 * là không phân biệt nổi, và không biết người đó thuộc phòng nào. Ở đây trả kèm
 * đơn vị, chức danh và vai trò để giao diện gom nhóm theo đơn vị và tìm kiếm.
 */
final class UserOptions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function active(): array
    {
        return User::query()
            ->where('is_active', true)
            ->with(['organizationUnit:id,name', 'roles:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'job_title', 'organization_unit_id'])
            ->map(static fn (User $user): array => self::present($user))
            ->all();
    }

    /**
     * Dùng khi người dùng không có quyền giao việc: họ vẫn phải tự nhận được
     * việc của mình, nên danh sách rút lại còn đúng một người.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function only(User $user): array
    {
        $user->loadMissing(['organizationUnit:id,name', 'roles:id,name']);

        return [self::present($user)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => $user->job_title,
            // Vai trò đầu tiên là vai trò chính, khớp với cách sidebar và màn
            // Người dùng đang hiển thị.
            'role' => $user->roles->first()?->name,
            'organization_unit_id' => $user->organization_unit_id,
            'organization_unit_name' => $user->organizationUnit?->name,
        ];
    }
}
