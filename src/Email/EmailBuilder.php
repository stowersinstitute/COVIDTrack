<?php

namespace App\Email;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Creates Email objects compatible with Symfony Notifier.
 */
class EmailBuilder
{
    /**
     * Emails built by this class will appear with "From:" this email address.
     *
     * @var string
     */
    private $fromAddress;

    /**
     * Emails built by this class will appear with "Reply-To:" this email address.
     *
     * @var string
     */
    private $replyToAddress;

    /**
     * Whether emails are being built in a test environment, which will add
     * text to the Subject and Body that indicate this is a test.
     *
     * @var bool
     */
    private $sendTestEmails;

    /**
     * See environment vars CT_DEFAULT_FROM_ADDRESS and CT_DEFAULT_REPLY_TO_ADDRESS
     * to set arguments.
     *
     * @param string $replyToAddress
     */
    public function __construct(string $fromAddress, string $replyToAddress, bool $sendTestEmails = false)
    {
        $this->fromAddress = $fromAddress;
        $this->replyToAddress = $replyToAddress;
        $this->sendTestEmails = $sendTestEmails;
    }

    /**
     * Create an email with an HTML body.
     *
     * @param Address[]|string[]  $toAddresses
     */
    public function createHtml(array $toAddresses, string $subject, string $html): Email
    {
        $email = $this->createBase($toAddresses, $subject);

        if ($this->sendTestEmails) {
            $html = "<p>This is a test email</p>\n" . $html;
        }

        $email->html($html);

        return $email;
    }

    /**
     * Create an email with a plain-text body.
     *
     * @param Address[]|string[]  $toAddresses
     */
    public function createText(array $toAddresses, string $subject, string $text): Email
    {
        $email = $this->createBase($toAddresses, $subject);

        if ($this->sendTestEmails) {
            $text = "This is a test email\n\n" . $text;
        }

        $email->text($text);

        return $email;
    }

    /**
     * Common logic for all created emails.
     *
     * @param Address[]|string[]  $toAddresses
     */
    private function createBase(array $toAddresses, string $subject): Email
    {
        $email = new Email();

        $email->from($this->fromAddress);
        $email->replyTo($this->replyToAddress);
        $email->to(...$toAddresses); // to(...[addr1, addr2]) ==> to(addr1, addr2)

        // Subject parts are space-delimited
        // "[TEST] [COVIDTrack] These groups require testing"
        $subjectParts = [];
        if ($this->sendTestEmails) {
            $subjectParts[] = '[TEST]';
        }
        $subjectParts[] = '[COVIDTrack]';
        $subjectParts[] = $subject;

        $email->subject(implode(' ', $subjectParts));

        return $email;
    }
}
