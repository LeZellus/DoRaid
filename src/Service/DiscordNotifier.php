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

        $payload = [
            'embeds' => [[
                'title'       => '⚔️ Nouveau raid : ' . $template->getName(),
                'url'         => $raidUrl,
                'description' => sprintf(
                    "Un nouveau raid vient d'être créé sur **[DoRaid](%s)** !\nRejoignez-le dès maintenant en cliquant sur le bouton ci-dessous.",
                    $siteUrl,
                ),
                'color'  => 0x5865F2,
                'fields' => [
                    ['name' => '🏰 Guilde',       'value' => sprintf('[%s](%s)', $raid->getGuild()->getName(), $guildUrl), 'inline' => true],
                    ['name' => '⚔️ Type',          'value' => $template->getName(),     'inline' => true],
                    ['name' => '👤 Créé par',      'value' => $raid->getCreator()->getName(), 'inline' => true],
                    ['name' => '📅 Date',          'value' => $date,                    'inline' => true],
                    ['name' => '👥 Participants',  'value' => $template->getMinParticipants() . ' – ' . $template->getMaxParticipants() . ' joueurs', 'inline' => true],
                ],
                'footer' => ['text' => 'DoRaid • ' . $raid->getGuild()->getName()],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
            'components' => [[
                'type'       => 1,
                'components' => [
                    [
                        'type'  => 2,
                        'style' => 5,
                        'label' => '🗡️ Voir le raid',
                        'url'   => $raidUrl,
                    ],
                    [
                        'type'  => 2,
                        'style' => 5,
                        'label' => '🏰 Page de la guilde',
                        'url'   => $guildUrl,
                    ],
                ],
            ]],
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
            // Silently fail — Discord notification is best-effort
        }
    }
}
