<?php

namespace App\Enums;

/**
 * Mirrors the Prisma `UserRole` enum.
 */
enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case USER = 'USER';
    case AGENT = 'AGENT';
    case API = 'API';
    case SMART = 'SMART';
}
