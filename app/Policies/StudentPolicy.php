<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

/**
 * Who may add, rename and delete a student/pupil.
 *
 *   ADD         a School Admin, in any class of their school; a Class Teacher,
 *               in a class they are the Class Teacher of.
 *   EDIT NAMES  the same: a School Admin for any student of their school, a
 *               Class Teacher for students in their own class(es).
 *   DELETE      a School Admin of the student's school, and NOBODY ELSE. No
 *               member of staff, whatever their role or assignments.
 *
 * Written once, here, and asked by both portals: the School Admin's student
 * pages and the Class Teacher's "My Class" pages. The screens hide what a user
 * cannot do; these rules are what actually refuses it.
 *
 * The actor is a User (the School Admin, on the web guard) or a Staff member
 * (on the staff guard), so the staff side asks with Gate::forUser($staff).
 */
class StudentPolicy
{
    /**
     * Add a student to this class.
     */
    public function create(User|Staff $actor, ?string $className = null): bool
    {
        if ($actor instanceof User) {
            return $this->isSchoolAdmin($actor);
        }

        return $className !== null && $this->teachesAsClassTeacher($actor, $className);
    }

    /**
     * Correct a student's first name and surname.
     */
    public function updateName(User|Staff $actor, Student $student): bool
    {
        if ($actor instanceof User) {
            return $this->isSchoolAdmin($actor) && $actor->school_id === $student->school_id;
        }

        return $actor->school_id === $student->school_id
            && $student->class_name !== null
            && $this->teachesAsClassTeacher($actor, $student->class_name);
    }

    /**
     * Delete a student's record, and with it everything saved against them.
     * School Admins only.
     */
    public function delete(User|Staff $actor, Student $student): bool
    {
        return $actor instanceof User
            && $this->isSchoolAdmin($actor)
            && $actor->school_id === $student->school_id;
    }

    private function isSchoolAdmin(User $user): bool
    {
        return $user->role === UserRole::SchoolAdmin && $user->school_id !== null;
    }

    /**
     * An active teacher who is the Class Teacher of this class.
     */
    private function teachesAsClassTeacher(Staff $staff, string $className): bool
    {
        return $staff->role === StaffRole::Teacher
            && $staff->is_active
            && $staff->classTeacherClassNames()->contains($className);
    }
}
