<?php

namespace Database\Seeders;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            LegalDocumentType::PRIVACY_POLICY->value => [
                'version' => '1.0',
                'content' => [
                    'en' => "Privacy Policy\n\n"
                        ."Last updated: this document is reviewed periodically and updated as our services evolve.\n\n"
                        ."1. Information we collect\n"
                        ."We collect the information you provide when you create an account (name, phone number, email, and — for providers — professional licenses), plus information generated as you use Sahtee, such as reservations, ratings, and messages.\n\n"
                        ."2. How we use your information\n"
                        ."We use your data to connect you with doctors, nurses, and hospitals, to process reservations, to send you notifications about your account and appointments, and to improve the quality and safety of our services.\n\n"
                        ."3. Sharing of information\n"
                        ."We share only the information necessary to complete a reservation with the relevant provider (doctor, nurse, or hospital). We do not sell your personal data to third parties.\n\n"
                        ."4. Data security\n"
                        ."We apply reasonable technical and organizational safeguards to protect your information, including access controls and encrypted authentication.\n\n"
                        ."5. Your rights\n"
                        ."You may request access to, correction of, or deletion of your account and associated data at any time by contacting our support team.\n\n"
                        ."6. Contact us\n"
                        ."If you have questions about this policy, please reach out through the contact channels listed on our website.",
                    'ar' => "سياسة الخصوصية\n\n"
                        ."آخر تحديث: تتم مراجعة هذه الوثيقة دوريًا وتحديثها مع تطور خدماتنا.\n\n"
                        ."1. المعلومات التي نجمعها\n"
                        ."نجمع المعلومات التي تقدّمها عند إنشاء حسابك (الاسم، رقم الهاتف، البريد الإلكتروني، والتراخيص المهنية بالنسبة لمقدمي الخدمة)، بالإضافة إلى المعلومات الناتجة عن استخدامك لصحتي مثل الحجوزات والتقييمات والرسائل.\n\n"
                        ."2. كيفية استخدام معلوماتك\n"
                        ."نستخدم بياناتك لربطك بالأطباء والممرضين والمستشفيات، ومعالجة الحجوزات، وإرسال إشعارات متعلقة بحسابك ومواعيدك، وتحسين جودة وأمان خدماتنا.\n\n"
                        ."3. مشاركة المعلومات\n"
                        ."نشارك فقط المعلومات اللازمة لإتمام الحجز مع مقدم الخدمة المعني (طبيب، ممرض، أو مستشفى). نحن لا نبيع بياناتك الشخصية لأي طرف ثالث.\n\n"
                        ."4. أمان البيانات\n"
                        ."نطبّق إجراءات تقنية وتنظيمية معقولة لحماية معلوماتك، بما في ذلك ضوابط الوصول والتحقق المشفّر.\n\n"
                        ."5. حقوقك\n"
                        ."يمكنك طلب الوصول إلى بياناتك أو تصحيحها أو حذف حسابك والبيانات المرتبطة به في أي وقت من خلال التواصل مع فريق الدعم.\n\n"
                        ."6. تواصل معنا\n"
                        ."إذا كانت لديك أي أسئلة حول هذه السياسة، يرجى التواصل عبر قنوات الاتصال المذكورة في موقعنا.",
                ],
            ],
            LegalDocumentType::TERMS_AND_CONDITIONS->value => [
                'version' => '1.0',
                'content' => [
                    'en' => "Terms and Conditions\n\n"
                        ."By creating an account or using Sahtee, you agree to the following terms.\n\n"
                        ."1. Eligibility\n"
                        ."Healthcare providers (doctors, nurses, hospitals) must hold a valid professional license and are subject to review and approval before their account is activated.\n\n"
                        ."2. Reservations\n"
                        ."Reservations made through Sahtee are agreements between the patient and the provider. Sahtee facilitates the connection but is not itself a medical provider.\n\n"
                        ."3. User conduct\n"
                        ."Users agree to provide accurate information and to use the platform only for legitimate healthcare-related purposes.\n\n"
                        ."4. Cancellations\n"
                        ."Cancellation and rescheduling policies may vary by provider and are shown before a reservation is confirmed.\n\n"
                        ."5. Suspension\n"
                        ."Sahtee reserves the right to suspend accounts that violate these terms, provide false credentials, or misuse the platform.\n\n"
                        ."6. Changes to these terms\n"
                        ."We may update these terms from time to time. Continued use of Sahtee after an update constitutes acceptance of the revised terms.",
                    'ar' => "الشروط والأحكام\n\n"
                        ."بإنشائك حسابًا أو استخدامك لصحتي، فإنك توافق على الشروط التالية.\n\n"
                        ."1. الأهلية\n"
                        ."يجب أن يحمل مقدمو الخدمة الصحية (الأطباء، الممرضون، المستشفيات) ترخيصًا مهنيًا ساري المفعول، ويخضعون للمراجعة والموافقة قبل تفعيل حساباتهم.\n\n"
                        ."2. الحجوزات\n"
                        ."الحجوزات التي تتم عبر صحتي هي اتفاق مباشر بين المريض ومقدم الخدمة. تُسهّل صحتي عملية التواصل لكنها ليست جهة تقديم رعاية طبية بحد ذاتها.\n\n"
                        ."3. سلوك المستخدم\n"
                        ."يوافق المستخدمون على تقديم معلومات دقيقة واستخدام المنصة فقط لأغراض صحية مشروعة.\n\n"
                        ."4. الإلغاء\n"
                        ."قد تختلف سياسات الإلغاء وإعادة الجدولة حسب مقدم الخدمة، وتُعرض قبل تأكيد الحجز.\n\n"
                        ."5. تعليق الحساب\n"
                        ."تحتفظ صحتي بحق تعليق أي حساب يخالف هذه الشروط، أو يقدّم بيانات غير صحيحة، أو يسيء استخدام المنصة.\n\n"
                        ."6. التعديلات على هذه الشروط\n"
                        ."قد نقوم بتحديث هذه الشروط من وقت لآخر. استمرار استخدامك لصحتي بعد أي تحديث يعني موافقتك على الشروط المُعدّلة.",
                ],
            ],
        ];

        foreach ($documents as $type => $doc) {
            LegalDocument::updateOrCreate(
                ['type' => $type],
                [
                    'content' => $doc['content'],
                    'version' => $doc['version'],
                ]
            );
        }
    }
}
