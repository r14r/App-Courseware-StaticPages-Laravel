<?php

namespace App\Enums;

enum UserType: string
{
    case Admin = 'Admin';
    case Trainer = 'Trainer';
    case Student = 'Student';
}
