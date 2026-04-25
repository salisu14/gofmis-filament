<?php
namespace App\Data\Permission;

class PermissionData
{
    public function __construct(
        public string $name,
        public ?string $guardName = 'web'
    ) {}
}
