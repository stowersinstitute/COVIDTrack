<?php


namespace App\Util;


class AppConfiguration
{
    public static function isLdapEnabled() : bool
    {
        return isset($_ENV['LDAP_HOST']) && $_ENV['LDAP_HOST'] != '';
    }
}