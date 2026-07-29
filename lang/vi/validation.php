<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Thông báo validation tiếng Việt
    |--------------------------------------------------------------------------
    |
    | Đây là bản dịch mặc định cho toàn hệ thống. FormRequest chỉ nên khai báo
    | messages() riêng khi cần câu chữ đặc thù cho nghiệp vụ; các rule thông
    | thường lấy câu chữ ở đây để không lọt tiếng Anh ra giao diện.
    |
    */

    'accepted' => 'Trường :attribute phải được chấp nhận.',
    'accepted_if' => 'Trường :attribute phải được chấp nhận khi :other là :value.',
    'active_url' => 'Trường :attribute không phải là một URL hợp lệ.',
    'after' => 'Trường :attribute phải là ngày sau :date.',
    'after_or_equal' => 'Trường :attribute phải là ngày sau hoặc bằng :date.',
    'alpha' => 'Trường :attribute chỉ được chứa chữ cái.',
    'alpha_dash' => 'Trường :attribute chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới.',
    'alpha_num' => 'Trường :attribute chỉ được chứa chữ cái và số.',
    'array' => 'Trường :attribute phải là một danh sách.',
    'ascii' => 'Trường :attribute chỉ được chứa ký tự và ký hiệu ASCII.',
    'before' => 'Trường :attribute phải là ngày trước :date.',
    'before_or_equal' => 'Trường :attribute phải là ngày trước hoặc bằng :date.',
    'between' => [
        'array' => 'Trường :attribute phải có từ :min đến :max phần tử.',
        'file' => 'Trường :attribute phải có dung lượng từ :min đến :max KB.',
        'numeric' => 'Trường :attribute phải có giá trị từ :min đến :max.',
        'string' => 'Trường :attribute phải có độ dài từ :min đến :max ký tự.',
    ],
    'boolean' => 'Trường :attribute phải là đúng hoặc sai.',
    'can' => 'Trường :attribute chứa giá trị không được phép.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'contains' => 'Trường :attribute thiếu giá trị bắt buộc.',
    'current_password' => 'Mật khẩu không chính xác.',
    'date' => 'Trường :attribute không phải là ngày hợp lệ.',
    'date_equals' => 'Trường :attribute phải là ngày bằng :date.',
    'date_format' => 'Trường :attribute không đúng định dạng :format.',
    'decimal' => 'Trường :attribute phải có :decimal chữ số thập phân.',
    'declined' => 'Trường :attribute phải bị từ chối.',
    'declined_if' => 'Trường :attribute phải bị từ chối khi :other là :value.',
    'different' => 'Trường :attribute và :other phải khác nhau.',
    'digits' => 'Trường :attribute phải có :digits chữ số.',
    'digits_between' => 'Trường :attribute phải có từ :min đến :max chữ số.',
    'dimensions' => 'Trường :attribute có kích thước ảnh không hợp lệ.',
    'distinct' => 'Trường :attribute có giá trị bị trùng lặp.',
    'doesnt_end_with' => 'Trường :attribute không được kết thúc bằng một trong các giá trị: :values.',
    'doesnt_start_with' => 'Trường :attribute không được bắt đầu bằng một trong các giá trị: :values.',
    'email' => 'Trường :attribute phải là địa chỉ email hợp lệ.',
    'ends_with' => 'Trường :attribute phải kết thúc bằng một trong các giá trị: :values.',
    'enum' => 'Giá trị đã chọn của :attribute không hợp lệ.',
    'exists' => 'Giá trị đã chọn của :attribute không tồn tại.',
    'extensions' => 'Trường :attribute phải có phần mở rộng thuộc: :values.',
    'file' => 'Trường :attribute phải là một tệp.',
    'filled' => 'Trường :attribute không được để trống.',
    'gt' => [
        'array' => 'Trường :attribute phải có nhiều hơn :value phần tử.',
        'file' => 'Trường :attribute phải lớn hơn :value KB.',
        'numeric' => 'Trường :attribute phải lớn hơn :value.',
        'string' => 'Trường :attribute phải dài hơn :value ký tự.',
    ],
    'gte' => [
        'array' => 'Trường :attribute phải có ít nhất :value phần tử.',
        'file' => 'Trường :attribute phải lớn hơn hoặc bằng :value KB.',
        'numeric' => 'Trường :attribute phải lớn hơn hoặc bằng :value.',
        'string' => 'Trường :attribute phải dài ít nhất :value ký tự.',
    ],
    'hex_color' => 'Trường :attribute phải là mã màu hex hợp lệ.',
    'image' => 'Trường :attribute phải là một hình ảnh.',
    'in' => 'Giá trị đã chọn của :attribute không hợp lệ.',
    'in_array' => 'Trường :attribute không có trong :other.',
    'integer' => 'Trường :attribute phải là số nguyên.',
    'ip' => 'Trường :attribute phải là địa chỉ IP hợp lệ.',
    'ipv4' => 'Trường :attribute phải là địa chỉ IPv4 hợp lệ.',
    'ipv6' => 'Trường :attribute phải là địa chỉ IPv6 hợp lệ.',
    'json' => 'Trường :attribute phải là chuỗi JSON hợp lệ.',
    'list' => 'Trường :attribute phải là một danh sách.',
    'lowercase' => 'Trường :attribute phải viết thường.',
    'lt' => [
        'array' => 'Trường :attribute phải có ít hơn :value phần tử.',
        'file' => 'Trường :attribute phải nhỏ hơn :value KB.',
        'numeric' => 'Trường :attribute phải nhỏ hơn :value.',
        'string' => 'Trường :attribute phải ngắn hơn :value ký tự.',
    ],
    'lte' => [
        'array' => 'Trường :attribute không được có quá :value phần tử.',
        'file' => 'Trường :attribute phải nhỏ hơn hoặc bằng :value KB.',
        'numeric' => 'Trường :attribute phải nhỏ hơn hoặc bằng :value.',
        'string' => 'Trường :attribute không được dài quá :value ký tự.',
    ],
    'mac_address' => 'Trường :attribute phải là địa chỉ MAC hợp lệ.',
    'max' => [
        'array' => 'Trường :attribute không được có quá :max phần tử.',
        'file' => 'Trường :attribute không được vượt quá :max KB.',
        'numeric' => 'Trường :attribute không được lớn hơn :max.',
        'string' => 'Trường :attribute không được dài quá :max ký tự.',
    ],
    'max_digits' => 'Trường :attribute không được có quá :max chữ số.',
    'mimes' => 'Trường :attribute phải là tệp thuộc định dạng: :values.',
    'mimetypes' => 'Trường :attribute phải là tệp thuộc định dạng: :values.',
    'min' => [
        'array' => 'Trường :attribute phải có ít nhất :min phần tử.',
        'file' => 'Trường :attribute phải có dung lượng ít nhất :min KB.',
        'numeric' => 'Trường :attribute phải lớn hơn hoặc bằng :min.',
        'string' => 'Trường :attribute phải có ít nhất :min ký tự.',
    ],
    'min_digits' => 'Trường :attribute phải có ít nhất :min chữ số.',
    'missing' => 'Trường :attribute phải vắng mặt.',
    'missing_if' => 'Trường :attribute phải vắng mặt khi :other là :value.',
    'missing_unless' => 'Trường :attribute phải vắng mặt trừ khi :other là :value.',
    'missing_with' => 'Trường :attribute phải vắng mặt khi có :values.',
    'missing_with_all' => 'Trường :attribute phải vắng mặt khi có đủ :values.',
    'multiple_of' => 'Trường :attribute phải là bội số của :value.',
    'not_in' => 'Giá trị đã chọn của :attribute không hợp lệ.',
    'not_regex' => 'Trường :attribute có định dạng không hợp lệ.',
    'numeric' => 'Trường :attribute phải là một số.',
    'password' => [
        'letters' => 'Mật khẩu phải chứa ít nhất một chữ cái.',
        'mixed' => 'Mật khẩu phải chứa ít nhất một chữ hoa và một chữ thường.',
        'numbers' => 'Mật khẩu phải chứa ít nhất một chữ số.',
        'symbols' => 'Mật khẩu phải chứa ít nhất một ký tự đặc biệt.',
        'uncompromised' => 'Mật khẩu này đã xuất hiện trong dữ liệu bị rò rỉ. Vui lòng chọn mật khẩu khác.',
    ],
    'present' => 'Trường :attribute phải có mặt.',
    'present_if' => 'Trường :attribute phải có mặt khi :other là :value.',
    'present_unless' => 'Trường :attribute phải có mặt trừ khi :other là :value.',
    'present_with' => 'Trường :attribute phải có mặt khi có :values.',
    'present_with_all' => 'Trường :attribute phải có mặt khi có đủ :values.',
    'prohibited' => 'Trường :attribute không được phép gửi lên.',
    'prohibited_if' => 'Trường :attribute không được phép khi :other là :value.',
    'prohibited_unless' => 'Trường :attribute không được phép trừ khi :other thuộc :values.',
    'prohibits' => 'Trường :attribute khiến :other không được phép có mặt.',
    'regex' => 'Trường :attribute có định dạng không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.',
    'required_array_keys' => 'Trường :attribute phải chứa các khoá: :values.',
    'required_if' => 'Vui lòng nhập :attribute khi :other là :value.',
    'required_if_accepted' => 'Vui lòng nhập :attribute khi :other được chấp nhận.',
    'required_if_declined' => 'Vui lòng nhập :attribute khi :other bị từ chối.',
    'required_unless' => 'Vui lòng nhập :attribute trừ khi :other thuộc :values.',
    'required_with' => 'Vui lòng nhập :attribute khi có :values.',
    'required_with_all' => 'Vui lòng nhập :attribute khi có đủ :values.',
    'required_without' => 'Vui lòng nhập :attribute khi không có :values.',
    'required_without_all' => 'Vui lòng nhập :attribute khi không có bất kỳ giá trị nào trong :values.',
    'same' => 'Trường :attribute và :other phải giống nhau.',
    'size' => [
        'array' => 'Trường :attribute phải có đúng :size phần tử.',
        'file' => 'Trường :attribute phải có dung lượng :size KB.',
        'numeric' => 'Trường :attribute phải bằng :size.',
        'string' => 'Trường :attribute phải có đúng :size ký tự.',
    ],
    'starts_with' => 'Trường :attribute phải bắt đầu bằng một trong các giá trị: :values.',
    'string' => 'Trường :attribute phải là chuỗi ký tự.',
    'timezone' => 'Trường :attribute phải là múi giờ hợp lệ.',
    'unique' => 'Giá trị :attribute đã tồn tại trong hệ thống.',
    'uploaded' => 'Không tải lên được :attribute. Tệp có thể vượt quá giới hạn dung lượng của máy chủ.',
    'uppercase' => 'Trường :attribute phải viết hoa.',
    'url' => 'Trường :attribute phải là URL hợp lệ.',
    'ulid' => 'Trường :attribute phải là ULID hợp lệ.',
    'uuid' => 'Trường :attribute phải là UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Thông báo tuỳ biến theo trường
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attachment' => [
            'file' => 'Tệp đính kèm không hợp lệ.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tên trường hiển thị trong thông báo
    |--------------------------------------------------------------------------
    |
    | Không có phần này thì thông báo sẽ hiện tên cột thô như
    | "organization unit id" thay vì "đơn vị sở hữu".
    |
    */

    'attributes' => [
        'action' => 'hành động',
        'assignee_id' => 'người phụ trách chính',
        'assignee_ids' => 'danh sách người phụ trách',
        'avatar' => 'ảnh đại diện',
        'body' => 'nội dung',
        'code' => 'mã',
        'completed_at' => 'thời điểm hoàn thành',
        'creator_id' => 'người tạo',
        'current_password' => 'mật khẩu hiện tại',
        'date_from' => 'từ ngày',
        'date_to' => 'đến ngày',
        'description' => 'mô tả',
        'due_at' => 'thời hạn',
        'email' => 'email',
        'employee_code' => 'mã nhân viên',
        'files' => 'tệp đính kèm',
        'is_active' => 'trạng thái hoạt động',
        'is_system_admin' => 'quyền quản trị hệ thống',
        'job_title' => 'chức danh',
        'name' => 'họ tên',
        'organization_unit_id' => 'đơn vị sở hữu',
        'overdue' => 'quá hạn',
        'parent_id' => 'công việc cha',
        'password' => 'mật khẩu',
        'password_confirmation' => 'xác nhận mật khẩu',
        'phone' => 'số điện thoại',
        'priority' => 'độ ưu tiên',
        'progress' => 'tiến độ',
        'reason' => 'lý do',
        'roles' => 'vai trò',
        'search' => 'từ khoá tìm kiếm',
        'status' => 'trạng thái',
        'title' => 'tiêu đề',
    ],

];
