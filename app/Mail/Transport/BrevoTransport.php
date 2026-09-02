<?php

namespace App\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly string $apiKey,
        private Client $client,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     *
     * @throws TransportException
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $envelope = $message->getEnvelope();

        $payload = array_filter([
            'sender' => $this->address($envelope->getSender()),
            'to' => $this->addresses($this->getRecipients($email, $envelope)),
            'cc' => $email->getCc() ? $this->addresses($email->getCc()) : null,
            'bcc' => $email->getBcc() ? $this->addresses($email->getBcc()) : null,
            'replyTo' => ($replyTo = $email->getReplyTo())
                ? $this->address($replyTo[0])
                : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
            'attachment' => $this->attachments($email),
        ], fn (mixed $value) => $value !== null);

        try {
            $response = $this->client->request('POST', self::ENDPOINT, [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => $this->apiKey,
                ],
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (\Throwable $error) {
            throw new TransportException(
                sprintf('Request to Brevo API failed: %s.', $error->getMessage()),
                is_int($error->getCode()) ? $error->getCode() : 0,
                $error
            );
        }

        $body = $this->decode($response);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new TransportException(sprintf(
                'Brevo API error [%d]: %s',
                $response->getStatusCode(),
                $body['message'] ?? 'Unknown error.'
            ), $response->getStatusCode());
        }

        $email->getHeaders()->addTextHeader('X-Brevo-Message-ID', $body['messageId'] ?? '');
    }

    /**
     * Get the recipients without CC or BCC.
     *
     * @return array<int, Address>
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        return array_filter($envelope->getRecipients(), function (Address $address) use ($email) {
            return in_array($address, array_merge($email->getCc(), $email->getBcc()), true) === false;
        });
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{email: string, name: string}>
     */
    protected function addresses(array $addresses): array
    {
        $mapped = array_map(fn (Address $address) => $this->address($address), array_values($addresses));

        return array_values(array_filter($mapped, fn (array $address) => $address['email'] !== null));
    }

    /**
     * @return array{email: string, name: string}
     */
    protected function address(Address $address): array
    {
        return [
            'email' => $address->getAddress(),
            'name' => $address->getName() ?? explode('@', $address->getAddress())[0],
        ];
    }

    /**
     * @return array<int, array{content: string, name: string}>|null
     */
    protected function attachments(Email $email): ?array
    {
        if (! $email->getAttachments()) {
            return null;
        }

        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $attachments[] = [
                'content' => base64_encode($attachment->bodyToString()),
                'name' => $headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment',
            ];
        }

        return $attachments;
    }

    protected function decode(Response $response): array
    {
        try {
            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
