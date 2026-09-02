<?php

use App\Mail\Transport\BrevoTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    Mail::forgetMailers();
});

it('resolves the brevo transport via mailer', function () {
    config([
        'mail.mailers.brevo' => [
            'transport' => 'brevo',
            'key' => 'test-api-key',
        ],
    ]);

    expect(Mail::mailer('brevo')->getSymfonyTransport())
        ->toBeInstanceOf(BrevoTransport::class);
});

it('sends email via brevo api', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')->once()->with(
        'POST',
        'https://api.brevo.com/v3/smtp/email',
        Mockery::on(function (array $options) {
            expect($options['headers']['api-key'])->toBe('test-key')
                ->and($options['json']['sender']['email'])->toBe('from@test.com')
                ->and($options['json']['to'][0]['email'])->toBe('to@test.com')
                ->and($options['json']['subject'])->toBe('Test Email')
                ->and($options['json']['textContent'])->toBe('Hello world');

            return true;
        })
    )->andReturn(new Response(200, [], json_encode(['messageId' => 'msg-xyz'])));

    $transport = new BrevoTransport('test-key', $client);

    $email = (new Email)
        ->from(new Address('from@test.com', 'Sender'))
        ->to(new Address('to@test.com', 'Recipient'))
        ->subject('Test Email')
        ->text('Hello world');

    $envelope = new Envelope(
        new Address('from@test.com', 'Sender'),
        [new Address('to@test.com', 'Recipient')]
    );

    $sent = $transport->send($email, $envelope);

    expect($sent)->not->toBeNull();
    expect($sent->getOriginalMessage()->getHeaders()->get('X-Brevo-Message-ID')->getBody())
        ->toBe('msg-xyz');
});

it('always sends a non-empty recipient name to brevo', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')->once()->with(
        'POST',
        'https://api.brevo.com/v3/smtp/email',
        Mockery::on(function (array $options) {
            expect($options['json']['to'][0]['name'])->not->toBe('')
                ->and($options['json']['to'][0]['name'])->not->toBeNull()
                ->and($options['json']['to'][0]['name'])->toBe('to');

            return true;
        })
    )->andReturn(new Response(200, [], json_encode(['messageId' => 'msg-xyz'])));

    $transport = new BrevoTransport('test-key', $client);

    $email = (new Email)
        ->from(new Address('from@test.com'))
        ->to(new Address('to@test.com'))
        ->subject('Test')
        ->text('test');

    $transport->send($email, new Envelope(
        new Address('from@test.com'),
        [new Address('to@test.com')]
    ));
});

it('throws transport exception on api error', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')->once()->andReturn(
        new Response(401, [], json_encode(['message' => 'Invalid API key']))
    );

    $transport = new BrevoTransport('bad-key', $client);

    $email = (new Email)
        ->from(new Address('from@test.com'))
        ->to(new Address('to@test.com'))
        ->subject('Fail')
        ->text('test');

    $transport->send($email, new Envelope(
        new Address('from@test.com'),
        [new Address('to@test.com')]
    ));
})->throws(TransportException::class, 'Brevo API error [401]');

it('handles http client exceptions as transport exceptions', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('request')->once()->andThrow(
        new ConnectException(
            'Connection timed out',
            new Request('POST', '/')
        )
    );

    $transport = new BrevoTransport('key', $client);

    $email = (new Email)
        ->from(new Address('from@test.com'))
        ->to(new Address('to@test.com'))
        ->subject('Timeout')
        ->text('test');

    $transport->send($email, new Envelope(
        new Address('from@test.com'),
        [new Address('to@test.com')]
    ));
})->throws(TransportException::class, 'Request to Brevo API failed');
