<?php

namespace Tests\Integration;

use App\Mail\RegistrationVerificationOtpMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationField;
use App\Models\RegistrationFieldResponse;
use App\Models\RegistrationVerificationChallenge;
use App\Models\User;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Opt-in integration test, exclusively against a disposable InnoDB database. */
class RegistrationVerificationConcurrencyTest extends TestCase
{
    public static function challenges(): array
    {
        return ['same challenge retry' => [false], 'independent duplicate submissions' => [true]];
    }

    #[DataProvider('challenges')]
    public function test_simultaneous_finalization_creates_exactly_one_registration(bool $independent): void
    {
        if (getenv('WDEMS_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set WDEMS_CONCURRENCY_TEST=1 with the dedicated MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('wdems_verification_test', config('database.connections.mysql.database'));
        $this->assertSame('testing', app()->environment());
        Artisan::call('migrate:fresh', ['--force' => true]);
        Mail::fake();
        $event = Event::create([
            'created_by' => User::factory()->create(['role' => 'staff'])->id,
            'event_type' => 'community_run', 'event_name' => 'Concurrent Verification',
            'event_date' => now()->addWeek(), 'status' => 'registration_open',
            'registration_slug' => 'concurrent', 'registration_start' => now(),
            'registration_form_saved_at' => now(),
            'registration_common_field_requirements' => ['email' => true, 'contact_number' => false],
        ]);
        $field = RegistrationField::create([
            'event_id' => $event->id, 'label' => 'Size', 'field_type' => 'text',
            'is_required' => true, 'display_order' => 0,
        ]);
        $service = app(RegistrationVerificationService::class);
        $tokens = [];
        for ($i = 0; $i < ($independent ? 2 : 1); $i++) {
            $pending = $service->begin($event, [
                'first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 28,
                'email' => 'concurrency@example.test', 'address' => 'Manila',
                'responses' => [$field->id => 'Medium'],
            ], null);
            $tokens[] = $pending['token'];
            $this->assertNull($service->sendEmail($event, $pending['token']));
            $this->assertNull($service->verify($event, $pending['token'], 'email', Mail::sent(RegistrationVerificationOtpMail::class)->last()->code));
        }
        $tokens[1] ??= $tokens[0];
        $processes = [];
        DB::beginTransaction();
        try {
            // Hold the event row so both subprocesses compete for this lock.
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            foreach ($tokens as $token) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/FinalizeVerification.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
                    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'wdems_verification_test',
                    'DB_SOCKET' => config('database.connections.mysql.unix_socket'),
                    'DB_HOST' => config('database.connections.mysql.host'),
                    'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password') ?? '',
                    'WDEMS_CONCURRENCY_TEST' => '1',
                ]);
                $process->setInput(json_encode(['event_id' => $event->id, 'token' => $token], JSON_THROW_ON_ERROR));
                $process->setTimeout(20)->start();
                $processes[] = $process;
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), 'Worker did not reach finalization.');
            }
            DB::commit();
            $outputs = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'Worker failed without exposing its secret input.');
                $outputs[] = $process->getOutput();
            }
            $this->assertCount($independent ? 1 : 2, array_filter($outputs, fn ($output) => str_contains($output, 'complete')));
            $this->assertSame(1, Participant::count());
            $this->assertSame(1, Registration::count());
            $this->assertSame(1, RegistrationFieldResponse::count());
            $this->assertSame(1, RegistrationVerificationChallenge::whereNotNull('finalized_at')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }
}
