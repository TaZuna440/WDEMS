<?php

use App\Models\Event;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Subprocess fixture. Secrets arrive on stdin, never in command arguments.
require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('WDEMS_CONCURRENCY_TEST') !== '1' || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'wdems_verification_test') {
    exit(2);
}

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$event = Event::findOrFail($input['event_id']);
fwrite(STDOUT, "ready\n");
try {
    app(RegistrationVerificationService::class)->finalize($event, $input['token']);
    fwrite(STDOUT, "complete\n");
} catch (ValidationException) {
    fwrite(STDOUT, "conflict\n");
} catch (Throwable) {
    // Do not print exception SQL/bindings or contact payloads.
    fwrite(STDERR, "Finalization failed.\n");
    exit(1);
}
