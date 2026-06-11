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

        $raidUrl  = $this->router->generate('app_raid_show', ['id' => $raid->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $guildUrl = $this->router->generate('app_guild_show', ['slug' => $raid->getGuild()->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $siteUrl  = $this->router->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $template = $raid->getRaidTemplate();
        $date     = $raid->getScheduledAt()?->format('d/m/Y à H\hi') ?? 'Non définie';

        $participants = $template->getMinParticipants() === $template->getMaxParticipants()
            ? $template->getMinParticipants() . ' joueurs'
            : $template->getMinParticipants() . ' à ' . $template->getMaxParticipants() . ' joueurs';

        $payload = [
            'embeds' => [[
                'title' => '⚔️ ' . $template->getName(),
                'url'   => $raidUrl,
                'color' => 0x5865F2,
                'description' => implode("\n", [
                    sprintf("Un nouveau raid est ouvert dans la guilde **[%s](%s)** !", $raid->getGuild()->getName(), $guildUrl),
                    '',
                    sprintf("**Organisateur** · %s", $raid->getCreator()->getName()),
                    sprintf("**Date** · %s", $date),
                    sprintf("**Places** · %s", $participants),
                    '',
                    sprintf("[🗡️ Rejoindre le raid](%s)  ·  [🌐 DoRaid](%s)", $raidUrl, $siteUrl),
                ]),
                'footer'    => ['text' => 'DoRaid'],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
            // Silently fail — Discord notification is best-effort
        }
    }
}
