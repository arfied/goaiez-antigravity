import sys
content = open("app/tests/Feature/Console/DemoFillSixtyTest.php").read()

content = content.replace("""<<<<<<< HEAD
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,C-Whatsapp,X-66,X-102,X-209'])->assertExitCode(0);
=======
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,X-66,X-102,X-118,X-209'])->assertExitCode(0);
>>>>>>> origin/track/sixty""", """        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,C-Whatsapp,X-66,X-102,X-118,X-209'])->assertExitCode(0);""")

content = content.replace("""<<<<<<< HEAD
        $this->assertDatabaseHas('whatsapp_templates', ['business_id' => $biz->id, 'name' => 'demo·quote_followup']);

        Tenancy::forgetAll();
=======
        $this->assertDatabaseHas('onboarding_steps', ['business_id' => $biz->id, 'step_name' => 'demo·industry inferred']);

        Tenancy::forgetAll();
>>>>>>> origin/track/sixty""", """        $this->assertDatabaseHas('whatsapp_templates', ['business_id' => $biz->id, 'name' => 'demo·quote_followup']);
        $this->assertDatabaseHas('onboarding_steps', ['business_id' => $biz->id, 'step_name' => 'demo·industry inferred']);

        Tenancy::forgetAll();""")

content = content.replace("""<<<<<<< HEAD
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,C-Whatsapp,X-66,X-102,X-209'])->assertExitCode(0);
=======
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,X-66,X-102,X-118,X-209'])->assertExitCode(0);
>>>>>>> origin/track/sixty""", """        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,C-Whatsapp,X-66,X-102,X-118,X-209'])->assertExitCode(0);""")

content = content.replace("""<<<<<<< HEAD
        $this->assertDatabaseMissing('whatsapp_templates', ['business_id' => $biz->id, 'name' => 'demo·quote_followup']);
=======
        $this->assertDatabaseMissing('onboarding_steps', ['business_id' => $biz->id, 'step_name' => 'demo·industry inferred']);
>>>>>>> origin/track/sixty""", """        $this->assertDatabaseMissing('whatsapp_templates', ['business_id' => $biz->id, 'name' => 'demo·quote_followup']);
        $this->assertDatabaseMissing('onboarding_steps', ['business_id' => $biz->id, 'step_name' => 'demo·industry inferred']);""")

content = content.replace("""<<<<<<< HEAD
        $this->get(route('c-whatsapp.template-status-card'))->assertOk()->assertSee('demo·appointment_reminder');
        $this->get(route('c-whatsapp.template-approval-queue'))->assertOk()->assertSee('demo·quote_followup');
=======
        $this->get(route('x-118.groundcheck'))->assertOk()->assertSee('demo·contract missing');
>>>>>>> origin/track/sixty""", """        $this->get(route('c-whatsapp.template-status-card'))->assertOk()->assertSee('demo·appointment_reminder');
        $this->get(route('c-whatsapp.template-approval-queue'))->assertOk()->assertSee('demo·quote_followup');
        $this->get(route('x-118.groundcheck'))->assertOk()->assertSee('demo·contract missing');""")

open("app/tests/Feature/Console/DemoFillSixtyTest.php", "w").write(content)
