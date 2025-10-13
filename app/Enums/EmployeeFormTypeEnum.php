<?php

namespace App\Enums;

enum EmployeeFormTypeEnum: string
{
    case BasicInformation = 'basic_info';
    case PersonalInformation = 'personal_info';
    case EmergencyContact = 'emergency_contact';
    case About = 'about_employee';
}
