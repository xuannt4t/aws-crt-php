<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CloseProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('close', $project);
    }

    public function rules(): array
    {
        return [
            'close_reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('close_reason')) {
                return;
            }

            /** @var Project $project */
            $project = $this->route('project');

            if (blank($this->input('close_reason')) && $project->openTasks()->exists()) {
                $validator->errors()->add(
                    'close_reason',
                    'Dự án còn công việc chưa hoàn thành, vui lòng nhập lý do đóng ngoại lệ.',
                );
            }
        });
    }
}
