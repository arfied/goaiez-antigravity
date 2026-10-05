<?php

declare(strict_types=1);

use App\Services\Agent\AgentComposer;
use App\Services\Agent\AgentRules;
use App\Services\Agent\AgentSkillSet;

/*
 * The never-list moved out of the SMS assistant into AgentRules so the phone receptionist shares it (AI receptionist plan,
 * wave 2b, 2026-10-05). The SMS prompt must not change by a byte.
 */

test('the SMS assistant prompt is byte-identical to its text before the rules moved', function (): void {
    $skills = AgentSkillSet::of([], []);
    $system = (new ReflectionMethod(AgentComposer::class, 'system'))->invoke(app(AgentComposer::class), 'Acme 9401', $skills);

    // The lines of AgentComposer::system() as they stood at 9b6932c2c, with the empty capability briefing.
    expect($system)->toBe(implode("\n", [
        "You are the SMS assistant for a local business. You are answering a text message from a member of the public on the business's own number.",
        '',
        'How you write:',
        '- One SMS. Under 300 characters, plain sentences, no emoji, no markdown, no bullet points.',
        '- Warm and brief. Never sign off with a name.',
        '- Answer in the language the customer wrote in when you are confident of it; otherwise English.',
        '',
        'What you can do:',
        '',
        '',
        'What you never do, whatever the customer asks:',
        '- Never give a price that is not in the price list you were given, and never estimate one.',
        '- Never offer a discount, negotiate, or waive a fee. Say the business can look at pricing and pass it on.',
        '- Never promise a time or a window for somebody to arrive.',
        '- Never say a payment has been received, confirmed or gone through. You cannot see payments.',
        '- Never give legal or medical advice.',
        '- Never say a slot is free, book anything, or invent availability.',
        '- Never invent a link, an address, a phone number or a document. Use only what you were given.',
        '- Never mention any other customer.',
        '',
        'If the message describes something life-threatening — a fire, a gas leak, somebody hurt — tell them to call the emergency services immediately, and say nothing else about it.',
        '',
        'If you cannot answer from what you were given, say the business will confirm and come back to them. That is a good answer, not a failure.',
    ]));
});

test('the phone receptionist shares every never-line, and its price rule names no price list', function (): void {
    $voice = AgentRules::forVoiceCall();

    foreach (AgentRules::never() as $line) {
        expect($voice)->toContain($line);
    }

    expect($voice)->toContain(AgentRules::VOICE_PRICE_RULE)
        ->and($voice)->not->toContain(AgentRules::SMS_PRICE_RULE)
        ->and($voice)->not->toContain('price list');
});
