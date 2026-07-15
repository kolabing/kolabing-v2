@php
    $title = 'Terms of Service';
    $description = 'The terms that govern your use of Kolabing.';
    $canonical = route('terms');
    $locale = 'en';
    $alternates = [
        ['hreflang' => 'en', 'href' => route('terms')],
        ['hreflang' => 'es', 'href' => route('terms.es')],
        ['hreflang' => 'x-default', 'href' => route('terms')],
    ];
@endphp

<x-layouts.marketing-page :title="$title" :description="$description" :canonical="$canonical" :locale="$locale" :alternates="$alternates">
    <section class="mx-auto max-w-4xl px-6 py-20">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-off-black/50">Last updated: {{ $company->effectiveDateLabel($locale) }}</p>
            <p class="text-sm font-semibold"><span>English</span> <span class="text-off-black/30">·</span> <a href="{{ route('terms.es') }}" class="text-off-black/60 underline hover:text-off-black">Español</a></p>
        </div>
        <h1 class="mt-4 font-montserrat text-4xl font-black uppercase md:text-5xl">Terms of Service</h1>
        <div class="prose prose-lg mt-8 max-w-none prose-headings:font-montserrat prose-headings:uppercase prose-a:text-off-black">
            <p>Welcome to Kolabing. These Terms of Service ("Terms") are a legal agreement between you and {{ $company->legal_name }} ("Kolabing", "we", "us" or "our"), the operator of the Kolabing platform, mobile applications and related services (together, the "Service"). Please read them carefully. By creating an account or using the Service, you agree to be bound by these Terms.</p>

            <h2>1. Acceptance and Eligibility</h2>
            <p>By accessing or using the Service, you confirm that you have read, understood and agree to these Terms and to our Privacy Policy. If you do not agree, please do not use the Service.</p>
            <p>You must be at least 18 years old to use Kolabing. By using the Service you represent and warrant that you are 18 or older and that you have the legal capacity to enter into these Terms. If you use the Service on behalf of a business or organisation, you represent that you are authorised to bind that entity to these Terms.</p>

            <h2>2. The Service</h2>
            <p>Kolabing is a collaboration marketplace based in Spain that connects local businesses with community organisers, and also supports an attendee and gamification experience. Depending on how you sign up, you may use the Service as one of the following:</p>
            <ul>
                <li><strong>Businesses</strong> — create and publish collaboration opportunities and offers.</li>
                <li><strong>Communities</strong> — browse opportunities and apply to collaborate with businesses.</li>
                <li><strong>Attendees</strong> — discover nearby events, check in, complete challenges and earn rewards.</li>
            </ul>
            <p><strong>Kolabing is a facilitator, not a party to any collaboration.</strong> We provide the platform that helps businesses, communities and attendees find and connect with each other. We are not a party to, and are not responsible for, any agreement, arrangement, event, offer, payment or outcome between users. We do not guarantee the quality, safety, legality or performance of any collaboration, opportunity, reward or user.</p>

            <h2>3. Accounts</h2>
            <p>To use most features you must create an account. You can sign in using Google or Apple, and in some cases with an email and password. You agree to:</p>
            <ul>
                <li>Provide accurate, current and complete information and keep it up to date.</li>
                <li>Keep your login credentials confidential and not share your account.</li>
                <li>Be responsible for all activity that happens under your account.</li>
                <li>Notify us promptly at <a href="mailto:{{ $company->support_email }}">{{ $company->support_email }}</a> if you suspect unauthorised use of your account.</li>
            </ul>
            <p>You are responsible for maintaining the security of your account and your device. We are not liable for any loss arising from your failure to keep your credentials secure.</p>

            <h2>4. Subscriptions and Payments</h2>
            <p>Certain features are available only to business users through a paid monthly subscription. Payments are processed by our payment provider, <strong>Stripe</strong>. We do not store your full card number.</p>
            <ul>
                <li><strong>Billing and renewal.</strong> Business subscriptions are billed monthly and renew automatically at the end of each billing period until cancelled.</li>
                <li><strong>Cancellation.</strong> You can cancel your subscription at any time. Cancellation takes effect at the end of the current billing period, and you will retain access to paid features until then.</li>
                <li><strong>Price changes.</strong> We may change subscription prices. We will give you reasonable advance notice, and any change will apply from your next billing period.</li>
                <li><strong>Refunds.</strong> {{ $company->refund_policy }}. Except where required by applicable Spanish and EU consumer law, subscription fees are non-refundable.</li>
                <li><strong>Taxes.</strong> Prices may be shown exclusive or inclusive of applicable taxes (such as VAT/IVA), as indicated at checkout.</li>
            </ul>

            <h2>5. Collaborations</h2>
            <p>When users agree to collaborate, they do so directly with each other. <strong>Users are solely responsible for their own agreements, logistics and outcomes</strong>, including the terms of any collaboration, the delivery of any offer or deliverable, event organisation, attendance, health and safety, and compliance with applicable law. Kolabing does not supervise, direct or control collaborations and disclaims responsibility for them. Any dispute between users must be resolved between the users involved.</p>

            <h2>6. User Content and Licence</h2>
            <p>The Service lets you upload and share content, such as profile photos, offer and gallery photos, event photos and messages ("User Content"). <strong>You keep ownership of your User Content.</strong></p>
            <p>By submitting User Content, you grant Kolabing a worldwide, non-exclusive, royalty-free licence to host, store, reproduce, adapt (for formatting and display), and display that content solely as needed to operate, provide and improve the Service. This licence ends when you delete the relevant content or your account, except for content already shared with others or where we must retain it to comply with the law.</p>
            <p>You are responsible for your User Content and confirm that you have the rights to share it and that it does not infringe anyone else's rights or break the law. You must obtain the consent of any identifiable person appearing in photos you upload.</p>

            <h2>7. Acceptable Use and Prohibited Conduct</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Break any applicable law or regulation, or infringe the rights of others.</li>
                <li>Post content that is unlawful, harmful, harassing, defamatory, hateful, deceptive or obscene.</li>
                <li>Impersonate any person or misrepresent your affiliation with anyone.</li>
                <li>Upload viruses, malicious code, or attempt to hack, disrupt or overload the Service.</li>
                <li>Scrape, harvest or collect data from the Service without our permission.</li>
                <li>Use the Service to send spam or unsolicited communications.</li>
                <li>Circumvent any security, subscription paywall, or access control.</li>
                <li>Use the Service for any fraudulent or misleading purpose.</li>
            </ul>

            <h2>8. Intellectual Property</h2>
            <p>The Service, including its software, design, text, graphics, logos and trademarks (but excluding User Content), is owned by Kolabing or its licensors and is protected by intellectual property laws. We grant you a limited, personal, non-transferable, revocable licence to use the Service for its intended purpose. You may not copy, modify, distribute, sell or create derivative works from any part of the Service without our prior written consent.</p>

            <h2>9. Third-Party Services</h2>
            <p>The Service relies on third-party providers (for example Google and Apple sign-in, Stripe for payments, and push notification services). Your use of those services may be subject to their own terms and policies. We are not responsible for third-party services and do not control them.</p>

            <h2>10. Suspension and Termination</h2>
            <p>You may stop using the Service and delete your account at any time. We may suspend or terminate your access to the Service, with or without notice, if you breach these Terms, if we are required to do so by law, or to protect the Service or other users. On termination, the licences you granted to us end (subject to Section 6), and provisions that by their nature should survive (such as disclaimers, limitation of liability and governing law) will continue to apply.</p>

            <h2>11. Disclaimers</h2>
            <p>The Service is provided "as is" and "as available". To the fullest extent permitted by law, we disclaim all warranties, whether express or implied, including fitness for a particular purpose, merchantability, and non-infringement. We do not warrant that the Service will be uninterrupted, error-free or secure, or that any content, opportunity, collaboration or reward will meet your expectations. Nothing in these Terms excludes any warranty or right that cannot be excluded under mandatory Spanish or EU law.</p>

            <h2>12. Limitation of Liability</h2>
            <p>To the fullest extent permitted by law, Kolabing will not be liable for any indirect, incidental, special, consequential or punitive damages, or for any loss of profits, revenue, data or goodwill, arising out of or relating to your use of the Service. Our total aggregate liability for all claims relating to the Service will not exceed the greater of the amounts you paid us in the twelve months before the event giving rise to the claim, or one hundred euros (€100). Nothing in these Terms limits liability that cannot be limited under mandatory law, including liability for death or personal injury caused by negligence, or for fraud.</p>

            <h2>13. Indemnity</h2>
            <p>You agree to indemnify and hold harmless Kolabing and its directors, employees and agents from any claims, damages, losses and expenses (including reasonable legal fees) arising out of your use of the Service, your User Content, your collaborations with other users, or your breach of these Terms or of any law.</p>

            <h2>14. Governing Law and Disputes</h2>
            <p>These Terms are governed by the laws of Spain. Subject to any mandatory rights you have as a consumer, any dispute relating to these Terms or the Service will be subject to the jurisdiction of the competent courts of Spain. If you are a consumer, you may also have the right to bring proceedings in the courts of your place of residence, and you may use the European Commission's online dispute resolution platform.</p>

            <h2>15. Changes to These Terms</h2>
            <p>We may update these Terms from time to time. If we make material changes, we will notify you (for example, in the app or by email) and update the "Last updated" date above. Changes take effect when posted, unless we state otherwise. Your continued use of the Service after changes take effect means you accept the updated Terms.</p>

            <h2>16. Contact</h2>
            <p>If you have any questions about these Terms, please contact us:</p>
            <ul>
                <li><strong>{{ $company->legal_name }}</strong></li>
                <li>Registered address: {{ $company->registered_address }}</li>
                <li>Company registration / NIF: {{ $company->registration_number }}</li>
                <li>Support: <a href="mailto:{{ $company->support_email }}">{{ $company->support_email }}</a></li>
            </ul>
        </div>
    </section>
</x-layouts.marketing-page>
