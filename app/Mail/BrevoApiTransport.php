<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Brevo transactional email over their HTTP API.
 *
 * Written against the API rather than Brevo's SMTP relay because outbound
 * SMTP is frequently blocked on VPS hosts, and this needs no new Composer
 * package (symfony/brevo-mailer would be the alternative).
 *
 * Registered as the "brevo" mail transport in AppServiceProvider.
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $key,
        private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $response = Http::timeout(30)
            ->withHeaders([
                'api-key' => $this->key,
                'accept' => 'application/json',
            ])
            ->post($this->endpoint, $this->payload($email, $envelope));

        if (! $response->successful()) {
            // Throwing TransportException (not a bare exception) matters:
            // callers such as PasswordResetLinkController catch
            // TransportExceptionInterface to show a usable error instead of a 500.
            throw new TransportException(
                'Brevo rejected the message: '.$response->status().' '.$response->body()
            );
        }

        $messageId = $response->json('messageId');

        if ($messageId) {
            $message->setMessageId(is_array($messageId) ? reset($messageId) : $messageId);
        }
    }

    private function payload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'sender' => $this->address($envelope->getSender()),
            'to' => $this->addresses($email->getTo() ?: $envelope->getRecipients()),
            'subject' => $email->getSubject() ?? '',
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['htmlContent'] = is_resource($html) ? stream_get_contents($html) : $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['textContent'] = is_resource($text) ? stream_get_contents($text) : $text;
        }

        // Brevo requires at least one content field.
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = '';
        }

        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $addresses) {
            if ($addresses) {
                $payload[$field] = $this->addresses($addresses);
            }
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = $this->address($replyTo[0]);
        }

        foreach ($email->getAttachments() as $attachment) {
            $payload['attachment'][] = [
                'name' => $attachment->getFilename() ?? 'attachment',
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $payload;
    }

    /** @param  Address[]  $addresses */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $a) => $this->address($a), $addresses);
    }

    private function address(Address $address): array
    {
        $out = ['email' => $address->getAddress()];

        if ($name = $address->getName()) {
            $out['name'] = $name;
        }

        return $out;
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }
}
