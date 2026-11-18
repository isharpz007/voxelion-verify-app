<?php

namespace Tests\Feature;

use App\Enums\SessionState;
use App\Mail\EmailVerificationCode;
use App\Models\Otp;
use App\Models\VerificationSession;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeSmsSender;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function driveToIntake(): VerificationSession
    {
        $session = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->put('/intake', [
            'full_name' => 'Test Volunteer',
            'email' => 'v@example.com',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ]);

        return $session;
    }

    private function fakeSms(): FakeSmsSender
    {
        $sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $sms);

        return $sms;
    }

    public function test_email_otp_is_delivered_by_mail(): void
    {
        Mail::fake();
        $this->driveToIntake();

        $this->post('/api/otp/send', ['channel' => 'email'])->assertSessionHasNoErrors();

        Mail::assertSent(EmailVerificationCode::class, fn (EmailVerificationCode $m) => $m->hasTo('v@example.com'));
    }

    public function test_sms_otp_is_delivered_to_the_phone(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();

        // Redirects back to the intake page — never back() to a stale
        // previousUrl (which would bounce the volunteer to /start).
        $this->post('/api/otp/send', ['channel' => 'sms'])->assertRedirect('/intake');

        $this->assertCount(1, $sms->sent);
        $this->assertSame('+2348012345678', $sms->sent[0]['to']);
        $this->assertMatchesRegularExpression('/\d{6}/', $sms->sent[0]['message']);
    }

    public function test_correct_otp_verifies_and_advances_the_journey(): void
    {
        $session = $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        $this->post('/api/otp/verify', ['code' => $sms->lastCode()])->assertRedirect('/photo');

        $this->assertSame(SessionState::IntakeDone, $session->fresh()->state);
        $this->assertNotNull($session->participant->fresh()->phone_verified_at);
        $this->assertNotNull(Otp::first()->verified_at);
    }

    public function test_email_channel_marks_email_verified(): void
    {
        Mail::fake();
        $session = $this->driveToIntake();
        $this->post('/api/otp/send', ['channel' => 'email']);

        $code = null;
        Mail::assertSent(EmailVerificationCode::class, function (EmailVerificationCode $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $this->post('/api/otp/verify', ['code' => $code])->assertRedirect('/photo');
        $this->assertNotNull($session->participant->fresh()->email_verified_at);
    }

    public function test_incorrect_otp_counts_an_attempt(): void
    {
        $this->driveToIntake();
        $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        $this->post('/api/otp/verify', ['code' => '999998'])->assertSessionHasErrors('code');
        $this->assertSame(1, Otp::first()->attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        Otp::first()->update(['expires_at' => now()->subMinute()]);

        $this->post('/api/otp/verify', ['code' => $sms->lastCode()])
            ->assertSessionHasErrors('code');
    }

    public function test_resend_issues_a_fresh_code(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        $this->travel(61)->seconds(); // clear the resend cooldown
        $this->post('/api/otp/resend', ['channel' => 'sms'])->assertSessionHasNoErrors();

        $this->assertCount(2, $sms->sent);
        $this->post('/api/otp/verify', ['code' => $sms->lastCode()])->assertRedirect('/photo');
    }

    public function test_resend_invalidates_the_previous_otp(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);
        $first = $sms->lastCode();

        $this->travel(61)->seconds();
        $this->post('/api/otp/resend', ['channel' => 'sms']);

        $this->post('/api/otp/verify', ['code' => $first])
            ->assertSessionHasErrors('code');
        $this->assertNotNull(Otp::oldest()->first()->invalidated_at);
    }

    public function test_five_wrong_attempts_kill_the_otp(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/api/otp/verify', ['code' => '000000']);
        }

        // Even the correct code is dead now.
        $this->post('/api/otp/verify', ['code' => $sms->lastCode()])
            ->assertSessionHasErrors('code');
        $this->assertNotNull(Otp::first()->invalidated_at);
    }

    public function test_resend_inside_the_cooldown_is_refused(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        $this->post('/api/otp/resend', ['channel' => 'sms'])
            ->assertSessionHasErrors('channel');
        $this->assertCount(1, $sms->sent);
    }

    public function test_send_window_caps_at_five_codes(): void
    {
        $this->driveToIntake();
        $this->fakeSms();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/api/otp/send', ['channel' => 'sms'])->assertSessionHasNoErrors();
            $this->travel(61)->seconds(); // clear each cooldown, stay in the 15-min window
        }

        $this->post('/api/otp/send', ['channel' => 'sms'])
            ->assertSessionHasErrors('channel');
        $this->assertSame(5, Otp::count());
    }

    public function test_provider_failure_invalidates_the_otp_and_reports_error(): void
    {
        $this->driveToIntake();
        $sms = $this->fakeSms();
        $sms->throws = true;

        $this->post('/api/otp/send', ['channel' => 'sms'])
            ->assertSessionHasErrors('channel');

        $this->assertNotNull(Otp::first()->invalidated_at);
        $this->assertSame(SessionState::Consented, VerificationSession::first()->state);
    }

    public function test_verified_otp_cannot_be_replayed(): void
    {
        $session = $this->driveToIntake();
        $sms = $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);
        $code = $sms->lastCode();

        $this->post('/api/otp/verify', ['code' => $code])->assertRedirect('/photo');

        // A concurrent or repeated submission of the same code must fail —
        // the state gate bounces it before the controller even runs, and no
        // second verification is recorded.
        $this->post('/api/otp/verify', ['code' => $code])->assertRedirect('/photo');
        $this->assertSame(1, Otp::whereNotNull('verified_at')->count());
    }

    public function test_otp_endpoints_respect_the_state_gate(): void
    {
        $this->beginSession(); // created — no consent yet

        // No journey bypass: send/verify bounce until consent is recorded.
        $this->post('/api/otp/send', ['channel' => 'sms'])->assertRedirect('/consent');
        $this->post('/api/otp/verify', ['code' => '123456'])->assertRedirect('/consent');
    }

    public function test_status_endpoint_never_leaks_the_code(): void
    {
        $this->driveToIntake();
        $this->fakeSms();
        $this->post('/api/otp/send', ['channel' => 'sms']);

        $response = $this->getJson('/api/otp/status');
        $response->assertOk()
            ->assertJson(['active' => true, 'channel' => 'sms', 'verified' => false])
            ->assertJsonMissingPath('code')
            ->assertJsonMissingPath('code_hash');

        $this->assertStringNotContainsString(Otp::first()->code_hash, $response->getContent());
    }

    public function test_unknown_channel_is_rejected(): void
    {
        $this->driveToIntake();
        $this->post('/api/otp/send', ['channel' => 'carrier-pigeon'])
            ->assertSessionHasErrors('channel');
    }
}
