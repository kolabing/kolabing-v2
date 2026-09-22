<?php

declare(strict_types=1);

/*
 * Fixed furniture for the outreach pitch (BE-NF-65). The generated copy is the
 * draft's `body_markdown`; everything here is the template's and never changes per
 * pitch — which is why the estimate's wording lives at this end. "Estimate", "based
 * on", "not a guarantee" are the words that keep a revenue figure honest, and they
 * are too load-bearing to leave to a language model.
 */
return [
    'greeting' => 'Hi :name,',

    'estimate_heading' => 'What a night like this could be worth',

    'estimate_line' => ':attendees people through the door × :spend average spend ≈ :total for the evening.',

    'estimate_disclaimer' => 'This is an estimate, not a guarantee — it is based on the community\'s own membership numbers and a typical spend for a venue like yours. Adjust either number and the maths is yours to check.',

    'cta_button' => 'See the collaboration',

    'signoff' => 'If it sounds worth a try, just reply to this email and we will set it up.'."\n\n".'— The Kolabing team',
];
