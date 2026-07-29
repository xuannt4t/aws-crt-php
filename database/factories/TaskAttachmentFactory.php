<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAttachment>
 */
final class TaskAttachmentFactory extends Factory
{
    protected $model = TaskAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->slug(3).'.pdf';

        return [
            'task_id' => Task::factory(),
            'uploader_id' => User::factory(),
            'disk' => 'local',
            'path' => 'task-attachments/1/'.$this->faker->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(1024, 5 * 1024 * 1024),
        ];
    }
}
