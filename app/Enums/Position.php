<?php

namespace App\Enums;

enum Position: string
{
    case NailTechnician = 'Nail Technician';
    case MassageTechnician = 'Massage Technician';
    case FacialTechnician = 'Facial Technician';

    public function label(): string
    {
        return $this->value;
    }
}
