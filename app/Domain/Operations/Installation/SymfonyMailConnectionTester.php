<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\MailConnectionTester;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

final class SymfonyMailConnectionTester implements MailConnectionTester
{
    public function test(MailConfiguration $configuration): MailConnectionResult
    {
        try {
            $credentials = $configuration->username === null
                ? ''
                : rawurlencode($configuration->username).':'.rawurlencode((string) $configuration->password).'@';
            $query = $configuration->encryption === null ? '' : '?encryption='.$configuration->encryption;
            $transport = Transport::fromDsn(sprintf(
                'smtp://%s%s:%d%s',
                $credentials,
                $configuration->host,
                $configuration->port,
                $query,
            ));
            $transport->send((new Email)
                ->from($configuration->fromAddress)
                ->to($configuration->testAddress)
                ->subject('Waymark Community email test')
                ->text('Email delivery is working. You may continue setting up Waymark Community.'));

            return new MailConnectionResult(true, 'Test message sent.');
        } catch (Throwable) {
            return new MailConnectionResult(false, 'Waymark could not send the test message using those email settings.');
        }
    }
}
