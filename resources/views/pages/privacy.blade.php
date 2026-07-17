@php
    $title = 'Privacy Policy';
    $description = 'How Kolabing collects, uses and protects your personal data.';
    $canonical = route('privacy');
    $locale = 'en';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('privacy')],
        ['hreflang' => 'es', 'href' => route('privacy.es')],
        ['hreflang' => 'x-default', 'href' => route('privacy')],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <section class="mx-auto max-w-4xl px-6 py-20">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-off-black/50">Last updated: {{ $company->effectiveDateLabel($locale) }}</p>
            <p class="text-sm font-semibold"><span>English</span> <span class="text-off-black/30">·</span> <a href="{{ route('privacy.es') }}" class="text-off-black/60 underline hover:text-off-black">Español</a></p>
        </div>
        <h1 class="mt-4 font-montserrat text-4xl font-black uppercase md:text-5xl">Privacy Policy</h1>
        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-headings:uppercase prose-a:text-off-black">
            <p>This Privacy Policy explains how Kolabing collects, uses, shares and protects your personal data when you use our platform, mobile applications and related services (the "Service"). We are committed to protecting your privacy and complying with the EU General Data Protection Regulation (GDPR) and the Estonian Personal Data Protection Act (Isikuandmete kaitse seadus).</p>

            <h2>1. Who We Are</h2>
            <p>The data controller responsible for your personal data is:</p>
            <ul>
                <li><strong>{{ $company->legal_name }}</strong></li>
                <li>Registered address: {{ $company->registered_address }}</li>
                <li>Company registration number: {{ $company->registration_number }}</li>
                <li>Privacy contact: <a href="mailto:{{ $company->privacy_email }}">{{ $company->privacy_email }}</a></li>
            </ul>

            <h2>2. Information We Collect</h2>
            <p>We collect the following categories of personal data:</p>
            <ul>
                <li><strong>Account and identity data.</strong> When you sign in with Google or Apple, we receive your name, email address, email-verified status and profile photo/avatar. If you use email sign-in, we process your password and password-reset requests. Your profile may include a handle, city, interests and language preference.</li>
                <li><strong>Photos.</strong> Profile photos, business "offer" photos, profile gallery photos and event photos that you choose to upload. We also process QR codes when you scan them for event check-in and reward redemption.</li>
                <li><strong>Location data.</strong> With your permission, we process your approximate or precise location (latitude and longitude) to show you nearby events and to enable event check-in.</li>
                <li><strong>Push notifications.</strong> If you enable notifications, we process a device push token (through Firebase Cloud Messaging) and your notification preferences.</li>
                <li><strong>Messaging.</strong> In-app chat threads and messages exchanged between users.</li>
                <li><strong>Payment data.</strong> If you are a business user with a monthly subscription, payments are processed by Stripe. We do not store your full card number; we retain limited transaction and subscription details.</li>
                <li><strong>Usage and analytics data.</strong> Product analytics collected through PostHog to understand how the Service is used and to improve it. You can opt out of analytics.</li>
                <li><strong>Support communications.</strong> The content of any messages you send us for support.</li>
            </ul>

            <h2>3. App Permissions We Request</h2>
            <p>To provide certain features, our app may ask for the following device permissions. These permissions are <strong>optional</strong>, and you can grant or revoke them at any time in your device settings:</p>
            <ul>
                <li><strong>Camera and photo library</strong> — to take or select profile, offer, gallery and event photos, and to scan QR codes.</li>
                <li><strong>Location</strong> — to discover nearby events and to check in to events.</li>
                <li><strong>Notifications</strong> — to send you push notifications about your activity on the Service.</li>
            </ul>
            <p>If you decline or revoke a permission, the related feature may not work, but you can continue to use the rest of the Service.</p>

            <h2>4. How We Use Your Information and Our Legal Bases</h2>
            <p>Under Article 6 of the GDPR, we rely on the following legal bases:</p>
            <ul>
                <li><strong>Performance of a contract (Art. 6(1)(b)).</strong> To create and manage your account, provide the Service, enable collaborations, process subscriptions and provide support.</li>
                <li><strong>Consent (Art. 6(1)(a)).</strong> To access your camera, photos, location and to send push notifications; and to use optional analytics. You can withdraw consent at any time.</li>
                <li><strong>Legitimate interests (Art. 6(1)(f)).</strong> To keep the Service secure, prevent fraud and abuse, improve and develop features, and communicate with you about the Service, balanced against your rights.</li>
                <li><strong>Legal obligation (Art. 6(1)(c)).</strong> To comply with accounting, tax and other legal requirements.</li>
            </ul>

            <h2>5. Sharing and Processors</h2>
            <p>We do not sell your personal data. We share it only with trusted service providers ("processors") who process it on our behalf under contract, and where required by law. These include:</p>
            <ul>
                <li><strong>Google</strong> and <strong>Apple</strong> — sign-in and authentication.</li>
                <li><strong>Stripe</strong> — payment processing.</li>
                <li><strong>Firebase Cloud Messaging</strong> and the <strong>Apple Push Notification service</strong> — delivery of push notifications.</li>
                <li><strong>Cloud hosting providers</strong> — hosting and storage of the Service and its data.</li>
                <li><strong>PostHog</strong> — product analytics.</li>
            </ul>
            <p>We may also share data with other users as part of the Service (for example, your profile and messages), and with authorities or advisers where necessary to comply with the law or protect our rights.</p>

            <h2>6. International Transfers</h2>
            <p>Some of our processors may store or process data outside the European Economic Area (EEA). Where this happens, we ensure appropriate safeguards are in place, such as the European Commission's Standard Contractual Clauses or an adequacy decision, so that your data receives an equivalent level of protection.</p>

            <h2>7. Data Retention</h2>
            <p>We keep your personal data only for as long as necessary for the purposes described in this policy. In general, we keep account data for as long as your account is active. When you delete your account, we soft-delete it and then remove or anonymise your personal data within a reasonable period, except where we must retain certain information to comply with legal obligations (such as tax and accounting records) or to resolve disputes.</p>

            <h2>8. Your Rights</h2>
            <p>Under the GDPR and the Estonian Personal Data Protection Act, you have the right to:</p>
            <ul>
                <li><strong>Access</strong> — obtain a copy of the personal data we hold about you.</li>
                <li><strong>Rectification</strong> — correct inaccurate or incomplete data.</li>
                <li><strong>Erasure</strong> — request deletion of your data ("right to be forgotten"). You can delete your account at any time, and we will erase your data subject to our retention obligations.</li>
                <li><strong>Restriction</strong> — ask us to limit how we process your data.</li>
                <li><strong>Portability</strong> — receive your data in a structured, commonly used, machine-readable format.</li>
                <li><strong>Objection</strong> — object to processing based on our legitimate interests.</li>
                <li><strong>Withdraw consent</strong> — withdraw any consent you have given, at any time, without affecting prior processing.</li>
            </ul>
            <p>To exercise your rights, contact us at <a href="mailto:{{ $company->privacy_email }}">{{ $company->privacy_email }}</a>. You also have the right to lodge a complaint with the Estonian supervisory authority, the Data Protection Inspectorate (Andmekaitse Inspektsioon), at <a href="https://www.aki.ee">www.aki.ee</a>.</p>

            <h2>9. Children</h2>
            <p>The Service is intended only for people aged 18 or older. We do not knowingly collect personal data from anyone under 18. If you believe a person under 18 has provided us with personal data, please contact us so we can delete it.</p>

            <h2>10. Security</h2>
            <p>We use appropriate technical and organisational measures to protect your personal data against loss, misuse and unauthorised access, including encryption in transit, access controls and secure hosting. No system is completely secure, but we work continuously to protect your data and will notify you and the Data Protection Inspectorate of a personal data breach where required by law.</p>

            <h2>11. Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time. If we make material changes, we will notify you (for example, in the app or by email) and update the "Last updated" date above. We encourage you to review this policy periodically.</p>

            <h2>12. Contact</h2>
            <p>If you have any questions about this Privacy Policy or how we handle your data, please contact us:</p>
            <ul>
                <li><strong>{{ $company->legal_name }}</strong></li>
                <li>Registered address: {{ $company->registered_address }}</li>
                <li>Company registration number: {{ $company->registration_number }}</li>
                <li>Privacy: <a href="mailto:{{ $company->privacy_email }}">{{ $company->privacy_email }}</a></li>
                <li>Support: <a href="mailto:{{ $company->support_email }}">{{ $company->support_email }}</a></li>
            </ul>
        </div>
    </section>
</x-layouts.marketing-page>
