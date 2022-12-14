<?php

namespace App\MessageHandler\Email;

use App\Message\Email\SendEmailMessages;
use App\Trait\Email\EmailPreparation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

#[AsMessageHandler]
final class SendEmailMessageHandler implements MessageHandlerInterface
{
    use EmailPreparation;
    private $mailerService;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailerService = $mailer;
    }

    public function __invoke(SendEmailMessages $message)
    {
        $email = $this->emailPreparation(new TemplatedEmail(), $message);
        $this->mailerService->send($email);
    }
}
