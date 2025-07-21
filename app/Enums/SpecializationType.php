<?php
// app/Enums/SpecializationType.php
namespace App\Enums;

enum SpecializationType: int
{
// تخصصات طبية غير جراحية
    case InternalMedicine             = 1;
    case Cardiology                   = 2;
    case RespiratoryMedicine         = 3;
    case Gastroenterology            = 4;
    case Nephrology                  = 5;
    case Hematology                  = 6;
    case Endocrinology               = 7;
    case InfectiousDiseases          = 8;
    case Neurology                   = 9;
    case Psychiatry                  = 10;
    case Dermatology                 = 11;
    case FamilyMedicine              = 12;
    case EmergencyMedicine          = 13;
    case Pediatrics                  = 14;
    case Oncology                    = 15;
    case Rheumatology                = 16;

// تخصصات جراحية
    case GeneralSurgery              = 17;
    case OrthopedicSurgery           = 18;
    case Neurosurgery                = 19;
    case CardiothoracicSurgery       = 20;
    case VascularSurgery            = 21;
    case PediatricSurgery           = 22;
    case PlasticBurnSurgery         = 23;
    case UrologySurgery             = 24;
    case ENT                         = 25;
    case OphthalmicSurgery          = 26;
    case OralMaxillofacialSurgery   = 27;
    case OncologicSurgery           = 28;

// نسائية وولادة
    case ObstetricsAndGynecology     = 29;

// تخصصات تشخيصية ومساعدة
    case DiagnosticRadiology        = 30;
    case NuclearMedicine           = 31;
    case Pathology                  = 32;
    case LaboratoryMedicine         = 33;
    case Anesthesiology             = 34;
    case IntensiveCare             = 35;
    case ForensicMedicine          = 36;
    case PreventivePublicHealth    = 37;

    public function label(): string
    {
        return match($this) {
            self::InternalMedicine           => 'الأمراض الباطنة',
            self::Cardiology                 => 'أمراض القلب والأوعية',
            self::RespiratoryMedicine       => 'أمراض الجهاز التنفسي',
            self::Gastroenterology          => 'طب الجهاز الهضمي',
            self::Nephrology                => 'أمراض الكلية',
            self::Hematology                => 'أمراض الدم',
            self::Endocrinology             => 'أمراض الغدد الصم',
            self::InfectiousDiseases        => 'الأمراض المعدية',
            self::Neurology                 => 'الأمراض العصبية',
            self::Psychiatry                => 'الطب النفسي',
            self::Dermatology               => 'الأمراض الجلدية',
            self::FamilyMedicine            => 'طب الأسرة',
            self::EmergencyMedicine        => 'طب الطوارئ',
            self::Pediatrics                => 'طب الأطفال',
            self::Oncology                  => 'طب الأورام',
            self::Rheumatology              => 'روماتيزم',
            self::GeneralSurgery            => 'جراحة عامة',
            self::OrthopedicSurgery         => 'جراحة العظام',
            self::Neurosurgery              => 'جراحة المخ والأعصاب',
            self::CardiothoracicSurgery     => 'جراحة القلب والصدر',
            self::VascularSurgery          => 'جراحة الأوعية الدموية',
            self::PediatricSurgery         => 'جراحة الأطفال',
            self::PlasticBurnSurgery       => 'جراحة التجميل والحروق',
            self::UrologySurgery           => 'جراحة المسالك البولية',
            self::ENT                       => 'جراحة الأنف والأذن والحنجرة',
            self::OphthalmicSurgery        => 'جراحة العيون',
            self::OralMaxillofacialSurgery => 'جراحة الفم والوجه والفكين',
            self::OncologicSurgery         => 'جراحة الأورام',
            self::ObstetricsAndGynecology   => 'طب النساء والتوليد',
            self::DiagnosticRadiology      => 'الأشعة التشخيصية',
            self::NuclearMedicine          => 'الطب النووي',
            self::Pathology                => 'علم الأمراض',
            self::LaboratoryMedicine       => 'التحاليل الطبية',
            self::Anesthesiology           => 'التخدير',
            self::IntensiveCare            => 'العناية المركزة',
            self::ForensicMedicine         => 'الطب الشرعي',
            self::PreventivePublicHealth   => 'الطب الوقائي والصحة العامة',
        };
    }
    public static function tryFromArabic(string $arabicName): ?self
    {
        foreach (self::cases() as $case) {
            if (trim($case->label()) === trim($arabicName)) {
                return $case;
            }
        }
        return null;
    }
    public function value(): int
    {
        return $this->value;
    }
}
