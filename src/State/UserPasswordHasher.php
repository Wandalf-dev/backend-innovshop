<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $processor,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        try {
            // S'assurer que les rôles sont valides
            $roles = $data->getRoles();
            if (empty($roles)) {
                // Si aucun rôle n'est fourni, ajouter ROLE_USER par défaut
                $data->setRoles(['ROLE_USER']);
            }

            // Hash le mot de passe s'il est fourni (nouveau ou modifié)
            $plainPassword = $data->getPassword();
            if ($plainPassword && !empty($plainPassword)) {
                // Vérifier si c'est un hash existant ou un nouveau mot de passe
                // Un hash commence généralement par $2y$ (bcrypt)
                if (!str_starts_with($plainPassword, '$2y$')) {
                    $hashedPassword = $this->passwordHasher->hashPassword(
                        $data,
                        $plainPassword
                    );
                    $data->setPassword($hashedPassword);
                }
            }

            return $this->processor->process($data, $operation, $uriVariables, $context);
        } catch (\Exception $e) {
            error_log(sprintf('[UserPasswordHasher] Error processing user: %s', $e->getMessage()));
            error_log(sprintf('[UserPasswordHasher] Stack trace: %s', $e->getTraceAsString()));
            throw $e;
        }
    }
}
