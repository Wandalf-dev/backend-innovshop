<?php

namespace App\EventListener;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class UniqueConstraintExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        // Vérifier si c'est une exception de contrainte unique
        if ($exception instanceof UniqueConstraintViolationException) {
            $message = $exception->getMessage();
            
            // Détecter les erreurs d'email dupliqué
            if (str_contains($message, 'UNIQ_8D93D649E7927C74') || 
                (str_contains($message, 'Duplicate entry') && (str_contains($message, 'email') || str_contains($message, 'E7927C74')))) {
                
                $response = new JsonResponse([
                    'error' => 'Cet email est déjà utilisé. Veuillez utiliser un autre email ou vous connecter.',
                    'code' => 409,
                    'type' => 'UniqueConstraintViolation',
                    'detail' => 'Cet email est déjà utilisé. Veuillez utiliser un autre email ou vous connecter.'
                ], 409);
                
                $event->setResponse($response);
                $event->stopPropagation();
                return;
            }
            
            // Détecter d'autres contraintes uniques (slug, etc.)
            if (str_contains($message, 'Duplicate entry')) {
                $response = new JsonResponse([
                    'error' => 'Cette valeur existe déjà. Veuillez en choisir une autre.',
                    'code' => 409,
                    'type' => 'UniqueConstraintViolation',
                    'detail' => 'Cette valeur existe déjà. Veuillez en choisir une autre.'
                ], 409);
                
                $event->setResponse($response);
                $event->stopPropagation();
                return;
            }
        }
        
        // Vérifier si l'exception contient une contrainte unique dans son message
        $message = $exception->getMessage();
        if (str_contains($message, 'UNIQ_8D93D649E7927C74') || 
            (str_contains($message, 'Duplicate entry') && (str_contains($message, 'email') || str_contains($message, 'E7927C74')))) {
            
            $response = new JsonResponse([
                'error' => 'Cet email est déjà utilisé. Veuillez utiliser un autre email ou vous connecter.',
                'code' => 409,
                'type' => 'UniqueConstraintViolation',
                'detail' => 'Cet email est déjà utilisé. Veuillez utiliser un autre email ou vous connecter.'
            ], 409);
            
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}

