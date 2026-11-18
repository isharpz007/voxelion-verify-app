<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_journey_emits_expected_audit_events(): void
    {
        Storage::fake('media');
        $session = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->put('/intake', [
            'full_name' => 'Audit Tester',
            'email' => 'a@example.com',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ]);
        $this->verifyOtp();
        $this->post('/photo', ['photo' => $this->noiseImage()]);
        $this->postJson('/live/challenge');
        $this->post('/live/complete', ['capture' => $this->noiseImage()]);

        foreach ([
            'session.created', 'consent.recorded', 'intake.submitted', 'otp.sent', 'otp.verified',
            'photo.accepted', 'liveness.challenge_issued', 'liveness.completed',
        ] as $action) {
            $this->assertDatabaseHas('audit_events', [
                'action' => $action,
                'entity_type' => 'verification_session',
                'entity_id' => $session->id,
            ]);
        }
    }

    public function test_consent_decline_and_withdrawal_audited(): void
    {
        // Decline
        $s1 = $this->beginSession();
        $this->post('/consent/decline');
        $this->assertDatabaseHas('audit_events', [
            'action' => 'consent.declined',
            'entity_id' => $s1->id,
        ]);

        // Withdraw mid-journey (new session = new cookie state not needed;
        // beginSession rewrites the session id each time).
        $s2 = $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->post('/withdraw');
        $this->assertDatabaseHas('audit_events', [
            'action' => 'consent.withdrawn',
            'entity_id' => $s2->id,
        ]);
    }

    public function test_audit_events_do_not_store_pii(): void
    {
        Storage::fake('media');
        $this->beginSession();
        $this->post('/consent', ['agree_processing' => 'yes', 'agree_biometrics' => 'yes']);
        $this->put('/intake', [
            'full_name' => 'Pii Checkperson',
            'email' => 'pii@example.com',
            'phone_country_code' => '+234',
            'phone' => '8012345678',
        ]);

        $blobs = AuditEvent::all()->map(fn ($e) => json_encode($e->metadata_json))->implode(' ');
        $this->assertStringNotContainsString('Pii Checkperson', $blobs);
        $this->assertStringNotContainsString('pii@example.com', $blobs);
    }
}
