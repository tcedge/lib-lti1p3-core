# Library interfaces

> Depending on the top level services you want to use, you have to provide your own implementation of the following interfaces.

## Table of contents

- [Mandatory interfaces](#mandatory-interfaces)
- [Optional interfaces](#optional-interfaces)

## Mandatory interfaces

This section present the mandatory interfaces from the library to be implemented to use provided services.

### Registration repository interface

**Required by**:
- [Message](../../src/Message)
- [Service](../../src/Service)

In order to be able to retrieve your registrations from your configuration storage, you need to provide an implementation of the [RegistrationRepositoryInterface](../../src/Registration/RegistrationRepositoryInterface.php).

By example:
```php
<?php

use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;

$registrationRepository = new class implements RegistrationRepositoryInterface
{
   public function find(string $identifier): ?RegistrationInterface
   {
       // TODO: Implement find() method to find a registration by identifier, or null if not found.
   }

   public function findByPlatformIssuer(string $issuer, string $clientId = null): ?RegistrationInterface
   {
        // TODO: Implement findByPlatformIssuer() method to find a registration by platform issuer, and client id if provided.
   }

   public function findByToolIssuer(string $issuer, string $clientId = null): ?RegistrationInterface
   {
        // TODO: Implement findByToolIssuer() method to find a registration by tool issuer, and client id if provided.
   }
};
```
**Note**: you can find a simple implementation example of this interface in the method `createTestRegistrationRepository()` of the [DomainTestingTrait](../../tests/Traits/DomainTestingTrait.php).

### Nonce repository interface

**Required by**: [Message](../../src/Message) 

In order to be able to store security nonce the way you want, you need to provide an implementation of the [NonceRepositoryInterface](../../src/Security/Nonce/NonceRepositoryInterface.php).

By example:
```php
<?php

use OAT\Library\Lti1p3Core\Security\Nonce\NonceInterface;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceRepositoryInterface;

$nonceRepository = new class implements NonceRepositoryInterface
{
    public function find(string $value) : ?NonceInterface
    {
        // TODO: Implement find() method to find a nonce by value, or null if not found.
    }

    public function save(NonceInterface $nonce) : void
    {
        // TODO: Implement save() method to save a nonce (cache, database, etc)
    }
};
```
**Note**: you can find a simple implementation example of this interface in the method `createTestNonceRepository()` of the [SecurityTestingTrait](../../tests/Traits/SecurityTestingTrait.php).

### User authenticator interface

**Required by**: [Message](../../src/Message)  

During the [OIDC authentication handling](https://www.imsglobal.org/spec/security/v1p0#step-3-authentication-response) on the platform side, you need to define how to delegate the user authentication by providing an implementation of the [UserAuthenticatorInterface](../../src/Security/User/UserAuthenticatorInterface.php).

By example:
```php
<?php

use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticationResultInterface;

$userAuthenticator = new class implements UserAuthenticatorInterface
{
   public function authenticate(string $loginHint): UserAuthenticationResultInterface
   {
       // TODO: Implement authenticate() method to perform user authentication (ex: session, LDAP, etc)
   }
};
```
**Notes**:
- you can find a simple implementation example of this interface in the method `createTestUserAuthenticator()` of the [SecurityTestingTrait](../../tests/Traits/SecurityTestingTrait.php).
- you can find a ready to use `UserAuthenticationResultInterface` implementation is available in [UserAuthenticationResult](../../src/Security/User/UserAuthenticationResult.php)

### Service server interfaces

**Required by**: [Service](../../src/Service)  

The following interfaces must be implemented to use the service authentication server.

- [AccessTokenRepositoryInterface](https://github.com/thephpleague/oauth2-server/blob/master/src/Repositories/AccessTokenRepositoryInterface.php) implementation (to store the created access tokens)
- [ClientRepositoryInterface](https://github.com/thephpleague/oauth2-server/blob/master/src/Repositories/ClientRepositoryInterface.php) implementation (to retrieve your clients)
- [ScopeRepositoryInterface](https://github.com/thephpleague/oauth2-server/blob/master/src/Repositories/ScopeRepositoryInterface.php) implementation (to retrieve your scopes)

## Optional interfaces

This section present the optional interfaces from the library you can implement, but for which a default implementation is already provided.

### JWKS fetcher interface

**Default implementation**: [JwksFetcher](../../src/Security/Jwks/Fetcher/JwksFetcher.php)

In order to be able to fetch public keys JWK from configured [JWKS endpoint](https://auth0.com/docs/tokens/concepts/jwks), you need to provide an implementation of the [JwksFetcherInterface](../../src/Security/Jwks/Fetcher/JwksFetcherInterface.php).

By example:
```php
<?php

use OAT\Library\Lti1p3Core\Security\Jwks\Fetcher\JwksFetcherInterface;
use Lcobucci\JWT\Signer\Key;

$fetcher = new class implements JwksFetcherInterface
{
    public function fetchKey(string $jwksUrl, string $kId) : Key
    {
        // TODO: Implement fetchKey() method to find a Key via an HTTP call to the $jwksUrl, for the kid $kId.
    }
};
```
**Notes**:
- it is recommended to put in cache the JWKS endpoint responses, to improve performances since they dont change often. Your implementation can then rely on an injected PSR6 cache by example.
- you can find a ready to use implementation in [JwksFetcher](../../src/Security/Jwks/Fetcher/JwksFetcher.php): you need to provide it a [guzzle](http://docs.guzzlephp.org/en/stable/) client, with enabled [cache middleware](https://github.com/Kevinrob/guzzle-cache-middleware).

### Service client interface

**Default implementation**: [ServiceClient](../../src/Service/Client/ServiceClient.php) 

In order to send authenticated service calls, an implementation of the [ServiceClientInterface](../../src/Service/Client/ServiceClientInterface.php) can be provided.

By example:
```php
<?php

use OAT\Library\Lti1p3Core\Service\Client\ServiceClientInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;  
use Psr\Http\Message\ResponseInterface;

$client = new class implements ServiceClientInterface
{
    public function request(RegistrationInterface $registration, string $method, string $uri, array $options = [], array $scopes = []) : ResponseInterface
    {
        // TODO: Implement request() method to manage authenticated calls to services.
    }
};
```        
**Notes**:                                                                                                                                                                                                                                                                            
- it is recommended to put in cache the service access tokens, to improve performances. Your implementation can then rely on an injected PSR6 cache by example.                                                                                        