<?php
// app/Policies/ProjectPolicy.php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'coordinator']);
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) return true;
        return $user->managesZone($project->zone_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'coordinator']);
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) return true;
        if (! $user->managesZone($project->zone_id)) return false;
        // Coordinators can only edit planning projects
        return in_array($project->status->value, ['planning']);
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) return true;
        return $user->managesZone($project->zone_id) && $project->status->value === 'planning';
    }
}
