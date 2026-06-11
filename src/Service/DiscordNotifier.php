<?php

namespace App\Service;

use App\Entity\Raid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $router,
    ) {}

    public function notifyRaidCreated(Raid $raid): void
    {
        $webhookUrl = $raid->getGuild()->getDiscordWebhookUrl();
        if (!$webhookUrl) {
            return;
        }

        $raidUrl = $this->router->generate('app_raid_show', ['id' => $raid->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $template = $raid->getRaidTemplate();
        $date = $raid->getScheduledAt()?->format('d/m/Y à H\hi') ?? 'Date non définie';

        $payload = [
            'embeds' => [[
                'title'       => '⚔️ Nouveau raid : ' . $template->getName(),
                'description' => sprintf(
                    "**Guilde :** %s\n**Date :** %s\n**Participants :** %d – %d joueurs\n**Créé par :** %s",
                    $raid->getGuild()->getName(),
                    $date,
                    $template->getMinParticipants(),
                    $template->getMaxParticipants(),
                    $raid->getCreator()->getName(),
                ),
                'color'  => 0x5865F2,
                'footer' => ['text' => 'DoRaid'],
            ]],
            'components' => [[
                'type'       => 1,
                'components' => [[
                    'type'  => 2,
                    'style' => 5,
                    'label' => '🗡️ Rejoindre le raid',
                    'url'   => $raidUrl,
                ]],
            ]],
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
            // Silently fail — Discord notification is best-effort
        }
    }
}
