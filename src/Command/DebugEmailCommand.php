<?php


namespace App\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class DebugEmailCommand extends Command
{
    protected static $defaultName = 'debug:app:email';

    /** @var MailerInterface */
    protected $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->setDescription('Sends a test email')
            ->addArgument('toEmail', InputArgument::REQUIRED, 'Email address to send the test email to')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $to = $input->getArgument('toEmail');
        $from = $_ENV['CT_DEFAULT_FROM_ADDRESS'];

        $output->writeln('To  : ' . $to);
        $output->writeln('From: ' . $from);

        $htmlBody = sprintf(
            "This is a test email sent at %s",
            date('Y-m-d H:i:s')
        );

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('TEST EMAIL: Sent via debug:app:email')
            ->html($htmlBody)
        ;

        $output->writeln('');
        $output->write('Sending...');

        $this->mailer->send($email);
        $output->writeln(' Sent!');
    }
}