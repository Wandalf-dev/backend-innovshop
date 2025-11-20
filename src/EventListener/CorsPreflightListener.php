<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

class CorsPreflightListener implements EventSubscriberInterface
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://localhost:5174'
    ];

    public static function getSubscribedEvents(): array
    {
        // Priorité très élevée (512) pour s'exécuter AVANT tout autre listener
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Si c'est une requête OPTIONS (preflight) pour l'API
        if ($request->getMethod() === 'OPTIONS' && str_starts_with($path, '/api/')) {
            // Ignorer le webhook Stripe qui a son propre handler
            if (str_starts_with($path, '/api/stripe/webhook')) {
                return;
            }

            // Répondre directement aux requêtes OPTIONS avec les headers CORS appropriés
            $origin = $request->headers->get('Origin');
            $allowedOrigin = $this->getAllowedOrigin($origin);
            
            $response = new Response('', Response::HTTP_NO_CONTENT);
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '3600');
            
            $event->setResponse($response);
            $event->stopPropagation();
            return;
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Ajouter les headers CORS à toutes les réponses API
        if (str_starts_with($path, '/api/')) {
            // Ignorer le webhook Stripe qui a son propre handler
            if (str_starts_with($path, '/api/stripe/webhook')) {
                return;
            }

            $response = $event->getResponse();
            $origin = $request->headers->get('Origin');
            $allowedOrigin = $this->getAllowedOrigin($origin);

            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', '*');
        }
    }

    private function getAllowedOrigin(?string $origin): string
    {
        if ($origin) {
            foreach (self::ALLOWED_ORIGINS as $allowed) {
                if ($origin === $allowed) {
                    return $origin;
                }
            }
        }
        
        // Par défaut, utiliser la première origine autorisée pour le développement
        return self::ALLOWED_ORIGINS[0];
    }
}

