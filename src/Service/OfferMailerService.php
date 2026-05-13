<?php

namespace App\Service;

use App\Entity\Offer;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class OfferMailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom = 'no-reply@orion.com'
    ) {
    }

    public function sendNewOfferEmail(Offer $offer): void
    {
        $client = $offer->getServiceRequest()->getClient();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Orion Notifications'))
            ->to(new Address($client->getEmail(), $client->getFullName()))
            ->subject('New Offer Received: ' . $offer->getServiceRequest()->getTitle())
            ->htmlTemplate('emails/offer_new.html.twig')
            ->context([
                'offer' => $offer,
                'client' => $client,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Offer notification email failed (new offer).', [
                'offer_id' => $offer->getId(),
                'exception' => $e,
            ]);
        }
    }

    public function sendOfferStatusEmail(Offer $offer): void
    {
        $worker = $offer->getWorker();
        $status = $offer->getStatus();

        $subject = match ($status) {
            'ACCEPTED' => 'Offer Accepted: ' . $offer->getServiceRequest()->getTitle(),
            'REJECTED' => 'Offer Update: ' . $offer->getServiceRequest()->getTitle(),
            'NEGOTIATING' => 'New Negotiation: ' . $offer->getServiceRequest()->getTitle(),
            default => 'Offer Status Update: ' . $offer->getServiceRequest()->getTitle(),
        };

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Orion Notifications'))
            ->to(new Address($worker->getEmail(), $worker->getFullName()))
            ->subject($subject)
            ->htmlTemplate('emails/offer_status.html.twig')
            ->context([
                'offer' => $offer,
                'worker' => $worker,
                'status' => $status,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Offer notification email failed (status update).', [
                'offer_id' => $offer->getId(),
                'exception' => $e,
            ]);
        }
    }
}
