<?php

namespace App\Constant;

class RoleConstants
{
    public const ALL             = ['client', 'veterinaire', 'responsable', 'assistant', 'benevole'];
    public const CAN_EDIT_CLINIC = ['responsable', 'veterinaire'];
    public const CAN_DELETE_USER = ['responsable', 'veterinaire', 'assistant'];
}
