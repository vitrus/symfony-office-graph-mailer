<?php

namespace Vitrus\SymfonyOfficeGraphMailer\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Sjoerd Adema <vitrus@gmail.com>
 */
class GraphApiTransportFactory extends AbstractTransportFactory implements TransportFactoryInterface
{
    private const EXPECTED_SCHEME = 'microsoft-graph-api';

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        if ($scheme !== self::EXPECTED_SCHEME) {
            throw new UnsupportedSchemeException($dsn, 'Microsoft Office365 Graph API', $this->getSupportedSchemes());
        }

        return new GraphApiTransport($dsn->getHost(), $dsn->getUser(), $dsn->getPassword(), $this->client, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return [self::EXPECTED_SCHEME];
    }
}
