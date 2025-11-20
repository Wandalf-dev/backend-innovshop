<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserEmailChangeListener
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        // Vérifier si l'email a changé
        if ($args->hasChangedField('email')) {
            $currentUser = $this->security->getUser();
            
            // Si c'est l'utilisateur connecté qui modifie son propre email
            if ($currentUser instanceof User && $currentUser->getEmail() === $args->getOldValue('email')) {
                // Marquer dans la session qu'il faut se déconnecter
                $session = $this->requestStack->getSession();
                $session->set('logout_after_email_change', true);
            }
        }
    }
}
