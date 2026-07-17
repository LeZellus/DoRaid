<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_discord_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var DiscordResourceOwner $discordUser */
                $discordUser = $client->fetchUserFromToken($accessToken);

                $discordId = $discordUser->getId();
                $email = $discordUser->getEmail();

                $user = $this->em->getRepository(User::class)->findOneBy(['discordId' => $discordId]);

                if ($user) {
                    return $user;
                }

                // Link to existing account with same email
                if ($email) {
                    $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($user) {
                        $user->setDiscordId($discordId);
                        $this->em->flush();
                        return $user;
                    }
                }

                if (!$email) {
                    throw new AuthenticationException('Votre compte Discord n\'a pas d\'adresse email vérifiée. Activez-en une sur Discord puis réessayez.');
                }

                $user = new User();
                $user->setDiscordId($discordId);
                $user->setUsername($discordUser->getUsername());
                $user->setEmail($email);
                $this->em->persist($user);
                $this->em->flush();

                return $user;
            }),
            [new RememberMeBadge()]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $raw = strtr($exception->getMessageKey(), $exception->getMessageData());

        $message = str_contains(strtolower($raw), 'rate')
            ? 'Discord a temporairement bloqué les connexions (trop de tentatives). Patiente 1-2 minutes puis réessaie.'
            : 'Connexion Discord échouée : ' . $raw;

        $request->getSession()->getFlashBag()->add('error', $message);
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
