<?php

declare(strict_types=1);

namespace Mango\Jose;

use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\InvalidClaimException;
use Jose\Component\Checker\InvalidHeaderException;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JWSCompactSerializer;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Throwable;

use function is_string;

class JWTHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private JWKSLoader $jwksLoader,
        #[Autowire(service: 'jose.header_checker.access_token')]
        private HeaderCheckerManager $signatureHeaderCheckerManager,
        #[Autowire(service: 'jose.claim_checker.access_token')]
        private ClaimCheckerManager $claimCheckerManager,
        #[Autowire(service: 'jose.jws_verifier.access_token')]
        private JWSVerifier $jwsLoader,
        private array $mandatoryClaims = [],
        private ?LoggerInterface $logger = null,
        private string $userIdClaim = 'sub',
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        try {
            $claims = $this->verify($accessToken);
        } catch (InvalidClaimException $e) {
            if ($e->getClaim() === 'exp') {
                $this->logger?->debug('The JWT token is expired.');
            } else {
                $this->logger?->debug('Invalid JWT Token. The following claim was not verified: "{claim}".', [
                    'claim' => $e->getClaim(),
                    'exception' => $e,
                ]);
            }

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        } catch (InvalidHeaderException $e) {
            $this->logger?->debug('Invalid JWT Token. The following header was not verified: "{header}".', [
                'header' => $e->getHeader(),
                'exception' => $e,
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        } catch (Throwable $e) {
            $this->logger?->debug('Invalid JWT Token: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }

        if (empty($claims[$this->userIdClaim])) {
            $this->logger?->debug('Invalid JWT Token: User identifier claim "{claim}" not found.', [
                'claim' => $this->userIdClaim,
            ]);

            throw new BadCredentialsException('Invalid credentials.');
        }

        return new UserBadge($claims[$this->userIdClaim], null, $claims);
    }

    private function verify(string $token): array
    {
        $serializer = new JWSCompactSerializer();

        $jws = $serializer->unserialize($token);
        $this->signatureHeaderCheckerManager->check($jws, 0);

        $signatureKeyset = ($this->jwksLoader)();
        if ($this->jwsLoader->verifyWithKeySet($jws, $signatureKeyset, 0) === false) {
            throw new RuntimeException('Failed to decode the JWT token.');
        }

        $jwt = $jws->getPayload();
        if (! is_string($jwt)) {
            throw new RuntimeException('Failed to decode the JWT token.');
        }

        $payload = JsonConverter::decode($jwt);
        $this->claimCheckerManager->check($payload, $this->mandatoryClaims);

        return $payload;
    }
}
