<?php

namespace App\Constant;

class RoleConstants
{
    public const CLIENT      = 'client';
    public const BENEVOLE    = 'benevole';
    public const VETERINAIRE = 'veterinaire';
    public const RESPONSABLE = 'responsable';
    public const ASSISTANT   = 'assistant';
    public const SUPER_ADMIN = 'super_admin';

    public const ALL              = [self::CLIENT, self::VETERINAIRE, self::RESPONSABLE, self::ASSISTANT, self::BENEVOLE];
    public const CAN_EDIT_CLINIC  = [self::RESPONSABLE];
    public const CAN_DELETE_USER           = [self::RESPONSABLE, self::VETERINAIRE];
    public const ASSIGNABLE_BY_RESPONSABLE = [self::VETERINAIRE, self::ASSISTANT, self::BENEVOLE];
    public const CONSULTATION_VETERINAIRE_ROLES = [self::VETERINAIRE, self::RESPONSABLE];
}
