<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DiscordController extends AbstractController
{
    #[Route('/oauth/discord/login', name: 'app_discord_connect')]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('discord')->redirect(['identify', 'email']);
    }

    #[Route('/oauth/discord/check', name: 'app_discord_check')]
    public function check(): never
    {
        throw new \LogicException('Handled by DiscordAuthenticator.');
    }
}
