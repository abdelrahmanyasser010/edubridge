<?php

namespace App\Actions\People;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class PeopleProfileManager
{
    /** @param array<string, mixed> $data */
    public function createTeacher(array $data): Teacher
    {
        unset($data['section_ids'], $data['subject_ids']);
        $data['central_user_id'] ??= $this->ensureCentralUser($data['email'] ?? null, $data['full_name'], 'teacher');

        return Teacher::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        unset($data['section_ids'], $data['subject_ids']);
        $teacher->fill($data)->save();

        return $teacher->refresh();
    }

    public function archiveTeacher(Teacher $teacher): Teacher
    {
        $teacher->forceFill(['status' => Teacher::STATUS_ARCHIVED])->save();

        return $teacher->refresh();
    }

    /** @param array<string, mixed> $data */
    public function createGuardian(array $data): Guardian
    {
        $data['central_user_id'] ??= $this->ensureCentralUser($data['email'] ?? null, $data['full_name'], 'parent');

        return Guardian::query()->create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function updateGuardian(Guardian $guardian, array $data): Guardian
    {
        $guardian->fill($data)->save();

        return $guardian->refresh();
    }

    public function archiveGuardian(Guardian $guardian): Guardian
    {
        $guardian->forceFill(['status' => Guardian::STATUS_ARCHIVED])->save();

        return $guardian->refresh();
    }

    /** @param array<string, mixed> $data */
    public function createStudent(array $data): Student
    {
        $parentIds = $data['parent_ids'] ?? [];
        unset($data['parent_ids']);

        return DB::connection('tenant')->transaction(function () use ($data, $parentIds): Student {
            $student = Student::query()->create($data)->refresh();
            $this->attachParents($student, $parentIds);

            return $student->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateStudent(Student $student, array $data): Student
    {
        $parentIds = $data['parent_ids'] ?? null;
        unset($data['parent_ids']);

        $student->fill($data)->save();

        if (is_array($parentIds)) {
            $this->attachParents($student, $parentIds);
        }

        return $student->refresh();
    }

    public function archiveStudent(Student $student): Student
    {
        $student->forceFill(['status' => Student::STATUS_ARCHIVED])->save();

        return $student->refresh();
    }

    private function ensureCentralUser(mixed $email, string $name, string $roleKey): ?int
    {
        if (! is_string($email) || $email === '') {
            return null;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => str()->password(32), 'status' => 'active'],
        );

        DB::connection('central')->table('school_user')->updateOrInsert(
            ['school_id' => app(TenantContext::class)->schoolId(), 'user_id' => $user->id],
            ['role_key' => $roleKey, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) $user->id;
    }

    private function attachParents(Student $student, mixed $parentIds): void
    {
        if (! is_array($parentIds)) {
            return;
        }

        foreach (array_values(array_unique($parentIds)) as $index => $parentId) {
            StudentParent::query()->updateOrCreate(
                ['student_id' => $student->id, 'parent_id' => (int) $parentId],
                [
                    'relationship' => $index === 0 ? 'father' : 'guardian',
                    'is_primary' => $index === 0,
                    'can_pickup' => true,
                    'valid_from' => now()->toDateString(),
                    'status' => StudentParent::STATUS_ACTIVE,
                ],
            );
        }
    }
}
