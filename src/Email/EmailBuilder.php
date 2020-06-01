<?php

namespace App\Email;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailBuilder
{
    /**
     * @param Address[]|string[]  $toAddresses
     */
    public static function createHtml(array $toAddresses, string $subject, string $html): Email
    {
        $email = static::createBase($toAddresses, $subject);
        $email->html($html);

        return $email;
    }

    /**
     * @param Address[]|string[]  $toAddresses
     */
    public static function createText(array $toAddresses, string $subject, string $text): Email
    {
        $email = static::createBase($toAddresses, $subject);
        $email->text($text);

        return $email;
    }

    /**
     * @param Address[]|string[]  $toAddresses
     */
    private static function createBase(array $toAddresses, string $subject): Email
    {
        $email = new Email();

        // From sourced from environment variables
        $from = $_ENV['CT_DEFAULT_FROM_ADDRESS'];
        $email->from($from);

        // Reply-To sourced from environment variables
        $replyTo = $_ENV['CT_DEFAULT_REPLY_TO_ADDRESS'];
        if ($replyTo) {
            $email->replyTo($replyTo);
        }

        $email->to(...$toAddresses); // to(...[addr1, addr2]) ==> to(addr1, addr2)

        // All emails sent with a prefix
        $email->subject('[COVIDTrack] ' . $subject);

        return $email;
    }
}
