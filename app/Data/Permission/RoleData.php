<?php
namespace App\Data\Permission;

class RoleData
{
    public function __construct(
        public string $name,
        public ?string $guardName = 'web'
    ) {}
}
