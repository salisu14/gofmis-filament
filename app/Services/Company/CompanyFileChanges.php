<?php

namespace App\Services\Company;

final readonly class CompanyFileChanges
{
    /**
     * @param array<string, mixed> $updateData
     * @param list<string>         $filesToDelete             Files to remove after a successful update.
     * @param list<string>         $filesToCleanupOnFailure   Files to remove if the update throws.
     */
    public function __construct(
        public array $updateData,
        public array $filesToDelete,
        public array $filesToCleanupOnFailure,
    ) {}
}
