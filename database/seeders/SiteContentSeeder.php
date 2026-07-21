<?php

namespace Database\Seeders;

use App\Enums\SiteContentKey;
use App\Enums\SiteContentType;
use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => SiteContentKey::THEME->value,
                'type' => SiteContentType::SETTING->value,
                'sort_order' => 0,
                'value' => [
                    'primary' => '#0EA5A4',
                    'secondary' => '#0F172A',
                    'accent' => '#F59E0B',
                    'background' => '#FFFFFF',
                    'text' => '#0F172A',
                    'font' => "'Inter', 'Tajawal', sans-serif",
                ],
            ],
            [
                'key' => SiteContentKey::HERO->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 10,
                'value' => [
                    'en' => [
                        'title' => 'Healthcare that comes to <span class="accent">you</span>.',
                        'subtitle' => 'Sahtee connects you with trusted doctors, nurses, and hospitals across the country — book a home visit, a clinic appointment, or emergency care in a few taps.',
                        'cta_text' => 'Get the app',
                        'cta_url' => '#screenshots',
                        'secondary_cta_text' => 'For hospitals & providers',
                        'secondary_cta_url' => '#how_we_are',
                    ],
                    'ar' => [
                        'title' => 'رعاية صحية تصل إلى <span class="accent">بابك</span>.',
                        'subtitle' => 'تربطك منصة صحتي بأطباء وممرضين ومستشفيات موثوقين في مختلف المحافظات — احجز زيارة منزلية أو موعدًا في العيادة أو رعاية طارئة بضغطات قليلة.',
                        'cta_text' => 'حمّل التطبيق',
                        'cta_url' => '#screenshots',
                        'secondary_cta_text' => 'للمستشفيات ومقدمي الخدمة',
                        'secondary_cta_url' => '#how_we_are',
                    ],
                ],
            ],
            [
                'key' => SiteContentKey::HOW_WE_ARE->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 20,
                'value' => [
                    'en' => [
                        'title' => 'Who we are',
                        'body' => "Sahtee is a digital healthcare platform built to make quality care reachable for everyone, everywhere.\n\nWe bring doctors, nurses, and hospitals onto one platform so patients can find the right care fast — whether that means booking a home nursing visit, reserving a hospital bed, or consulting a specialist nearby.\n\nEvery provider on Sahtee is verified and approved before they can accept a single reservation, so you can trust who's on the other side of the appointment.",
                    ],
                    'ar' => [
                        'title' => 'من نحن',
                        'body' => "صحتي منصة صحية رقمية صُممت لتقريب الرعاية الصحية الجيدة من الجميع، في كل مكان.\n\nنجمع الأطباء والممرضين والمستشفيات في منصة واحدة ليجد المريض الرعاية المناسبة بسرعة — سواء كان ذلك بحجز زيارة تمريض منزلية، أو حجز سرير في مستشفى، أو استشارة طبيب مختص قريب منه.\n\nكل مقدّم خدمة على صحتي يخضع للتحقق والموافقة قبل أن يتمكن من قبول أي حجز، لتطمئن إلى من تتعامل معه.",
                    ],
                ],
            ],
            [
                'key' => SiteContentKey::FEATURES->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 30,
                'value' => [
                    'en' => [
                        'title' => 'Everything care-related, in one app',
                        'subtitle' => 'From finding a specialist to getting a nurse at home, Sahtee covers the whole journey.',
                        'items' => [
                            ['icon' => '🩺', 'title' => 'Verified doctors', 'desc' => 'Browse specialists by province and specialization, and book an appointment in minutes.'],
                            ['icon' => '🏠', 'title' => 'Home nursing', 'desc' => 'Request a licensed nurse to visit you at home for a wide range of services.'],
                            ['icon' => '🏥', 'title' => 'Hospital reservations', 'desc' => 'Reserve hospital services and beds directly, without the back-and-forth phone calls.'],
                            ['icon' => '🔔', 'title' => 'Live notifications', 'desc' => 'Get instant updates on approvals, reservations, and reminders — never miss a visit.'],
                            ['icon' => '⭐', 'title' => 'Ratings you can trust', 'desc' => 'Real reviews from real patients help you pick the right provider every time.'],
                            ['icon' => '🔒', 'title' => 'Secure by design', 'desc' => 'Your medical and personal data stays protected, always under your control.'],
                        ],
                    ],
                    'ar' => [
                        'title' => 'كل ما يخص الرعاية الصحية، في تطبيق واحد',
                        'subtitle' => 'من إيجاد طبيب مختص إلى استقدام ممرض إلى المنزل، تغطي صحتي الرحلة كاملة.',
                        'items' => [
                            ['icon' => '🩺', 'title' => 'أطباء موثوقون', 'desc' => 'تصفّح المختصين حسب المحافظة والاختصاص واحجز موعدك خلال دقائق.'],
                            ['icon' => '🏠', 'title' => 'تمريض منزلي', 'desc' => 'اطلب ممرضًا مرخّصًا ليزورك في المنزل لمجموعة واسعة من الخدمات.'],
                            ['icon' => '🏥', 'title' => 'حجز خدمات المستشفيات', 'desc' => 'احجز خدمات وأسرّة المستشفيات مباشرة، دون الحاجة لاتصالات متكررة.'],
                            ['icon' => '🔔', 'title' => 'إشعارات فورية', 'desc' => 'تابع حالة الموافقات والحجوزات والتذكيرات لحظة بلحظة.'],
                            ['icon' => '⭐', 'title' => 'تقييمات موثوقة', 'desc' => 'آراء حقيقية من مرضى حقيقيين تساعدك على اختيار مقدم الخدمة الأنسب.'],
                            ['icon' => '🔒', 'title' => 'أمان بالتصميم', 'desc' => 'بياناتك الطبية والشخصية محمية دائمًا وتحت سيطرتك.'],
                        ],
                    ],
                ],
            ],
            [
                'key' => SiteContentKey::STATS->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 40,
                'value' => [
                    'en' => [
                        'title' => 'Sahtee in numbers',
                        'items' => [
                            ['value' => '500+', 'label' => 'Verified doctors'],
                            ['value' => '300+', 'label' => 'Home-care nurses'],
                            ['value' => '50+', 'label' => 'Partner hospitals'],
                            ['value' => '14', 'label' => 'Provinces covered'],
                        ],
                    ],
                    'ar' => [
                        'title' => 'صحتي بالأرقام',
                        'items' => [
                            ['value' => '+500', 'label' => 'طبيب موثوق'],
                            ['value' => '+300', 'label' => 'ممرض رعاية منزلية'],
                            ['value' => '+50', 'label' => 'مستشفى شريك'],
                            ['value' => '14', 'label' => 'محافظة مغطاة'],
                        ],
                    ],
                ],
            ],
            [
                'key' => SiteContentKey::SCREENSHOTS->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 50,
                // No image files are seeded here — upload real app screenshots
                // through POST /api/admin/site-content (multipart, "images[]")
                // once the app has UI to capture. Left empty on purpose.
                'value' => [
                    'en' => [
                        'title' => 'See Sahtee in action',
                        'subtitle' => 'A quick look at booking a doctor, requesting a nurse, and tracking a reservation.',
                    ],
                    'ar' => [
                        'title' => 'شاهد صحتي أثناء الاستخدام',
                        'subtitle' => 'لمحة سريعة عن حجز طبيب، وطلب ممرض، ومتابعة حجز.',
                    ],
                ],
            ],
            [
                'key' => SiteContentKey::CTA->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 60,
                'value' => [
                    'en' => [
                        'title' => 'Ready to take control of your care?',
                        'subtitle' => 'Join thousands of patients and providers already using Sahtee.',
                        'button_text' => 'Download Sahtee',
                        'button_url' => '#screenshots',
                    ],
                    'ar' => [
                        'title' => 'جاهز لتتحكم برعايتك الصحية؟',
                        'subtitle' => 'انضم إلى آلاف المرضى ومقدمي الخدمة الذين يستخدمون صحتي بالفعل.',
                        'button_text' => 'حمّل صحتي',
                        'button_url' => '#screenshots',
                    ],
                ],
            ],
            [
                'key' => SiteContentKey::FOOTER->value,
                'type' => SiteContentType::SECTION->value,
                'sort_order' => 70,
                'value' => [
                    'en' => ['tagline' => 'Quality healthcare, one tap away.'],
                    'ar' => ['tagline' => 'رعاية صحية بجودة عالية، على بُعد ضغطة واحدة.'],
                ],
            ],
        ];

        foreach ($rows as $row) {
            SiteContent::updateOrCreate(
                ['key' => $row['key']],
                [
                    'type' => $row['type'],
                    'value' => $row['value'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
