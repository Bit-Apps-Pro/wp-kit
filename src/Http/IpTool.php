<?php

/**
 * Provides IP related functionality.
 */

namespace BitApps\WPKit\Http;

use BitApps\WPKit\Http\Detection\ClientIpResolver;
use BitApps\WPKit\Http\Detection\UserAgent;

trait IpTool
{
    /**
     * Provide user details.
     *
     * @return setUserDetail user details array
     */
    public static function getUserDetail()
    {
        return self::setUserDetail();
    }

    /**
     * Provide user IP address.
     *
     * @return ip
     */
    public static function ip()
    {
        return ClientIpResolver::checkIP();
    }

    /**
     * Set proxy addresses or CIDR ranges that may supply X-Forwarded-For.
     *
     * @param array $proxies
     */
    public static function setTrustedProxies(array $proxies)
    {
        ClientIpResolver::setTrustedProxies($proxies);
    }

    public function device()
    {
        return UserAgent::checkDevice();
    }

    public function user()
    {
        if (is_user_logged_in()) {
            return wp_get_current_user();
        }

        return false;
    }

    /**
     * Set user details ip,cdevice, user_id, user's visited page, current mysql formatted time.
     *
     * @return array of user details
     */
    private static function setUserDetail()
    {
        $userDetails['ip']     = ip2long(ClientIpResolver::checkIP());
        $userDetails['device'] = UserAgent::checkDevice();
        $userDetails['id']     = get_current_user_id();
        $userDetails['page']   = \is_object(get_post()) ? get_permalink(get_post()->ID) : null;
        $userDetails['time']   = current_time('mysql');

        return $userDetails;
    }
}
