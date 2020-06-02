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
     * See environment vars CT_DEFAULT_FROM_ADDRESS and CT_DEFAULT_REPLY_TO_ADDRESS
     * to set arguments.
     *
     * @param string $replyToAddress Uses $fromAddress when not given
     */
    public function __construct(string $fromAddress, string $replyToAddress = '')
    {
        $this->fromAddress = $fromAddress;
        $this->replyToAddress = $replyToAddress ?: $fromAddress;
    }

    /**
     * Create an email with an HTML body.
     *
     * @param Address[]|string[]  $toAddresses
     */
    public function createHtml(array $toAddresses, string $subject, string $html): Email
    {
        $email = $this->createBase($toAddresses, $subject);
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

        // All emails sent with a prefix
        $email->subject('[COVIDTrack] ' . $subject);

        return $email;
    }
}
