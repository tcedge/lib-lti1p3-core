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

namespace OAT\Library\Lti1p3Core\Message\Launch\Validator;

use Carbon\Carbon;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Result\LaunchValidationResult;
use OAT\Library\Lti1p3Core\Message\LtiMessage;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayload;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Security\Nonce\Nonce;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceGeneratorInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * @see https://www.imsglobal.org/spec/security/v1p0/#authentication-response-validation-0
 */
class PlatformLaunchValidator extends AbstractLaunchValidator
{
    public function getSupportedMessageTypes(): array
    {
        return [
            LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_RESPONSE,
            LtiMessageInterface::LTI_MESSAGE_TYPE_START_ASSESSMENT,
        ];
    }

    public function validateToolOriginatingLaunch(ServerRequestInterface $request): LaunchValidationResult
    {
        $this->reset();

        try {
            $message = LtiMessage::fromServerRequest($request);

            $payload = new LtiMessagePayload($this->parser->parse($message->getMandatoryParameter('JWT')));

            $registration = $this->registrationRepository->findByPlatformIssuer(
                $payload->getMandatoryClaim(MessagePayloadInterface::CLAIM_AUD),
                $payload->getMandatoryClaim(MessagePayloadInterface::CLAIM_ISS)
            );

            if (null === $registration) {
                throw new LtiException('No matching registration found platform side');
            }

            $this
                ->validatePayloadExpiry($payload)
                ->validatePayloadKid($payload)
                ->validatePayloadVersion($payload)
                ->validatePayloadMessageType($payload)
                ->validatePayloadSignature($registration, $payload)
                ->validatePayloadNonce($payload)
                ->validatePayloadDeploymentId($registration, $payload)
                ->validatePayloadLaunchMessageTypeSpecifics($payload);

            return new LaunchValidationResult($registration, $payload, null, $this->successes);

        } catch (Throwable $exception) {
            return new LaunchValidationResult(null, null, null, $this->successes, $exception->getMessage());
        }
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadKid(LtiMessagePayloadInterface $payload): self
    {
        if (!$payload->getToken()->hasHeader(LtiMessagePayloadInterface::HEADER_KID)) {
            throw new LtiException('JWT kid header is missing');
        }

        return $this->addSuccess('JWT kid header is provided');
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadVersion(LtiMessagePayloadInterface $payload): self
    {
        if ($payload->getVersion() !== LtiMessageInterface::LTI_VERSION) {
            throw new LtiException('JWT version claim is invalid');
        }

        return $this->addSuccess('JWT version claim is valid');
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadMessageType(LtiMessagePayloadInterface $payload): self
    {
        if ($payload->getMessageType() === '') {
            throw new LtiException('JWT id_token message_type claim is missing');
        }

        if (!in_array($payload->getMessageType(), $this->getSupportedMessageTypes())) {
            throw new LtiException(
                sprintf('JWT id_token message_type claim %s is not supported', $payload->getMessageType())
            );
        }

        return $this->addSuccess('JWT id_token message_type claim is valid');
    }


    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadSignature(RegistrationInterface $registration, LtiMessagePayloadInterface $payload): self
    {
        if (null === $registration->getToolKeyChain()) {
            $key = $this->fetcher->fetchKey(
                $registration->getToolJwksUrl(),
                $payload->getToken()->getHeader(LtiMessagePayloadInterface::HEADER_KID)
            );
        } else {
            $key = $registration->getToolKeyChain()->getPublicKey();
        }

        if (!$payload->getToken()->verify($this->signer, $key)) {
            throw new LtiException('JWT signature validation failure');
        }

        return $this->addSuccess('JWT signature validation success');
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadExpiry(LtiMessagePayloadInterface $payload): self
    {
        if ($payload->getToken()->isExpired()) {
            throw new LtiException('JWT is expired');
        }

        return $this->addSuccess('JWT is not expired');
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadNonce(LtiMessagePayloadInterface $payload): self
    {
        $nonceValue = $payload->getMandatoryClaim(MessagePayloadInterface::CLAIM_NONCE);

        $nonce = $this->nonceRepository->find($nonceValue);

        if (null !== $nonce) {
            if (!$nonce->isExpired()) {
                throw new LtiException('JWT nonce claim already used');
            }

            return $this->addSuccess('JWT nonce claim already used but expired');
        } else {
            $this->nonceRepository->save(
                new Nonce($nonceValue, Carbon::now()->addSeconds(NonceGeneratorInterface::TTL))
            );

            return $this->addSuccess('JWT nonce claim is valid');
        }
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadDeploymentId(RegistrationInterface $registration, LtiMessagePayloadInterface $payload): self
    {
        if (!$registration->hasDeploymentId($payload->getDeploymentId())) {
            throw new LtiException('JWT deployment_id claim not valid for this registration');
        }

        return $this->addSuccess('JWT deployment_id claim valid for this registration');
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function validatePayloadLaunchMessageTypeSpecifics(LtiMessagePayloadInterface $payload): self
    {
        switch ($payload->getMessageType()) {
            case LtiMessageInterface::LTI_MESSAGE_TYPE_START_ASSESSMENT:
                if (null === $payload->getProctoringSessionData()) {
                    throw new LtiException('JWT session_data proctoring claim is invalid');
                }

                $this->addSuccess('JWT session_data proctoring claim is valid');

                if (null === $payload->getProctoringAttemptNumber()) {
                    throw new LtiException('JWT attempt_number proctoring claim is invalid');
                }

                $this->addSuccess('JWT attempt_number proctoring claim is valid');

                if (null === $payload->getResourceLink()) {
                    throw new LtiException('JWT resource_link claim is invalid');
                }

                return $this->addSuccess('JWT resource_link claim is valid');

            default:
                throw new LtiException(sprintf('Launch message type %s not handled', $payload->getMessageType()));
        }
    }
}
