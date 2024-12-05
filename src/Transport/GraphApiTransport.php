<?php

namespace Vitrus\SymfonyOfficeGraphMailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Sjoerd Adema <vitrus@gmail.com>
 */
class GraphApiTransport extends AbstractApiTransport
{
    private string $graphTentantId;
    private string $graphClientId;
    private string $graphClientSecret;
    private ?string $accessToken = null;

    public function __construct(
        string $graphTentantId,
        string $graphClientId,
        string $graphClientSecret,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        $this->graphTentantId = $graphTentantId;
        $this->graphClientId = $graphClientId;
        $this->graphClientSecret = $graphClientSecret;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('microsoft-graph-api://%s:{SECRET}@%s', $this->graphClientId, $this->graphTentantId);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        if (null === $this->accessToken) {
            $this->requestAccessToken();
        }

        $response = $this->client->request('POST', $this->getEndpoint($sentMessage), [
            'json' => $this->normalizeEmail($email, $envelope),
            'auth_bearer' => $this->accessToken,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach Microsoft Graph API.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            throw new HttpTransportException('Unable to sent e-mail using Graph API', $response);
        }

        return $response;
    }

    private function normalizeEmail(Email $email, Envelope $envelope): array
    {
        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'toRecipients' => $this->normalizeAddresses($envelope->getRecipients() ?? $email->getTo()),
                'ccRecipients' => $this->normalizeAddresses($envelope->getRecipients() ? [] : $email->getCc()),
                'bccRecipients' => $this->normalizeAddresses($envelope->getRecipients() ? [] : $email->getBcc()),
                'body' => $this->normalizeBody($email),
                'attachments' => $this->normalizeAttachments($email),
            ],
            'saveToSentItems' => $this->normalizeSaveToSentItems($email),
        ];

        return $payload;
    }

    private function normalizeAddress(Address $address): array
    {
        $addressArray = [
            'emailAddress' => [
                'address' => $address->getAddress(),
            ],
        ];
        if ($address->getName()) {
            $addressArray['emailAddress']['name'] = $address->getName();
        }

        return $addressArray;
    }

    /**
     * @param Address[] $addresses
     */
    private function normalizeAddresses(array $addresses): array
    {
        $addressesArray = [];
        foreach ($addresses as $address) {
            $addressesArray[] = $this->normalizeAddress($address);
        }

        return $addressesArray;
    }

    private function normalizeBody(Email $email): array
    {
        // prefer html body
        if (null !== $htmlContent = $email->getHtmlBody()) {
            return [
                'contentType' => 'html',
                'content' => $htmlContent,
            ];
        }

        // fallback on textBody
        if (null !== $textContent = $email->getTextBody()) {
            return [
                'contentType' => 'text',
                'content' => $textContent,
            ];
        }

        return [];
    }

    private function normalizeAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'contentType' => $headers->get('Content-Type')->getBody(),
                'contentBytes' => base64_encode($attachment->getBody()),
                'name' => $filename,
            ];
        }

        return $attachments;
    }

    private function normalizeSaveToSentItems($email): bool
    {
        $saveToSentItems = true;
        $saveToSentHeader = $email->getHeaders()->get('X-Save-To-Sent-Items');
        if ($saveToSentHeader !== null) {
            if (strtolower($saveToSentHeader->getBodyAsString()) === 'false') {
                $saveToSentItems = false;
            }
        }

        return $saveToSentItems;
    }

    private function requestAccessToken(): void
    {
        $url = 'https://login.microsoftonline.com/' . $this->graphTentantId . '/oauth2/v2.0/token';

        $response = $this->client->request('POST', $url, [
            'body' => [
                'client_id' => $this->graphClientId,
                'client_secret' => $this->graphClientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);

        $token = json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);
        $this->accessToken = $token->access_token;
    }

    private function getEndpoint(SentMessage $sentMessage): string
    {
        $senderAddress = $sentMessage->getEnvelope()->getSender()->getAddress();

        return sprintf('https://graph.microsoft.com/v1.0/users/%s/sendMail', $senderAddress);
    }
}
