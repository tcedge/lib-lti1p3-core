<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\Lti1p3Core\Tests\Integration\Security\Oidc\Endpoint;

use Exception;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Launch\Builder\OidcLaunchRequestBuilder;
use OAT\Library\Lti1p3Core\Launch\Request\OidcLaunchRequest;
use OAT\Library\Lti1p3Core\Security\Oidc\Endpoint\OidcLoginInitiator;
use OAT\Library\Lti1p3Core\Security\Oidc\Request\OidcAuthenticationRequest;
use OAT\Library\Lti1p3Core\Tests\Traits\DomainTestingTrait;
use OAT\Library\Lti1p3Core\Tests\Traits\NetworkTestingTrait;
use PHPUnit\Framework\TestCase;

class OidcLoginInitiatorTest extends TestCase
{
    use DomainTestingTrait;
    use NetworkTestingTrait;

    /** @var OidcLoginInitiator */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new OidcLoginInitiator($this->createTestRegistrationRepository());
    }

    public function testInitiationSuccess(): void
    {
        $resourceLink = $this->createTestResourceLink();
        $registration = $this->createTestRegistration();

        $oidcLaunchRequest = (new OidcLaunchRequestBuilder())->buildResourceLinkOidcLaunchRequest(
            $resourceLink,
            $registration,
            'loginHint'
        );

        $result = $this->subject->initiate($this->createServerRequest('GET', $oidcLaunchRequest->toUrl()));

        $this->assertInstanceOf(OidcAuthenticationRequest::class, $result);

        $this->assertEquals($registration->getPlatform()->getOidcAuthenticationUrl(), $result->getUrl());
        $this->assertEquals($resourceLink->getUrl(), $result->getRedirectUri());
        $this->assertEquals($registration->getClientId(), $result->getClientId());
        $this->assertEquals('loginHint', $result->getLoginHint());

        $this->assertTrue($this->verifyJwt(
            $this->parseJwt($result->getLtiMessageHint()),
            $registration->getPlatformKeyChain()->getPublicKey()
        ));
        $this->assertTrue($this->verifyJwt(
            $this->parseJwt($result->getState()),
            $registration->getToolKeyChain()->getPublicKey()
        ));
    }

    public function testInitiationFailureOnMissingIssuer(): void
    {
        $this->expectException(LtiException::class);
        $this->expectExceptionMessage('Mandatory parameter iss cannot be found');

        $oidcLaunchRequest = new OidcLaunchRequest('invalid');

        $this->subject->initiate($this->createServerRequest('GET', $oidcLaunchRequest->toUrl()));
    }

    public function testInitiationFailureOnNotFoundRegistration(): void
    {
        $this->expectException(LtiException::class);
        $this->expectExceptionMessage('Cannot find registration for OIDC request');

        $oidcLaunchRequest = new OidcLaunchRequest('http://example.com', [
            'iss' => 'invalid',
            'client_id' => 'invalid'
        ]);

        $this->subject->initiate($this->createServerRequest('GET', $oidcLaunchRequest->toUrl()));
    }

    public function testInitiationFailureOnNotFoundDeployment(): void
    {
        $this->expectException(LtiException::class);
        $this->expectExceptionMessage('Cannot find deployment for OIDC request');

        $registration = $this->createTestRegistration();

        $oidcLaunchRequest = new OidcLaunchRequest('http://example.com', [
            'iss' => $registration->getPlatform()->getAudience(),
            'client_id' => $registration->getClientId(),
            'lti_deployment_id' => 'invalid'
        ]);

        $this->subject->initiate($this->createServerRequest('GET', $oidcLaunchRequest->toUrl()));
    }

    public function testInitiationFailureOnGenericError(): void
    {
        $this->expectException(LtiException::class);
        $this->expectExceptionMessage('OIDC login initiation failed: generic error');

        $repositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $repositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->with('invalid', 'invalid')
            ->willThrowException(new Exception('generic error'));

        $subject = new OidcLoginInitiator($repositoryMock);

        $oidcLaunchRequest = new OidcLaunchRequest('invalid', [
            'iss' => 'invalid',
            'client_id' => 'invalid'
        ]);

        $subject->initiate($this->createServerRequest('GET', $oidcLaunchRequest->toUrl()));
    }
}
