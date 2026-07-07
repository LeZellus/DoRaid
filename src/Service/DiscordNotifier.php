<?php

namespace App\Service;

use App\Entity\GuildMembership;
use App\Entity\Raid;
use App\Entity\RaidParticipant;
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
                'footer'    => ['text' => 'Zeminal'],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
            // Silently fail — Discord notification is best-effort
        }
    }

    public function notifyParticipationReceived(RaidParticipant $participant): void
    {
        $webhookUrl = $participant->getRaid()->getGuild()->getDiscordWebhookUrl();
        if (!$webhookUrl) {
            return;
        }

        $raid    = $participant->getRaid();
        $char    = $participant->getCharacter();
        $raidUrl = $this->router->generate('app_raid_show', ['id' => $raid->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $payload = [
            'embeds' => [[
                'title'       => '📩 Nouvelle candidature',
                'url'         => $raidUrl,
                'color'       => 0xFACC15,
                'description' => implode("\n", [
                    sprintf("**%s** souhaite rejoindre le raid **%s**.", $char->getName(), $raid->getRaidTemplate()->getName()),
                    '',
                    sprintf("**Classe** · %s", $char->getGameClass()->getName()),
                    sprintf("**Niveau** · %s", $char->getLevel()),
                    '',
                    sprintf("[⚔️ Voir la candidature](%s)", $raidUrl),
                ]),
                'footer'    => ['text' => 'Zeminal'],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
        }
    }

    public function notifyParticipationAccepted(RaidParticipant $participant): void
    {
        $webhookUrl = $participant->getRaid()->getGuild()->getDiscordWebhookUrl();
        if (!$webhookUrl) {
            return;
        }

        $raid       = $participant->getRaid();
        $char       = $participant->getCharacter();
        $discordId  = $char->getUser()->getDiscordId();
        $raidUrl    = $this->router->generate('app_raid_show', ['id' => $raid->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $payload = [
            'embeds' => [[
                'title'       => '✅ Participation acceptée',
                'url'         => $raidUrl,
                'color'       => 0x22C55E,
                'description' => implode("\n", [
                    sprintf("**%s** a été accepté(e) dans le raid **%s** !", $char->getName(), $raid->getRaidTemplate()->getName()),
                    '',
                    sprintf("[⚔️ Voir le raid](%s)", $raidUrl),
                ]),
                'footer'    => ['text' => 'Zeminal'],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
        ];

        if ($discordId) {
            $payload['content'] = sprintf('<@%s>', $discordId);
            $payload['allowed_mentions'] = ['users' => [$discordId]];
        }

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
        }
    }

    public function notifyMembershipRequested(GuildMembership $membership): void
    {
        $webhookUrl = $membership->getGuild()->getDiscordWebhookUrl();
        if (!$webhookUrl) {
            return;
        }

        $char     = $membership->getCharacter();
        $guild    = $membership->getGuild();
        $guildUrl = $this->router->generate('app_guild_members', ['slug' => $guild->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        $payload = [
            'embeds' => [[
                'title'       => '🛡️ Demande d\'adhésion',
                'url'         => $guildUrl,
                'color'       => 0x818CF8,
                'description' => implode("\n", [
                    sprintf("**%s** demande à rejoindre la guilde **%s**.", $char->getName(), $guild->getName()),
                    '',
                    sprintf("**Classe** · %s", $char->getGameClass()->getName()),
                    sprintf("**Niveau** · %s", $char->getLevel()),
                    '',
                    sprintf("[👥 Gérer les membres](%s)", $guildUrl),
                ]),
                'footer'    => ['text' => 'Zeminal'],
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]],
        ];

        try {
            $this->httpClient->request('POST', $webhookUrl, ['json' => $payload]);
        } catch (\Throwable) {
        }
    }
}
