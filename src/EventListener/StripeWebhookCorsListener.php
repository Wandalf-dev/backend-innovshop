<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookCorsListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Priorité 257 pour s'exécuter AVANT le CorsListener (priorité 256)
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 257],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Si c'est le webhook Stripe
        if (str_starts_with($path, '/api/stripe/webhook')) {
            // Pour les requêtes OPTIONS (preflight), on répond directement ET on stoppe
            if ($request->getMethod() === 'OPTIONS') {
                $response = new Response('', Response::HTTP_OK);
                $response->headers->set('Access-Control-Allow-Origin', '*');
                $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', '*');
                $event->setResponse($response);
                $event->stopPropagation(); // Stop seulement pour OPTIONS
            }
            // Pour POST, on ne fait RIEN - laisser la requête passer au routing et au controller
            // Le CorsListener s'exécutera mais ne bloquera pas car pas de preflight nécessaire
        }
    }
}
