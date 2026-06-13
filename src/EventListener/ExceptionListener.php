<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ExceptionListener
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        // Ne pas interférer avec les sous-requêtes (ex : rendu du template d'erreur lui-même)
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $request   = $event->getRequest();

        // Ne traiter que les requêtes HTML (pas les appels AJAX/JSON des énigmes etc.)
        if (!$this->isHtmlRequest($request)) {
            return;
        }

        if ($exception instanceof AccessDeniedException) {
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session) {
                $session->getFlashBag()->add('error', 'Vous n\'avez pas accès à cette page.');
            }

            // Redirige vers la page précédente si possible, sinon l'accueil
            $referer = $request->headers->get('referer', '');
            $home    = $this->router->generate('app_home');
            $target  = ($referer && $referer !== $request->getUri()) ? $referer : $home;

            $event->setResponse(new RedirectResponse($target));
            return;
        }

        // Log toutes les erreurs inattendues (pas les 404 ni les erreurs HTTP standard)
        if (!$exception instanceof HttpExceptionInterface) {
            $this->logger->error('[ExceptionListener] Erreur non gérée : {message}', [
                'message'   => $exception->getMessage(),
                'exception' => $exception,
                'url'       => $request->getUri(),
                'user'      => $request->getSession()?->get('_security_main') ? 'connecté' : 'anonyme',
            ]);
        }
    }

    private function isHtmlRequest(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        return !$request->isXmlHttpRequest()
            && str_contains($request->headers->get('Accept', 'text/html'), 'text/html');
    }
}
