<?php

namespace BitApps\WPKit\Tests\Http;

use BitApps\WPKit\Http\Request\Request;
use BitApps\WPKit\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ClientIpResolverTest extends TestCase
{
    public function testClientIPUntrustedClientsCannotSpoofForwardingHeaders(): void
    {
        Request::setTrustedProxies([]);
        $_SERVER['REMOTE_ADDR']          = '198.51.100.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';

        assertSameValue('198.51.100.10', Request::ip(), 'forwarded address replaced the untrusted direct peer');
    }

    public function testClientIPTrustedProxyChainsReturnTheNearestUntrustedClient(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        $_SERVER['REMOTE_ADDR']          = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 10.0.0.1';

        assertSameValue('203.0.113.50', Request::ip(), 'trusted proxy chain resolved the wrong client');
    }

    public function testClientIPSpoofedLeftmostForwardingEntriesAreIgnored(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        $_SERVER['REMOTE_ADDR']          = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.99, 203.0.113.50';

        assertSameValue('203.0.113.50', Request::ip(), 'attacker-controlled leftmost address was trusted');
    }

    public function testClientIPInvalidProxyChainsFallBackToTheDirectPeer(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        $_SERVER['REMOTE_ADDR']          = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.99, invalid';

        assertSameValue('10.0.0.2', Request::ip(), 'invalid chain did not fall back to the direct peer');
    }

    public function testClientIPTrustedPeersWithoutForwardingHeadersUseTheDirectAddress(): void
    {
        Request::setTrustedProxies(['10.0.0.2']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        assertSameValue('10.0.0.2', Request::ip(), 'trusted direct peer was not returned');
    }

    public function testClientIPBracketedIPv6AddressesWithPortsAreNormalized(): void
    {
        $_SERVER['REMOTE_ADDR'] = '[2001:db8::10]:443';

        assertSameValue('2001:db8::10', Request::ip(), 'bracketed IPv6 address was not normalized');
    }

    public function testClientIPIPv4AddressesWithPortsAreNormalized(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.10:8080';

        assertSameValue('198.51.100.10', Request::ip(), 'IPv4 address with port was not normalized');
    }

    public function testClientIPInvalidDirectAddressesAreRejected(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'invalid';

        assertSameValue(false, Request::ip(), 'invalid direct address was accepted');
    }

    public function testClientIPMixedAddressFamiliesDoNotMatchTrustedCIDRs(): void
    {
        Request::setTrustedProxies(['2001:db8::/32']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';

        assertSameValue('198.51.100.10', Request::ip(), 'IPv4 peer matched an IPv6 trusted range');
    }

    public function testClientIPInvalidCIDRPrefixLengthsAreRejected(): void
    {
        Request::setTrustedProxies(['10.0.0.0/999']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';

        assertSameValue('10.0.0.2', Request::ip(), 'peer matched an invalid CIDR prefix');
    }

    public function testClientIPInvalidProxyEntriesDoNotDisableValidRanges(): void
    {
        Request::setTrustedProxies(['invalid', '10.0.0.0/999', '10.0.0.0/8']);
        $_SERVER['REMOTE_ADDR']          = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';

        assertSameValue('203.0.113.10', Request::ip(), 'invalid proxy entries disabled a valid trusted range');
    }

    public function testClientIPPartialByteCIDRMasksMatchValidPeers(): void
    {
        Request::setTrustedProxies(['10.0.0.0/9']);
        $_SERVER['REMOTE_ADDR'] = '10.1.0.2';

        assertSameValue('10.1.0.2', Request::ip(), 'peer did not match a valid partial-byte CIDR');
    }
}
