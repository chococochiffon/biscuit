<?php

namespace App\Enums;

enum AdministratorRole: string
{
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
