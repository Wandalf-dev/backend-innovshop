<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em,
        private SluggerInterface $slugger
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Product) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
            // Si c'est une création (pas d'ID) et que l'utilisateur est un vendeur
            if (!$data->getId() && $user && $user->getSeller()) {
                // Assigner automatiquement le vendeur
                $data->setSeller($user->getSeller());
            }
            
            // Générer le slug si pas défini
            if (!$data->getSlug()) {
                $baseSlug = $this->slugger->slug($data->getName())->lower();
                $slug = $baseSlug . '-' . time();
                
                // S'assurer que le slug est unique
                $counter = 1;
                while ($this->em->getRepository(Product::class)->findOneBy(['slug' => $slug])) {
                    $slug = $baseSlug . '-' . time() . '-' . $counter;
                    $counter++;
                }
                
                $data->setSlug($slug);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
