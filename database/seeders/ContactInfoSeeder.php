<?php

namespace Database\Seeders;

use App\Models\ContactInfo;
use Illuminate\Database\Seeder;

class ContactInfoSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [
            ['name' => 'Facebook', 'url' => 'https://facebook.com/sahtee.app'],
            ['name' => 'Instagram', 'url' => 'https://instagram.com/sahtee.app'],
            ['name' => 'WhatsApp', 'url' => 'https://wa.me/963000000000'],
            ['name' => 'LinkedIn', 'url' => 'https://linkedin.com/company/sahtee'],
        ];

        foreach ($contacts as $contact) {
            ContactInfo::updateOrCreate(
                ['name' => $contact['name']],
                ['url' => $contact['url']]
            );
        }
    }
}
