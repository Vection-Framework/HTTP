<?php

/**
 * This file is part of the Vection package.
 *
 * (c) David M. Lung <vection@davidlung.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Vection\Component\Http\Server\Factory;

use Vection\Component\Http\Server\UserClient;
use Vection\Component\Http\Server\Environment;
use Vection\Component\Http\Server\Proxy;

/**
 * Class UserClientFactory
 *
 * @package Vection\Component\Http\Server\Factory
 *
 * @author  David M. Lung <vection@davidlung.de>
 */
class UserClientFactory
{
    /**
     * @param Environment $environment
     * @param Proxy|null  $proxy
     *
     * @return UserClient
     */
    public static function create(Environment $environment, ? Proxy $proxy = null): UserClient
    {
        $clientIp      = $environment->getRemoteAddr();
        $requestedHost = $environment->getRemoteAddr();
        $requestedPort = (int) $environment->getRemotePort();

        if ( ! $requestedPort ) {

            [$protocol] = explode('/', $environment->getServerProtocol());

            if ( $protocol === 'HTTP' ) {
                $requestedPort = $environment->get('REQUEST_SCHEME') === 'https'
                || ($environment->has('HTTPS') && $environment->getHttps() !== 'off') ? 443 : 80;
            }
        }

        if ( $proxy ) {
            $clientIp      = $proxy->getClientIP() ?: $clientIp;
            $requestedHost = $proxy->getOriginHost() ?: $requestedHost;
            $requestedPort = $proxy->getOriginPort() ?: $requestedPort;
        }

        return new UserClient($clientIp, $requestedHost, $requestedPort, $environment->getHttpUserAgent());
    }
}
