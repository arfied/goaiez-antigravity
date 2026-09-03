<?php

declare(strict_types=1);

return [
    'consent' => [
        'sms_text' => 'By providing your phone number, you agree to receive automated text messages from GO AI EZ on behalf of :business. Consent is not a condition of purchase. Message and data rates may apply. Message frequency varies. Reply STOP to opt out of all messages or HELP for help. See our Terms of Service and Privacy Policy.',
        'email_text' => 'By providing your email, you agree to receive updates and promotional emails from :business. You can unsubscribe at any time using the link in our emails. See our Terms of Service and Privacy Policy.',
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
        'email_label' => 'Email Updates',
        'sms_label' => 'SMS Updates',
        'phi_analysis_label' => 'Health Information Undertaking',
        'phi_analysis_text' => 'I confirm that this review does not contain protected health information.',
    ],
    'heading' => 'How did we do?',
    'intro' => 'We would love to hear about your experience.',
    'rating' => [
        'legend' => 'Tap to rate',
        'required' => 'Please select a rating.',
    ],
    'comment' => [
        'label' => 'Your feedback',
        'placeholder' => 'Tell us what you loved, or what we can do better...',
    ],
    'contact' => [
        'heading' => 'About you',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone number',
    ],
    'thanks' => [
        'heading' => 'Thank you!',
        'body' => 'Your feedback has been received.',
    ],
    'submit' => 'Submit Feedback',
    'errors' => [
        'rate_limited' => 'You have submitted too many requests. Please try again later.',
        'sms_needs_phone' => 'A phone number is required to receive SMS updates.',
        'email_needs_email' => 'An email address is required to receive email updates.',
        'too_fast' => 'You are submitting too fast. Please wait a moment.',
    ],
];
