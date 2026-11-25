<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Analyst = 'analyst';
    case Customer = 'customer';
}
