<?php

namespace App\Enums;

enum EmployeeFormTypeEnum: string
{
    case BasicInformation = 'basic_info';
    case PersonalInformation = 'personal_info';
    case EmergencyContact = 'emergency_contact';
    case About = 'about_employee';
    case BankInformation = 'bank_information';
    case FamilyInformation = 'family_information';
    case StatutoryInformation = 'statutory_information';
    case SupportingDocuments = 'supporting_documents';
    case Education = 'education';
    case Experience = 'experience';
}
