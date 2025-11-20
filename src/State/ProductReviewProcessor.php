<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ProductReview;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ProductReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $em
    ) {
        // Le $persistProcessor sera injecté automatiquement par Symfony
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof ProductReview) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
            if (!$user) {
                throw new \RuntimeException('Vous devez être connecté pour laisser un avis.');
            }

            // Si c'est une création (pas d'ID), assigner l'utilisateur connecté
            if (!$data->getId()) {
                $data->setUser($user);

                // Vérifier si l'utilisateur a déjà laissé un avis pour ce produit
                $existingReview = $this->em->getRepository(ProductReview::class)->findOneBy([
                    'user' => $user,
                    'product' => $data->getProduct()
                ]);

                if ($existingReview) {
                    throw new \RuntimeException('Vous avez déjà laissé un avis pour ce produit.');
                }
            } else {
                // En mode modification, vérifier que l'utilisateur modifie son propre avis
                if ($data->getUser() !== $user && !$this->security->isGranted('ROLE_ADMIN')) {
                    throw new \RuntimeException('Vous ne pouvez modifier que vos propres avis.');
                }
            }
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Mettre à jour les notes du produit et du vendeur
        $this->updateRatings($data);

        return $result;
    }

    private function updateRatings(ProductReview $review): void
    {
        $product = $review->getProduct();
        if (!$product) {
            return;
        }

        // Update Product Rating
        $queryProduct = $this->em->createQuery(
            'SELECT AVG(r.rating) as avgRating, COUNT(r.id) as countRating 
             FROM App\Entity\ProductReview r 
             WHERE r.product = :product'
        )->setParameter('product', $product);
        
        $resultProduct = $queryProduct->getSingleResult();
        $productAverage = (float) ($resultProduct['avgRating'] ?? 0);
        $productCount = (int) ($resultProduct['countRating'] ?? 0);

        $product->setRatingCount($productCount);
        $product->setRatingAverage((string) round($productAverage, 2));
        
        // Update Seller Rating
        $seller = $product->getSeller();
        if ($seller) {
            $querySeller = $this->em->createQuery(
                'SELECT AVG(r.rating) as avgRating, COUNT(r.id) as countRating 
                 FROM App\Entity\ProductReview r 
                 JOIN r.product p 
                 WHERE p.seller = :seller'
            )->setParameter('seller', $seller);
            
            $resultSeller = $querySeller->getSingleResult();
            $sellerAverage = (float) ($resultSeller['avgRating'] ?? 0);
            $sellerCount = (int) ($resultSeller['countRating'] ?? 0);
            
            $seller->setRatingAverage((string) round($sellerAverage, 2));
            $seller->setRatingCount($sellerCount);
        }

        $this->em->flush();
    }
}

