<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JourneyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_session_starts_at_created_state(): void
    {
        $session = $this->beginSession();
        $this->assertSame(SessionState::Created, $session->state);
    }

    public function test_cannot_skip_consent(): void
    {
        $this->beginSession();

        // State is `created` — intake/photo/live/result all bounce back.
        $this->get('/intake')->assertRedirect('/consent');
        $this->get('/photo')->assertRedirect('/consent');
        $this->get('/live')->assertRedirect('/consent');
        $this->get('/result')->assertRedirect('/consent');
    }

    public function test_consent_required_before_photo(): void
    {
        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        // Consented — photo is still gated until intake completes.
        $this->get('/photo')->assertRedirect('/intake');
        $this->get('/live')->assertRedirect('/intake');
    }

    public function test_consent_requires_both_acknowledgements(): void
    {
        $this->beginSession();

        $this->post('/consent', ['agree_processing' => 'yes'])
            ->assertSessionHasErrors('agree_biometrics');

        $this->post('/consent', ['agree_biometrics' => 'yes'])
            ->assertSessionHasErrors('agree_processing');
    }

    public function test_full_state_progression(): void
    {
        $session = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->assertSame(SessionState::Consented, $session->fresh()->state);

        $this->put('/intake', [
            'full_name' => 'Test Volunteer',
            'email' => 'v@example.com',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ]);
        $this->verifyEmail();
        $this->assertSame(SessionState::IntakeDone, $session->fresh()->state);

        $this->post('/photo', ['photo' => $this->noiseImage()]);
        $this->assertSame(SessionState::PhotoOk, $session->fresh()->state);
    }

    public function test_intake_validation_enforced(): void
    {
        $session = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        $this->put('/intake', ['full_name' => 'A', 'email' => 'not-an-email', 'phone' => '12'])
            ->assertSessionHasErrors(['full_name', 'email', 'phone_country_code', 'phone']);

        $this->assertSame(SessionState::Consented, $session->fresh()->state);
    }

    public function test_full_name_requires_first_and_last_name(): void
    {
        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        $intake = fn (string $name) => [
            'full_name' => $name,
            'email' => 'v@example.com',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ];

        // Single name, digits and symbols are all rejected.
        foreach (['Amara', 'Amara123 Okafor', 'Ama@ra Okafor', 'Amara Okafor!', '123 456'] as $name) {
            $this->put('/intake', $intake($name))->assertSessionHasErrors('full_name');
        }

        // Hyphenated and apostrophised multi-part names are accepted.
        $this->put('/intake', $intake("Amara O'kafor-Smith"))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_email_requires_a_real_domain(): void
    {
        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        $intake = fn (string $email) => [
            'full_name' => 'Test Volunteer',
            'email' => $email,
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ];

        foreach (['v@example', 'v@x.c', 'plainaddress', 'v@.com'] as $email) {
            $this->put('/intake', $intake($email))->assertSessionHasErrors('email');
        }

        $this->put('/intake', $intake('v@example.com'))->assertSessionDoesntHaveErrors();
    }

    public function test_unresolvable_email_domain_rejected(): void
    {
        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        config(['verification.email_dns_check' => true]);

        $this->put('/intake', [
            'full_name' => 'Test Volunteer',
            'email' => 'v@gmdd.comm',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ])->assertSessionHasErrors('email');
    }

    public function test_phone_digits_match_country_code(): void
    {
        $session = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        $intake = fn (string $code, string $phone) => [
            'full_name' => 'Test Volunteer',
            'email' => 'v@example.com',
            'phone_country_code' => $code,
            'phone' => $phone,
        ];

        // Nigeria (+234): 10–11 digits.
        $this->put('/intake', $intake('+234', '80123'))->assertSessionHasErrors('phone');
        $this->put('/intake', $intake('+234', '80123456789012'))->assertSessionHasErrors('phone');

        // US/Canada (+1): exactly 10 digits.
        $this->put('/intake', $intake('+1', '415555267'))->assertSessionHasErrors('phone');

        // Kenya (+254): 9–10 digits.
        $this->put('/intake', $intake('+254', '71234567'))->assertSessionHasErrors('phone');

        // Unsupported calling code rejected outright.
        $this->put('/intake', $intake('+999', '8012345678'))->assertSessionHasErrors('phone_country_code');

        // A valid PUT leaves the session consented until the emailed code
        // is verified, so consecutive valid submissions are all routable.
        foreach ([['+234', '8012345678'], ['+1', '4155552671'], ['+254', '0712345678']] as [$code, $phone]) {
            $this->put('/intake', $intake($code, $phone))->assertSessionDoesntHaveErrors();
        }
    }

    public function test_email_verification_code_flow(): void
    {
        $session = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        $this->put('/intake', [
            'full_name' => 'Test Volunteer',
            'email' => '  V@Example.COM ',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ])->assertRedirect('/intake');

        $code = session('dev_email_code');

        // Normalised before storage — lowercase + trimmed.
        $participant = $session->participant->fresh();
        $this->assertSame('v@example.com', $participant->email);
        $this->assertNull($participant->email_verified_at);
        $this->assertSame(SessionState::Consented, $session->fresh()->state); // not advanced yet

        // Wrong code rejected; journey stays consented.
        $this->post('/intake/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertSame(SessionState::Consented, $session->fresh()->state);

        // Expired code rejected.
        $participant->update(['email_code_expires_at' => now()->subMinute()]);
        $this->post('/intake/verify', ['code' => $code])->assertSessionHasErrors('code');
        $participant->update(['email_code_expires_at' => now()->addMinutes(10)]);

        // Correct code verifies the email and advances the journey.
        $this->post('/intake/verify', ['code' => $code])->assertRedirect('/photo');
        $this->assertSame(SessionState::IntakeDone, $session->fresh()->state);
        $this->assertNotNull($session->participant->fresh()->email_verified_at);
    }

    public function test_email_is_unique_per_volunteer(): void
    {
        Participant::create([
            'op_volunteer_ref' => 'vol-other',
            'task_id' => 'OP-OTHER',
            'email' => 'taken@example.com',
        ]);

        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);

        // Same address in a different case, held by another volunteer.
        $this->put('/intake', [
            'full_name' => 'Test Volunteer',
            'email' => 'TAKEN@example.com',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ])->assertSessionHasErrors('email');
    }

    public function test_expired_session_withdraws_and_exits(): void
    {
        $session = $this->beginSession();
        $session->update(['expires_at' => now()->subMinute()]);

        $this->get('/consent')->assertRedirect('/exit?reason=expired');
        $this->assertSame(SessionState::Withdrawn, $session->fresh()->state);

        // Exit page is reachable from withdrawn state.
        $this->get('/exit')->assertOk();
    }

    public function test_terminal_states_cannot_reenter_journey(): void
    {
        $session = $this->beginSession();
        $session->update(['state' => SessionState::Verified]);

        $this->get('/consent')->assertRedirect('/result');
        $this->get('/photo')->assertRedirect('/result');
        $this->post('/withdraw')->assertRedirect('/result'); // no re-withdrawal
    }

    public function test_duplicate_handoff_link_rejected_after_use(): void
    {
        $token = $this->mintHandoff();
        $this->post('/start?t='.urlencode($token))->assertRedirect('/consent');

        // Same link a second time — jti already bound to a session.
        $this->post('/start?t='.urlencode($token))
            ->assertInertia(fn ($page) => $page->where('linkState', 'used'));
    }

    public function test_session_resumes_at_correct_step(): void
    {
        $session = $this->beginSession();
        $this->driveToPhoto($session);

        // A fresh request mid-journey resumes at /photo, not the start.
        $this->get('/photo')->assertOk();
        $this->get('/consent')->assertRedirect('/photo');
    }
}
