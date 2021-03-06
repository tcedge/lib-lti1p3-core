<?php

/**
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; under version 2
 *  of the License (non-upgradable).
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *  Copyright (c) 2020 (original work) Open Assessment Technologies S.A.
 */

declare(strict_types=1);

namespace OAT\Library\Lti1p3Core\Security\Oidc\Endpoint;

use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Launch\Request\OidcLaunchRequest;
use OAT\Library\Lti1p3Core\Message\Builder\MessageBuilder;
use OAT\Library\Lti1p3Core\Message\MessageInterface;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceGenerator;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceGeneratorInterface;
use OAT\Library\Lti1p3Core\Security\Oidc\Request\OidcAuthenticationRequest;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * @see https://www.imsglobal.org/spec/security/v1p0/#step-2-authentication-request
 */
class OidcLoginInitiator
{
    /** @var RegistrationRepositoryInterface */
    private $repository;

    /** @var NonceGeneratorInterface */
    private $generator;

    /** @var MessageBuilder */
    private $builder;

    public function __construct(
        RegistrationRepositoryInterface $repository,
        NonceGeneratorInterface $generator = null,
        MessageBuilder $builder = null
    ) {
        $this->repository = $repository;
        $this->generator = $generator ?? new NonceGenerator();
        $this->builder = $builder ?? new MessageBuilder();
    }

    /**
     * @throws LtiException
     */
    public function initiate(ServerRequestInterface $request): OidcAuthenticationRequest
    {
        try {
            /** @var OidcLaunchRequest $oidcRequest */
            $oidcRequest = OidcLaunchRequest::fromServerRequest($request);

            $registration = $registration = $this->repository->findByPlatformIssuer(
                $oidcRequest->getIssuer(),
                $oidcRequest->getClientId()
            );

            if (null === $registration) {
                throw new LtiException('Cannot find registration for OIDC request');
            }

            if (null !== $oidcRequest->getLtiDeploymentId()) {
                if (!$registration->hasDeploymentId($oidcRequest->getLtiDeploymentId())) {
                    throw new LtiException('Cannot find deployment for OIDC request');
                }
            }

            $nonce = $this->generator->generate();

            $this->builder
                ->withClaim(MessageInterface::CLAIM_SUB, $registration->getIdentifier())
                ->withClaim(MessageInterface::CLAIM_ISS, $registration->getTool()->getAudience())
                ->withClaim(MessageInterface::CLAIM_AUD, $registration->getPlatform()->getAudience())
                ->withClaim(MessageInterface::CLAIM_NONCE, $nonce->getValue())
                ->withClaim(MessageInterface::CLAIM_PARAMETERS, $oidcRequest->getParameters());

            return new OidcAuthenticationRequest($registration->getPlatform()->getOidcAuthenticationUrl(), [
                'redirect_uri' => $oidcRequest->getTargetLinkUri(),
                'client_id' => $registration->getClientId(),
                'login_hint' => $oidcRequest->getLoginHint(),
                'nonce' => $nonce->getValue(),
                'state' => $this->builder->getMessage($registration->getToolKeyChain())->getToken()->__toString(),
                'lti_message_hint' => $oidcRequest->getLtiMessageHint()
            ]);

        } catch (LtiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LtiException(
                sprintf('OIDC login initiation failed: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }
}
