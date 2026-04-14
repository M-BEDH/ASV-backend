<?php

namespace App\Constant;

class RoleConstants
{
    public const ALL             = ['client', 'veterinaire', 'responsable', 'assistant', 'benevole'];
    public const SUPER_ADMIN     = 'super_admin';
    public const CAN_EDIT_CLINIC = ['responsable', 'veterinaire'];
    public const CAN_DELETE_USER           = ['responsable', 'veterinaire'];
    public const ASSIGNABLE_BY_RESPONSABLE = ['veterinaire', 'assistant', 'benevole'];
    public const CONSULTATION_VETERINAIRE_ROLES = ['veterinaire', 'responsable'];
}
