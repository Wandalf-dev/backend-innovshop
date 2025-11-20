<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Cart;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EmailController extends AbstractController
{
    private EmailService $emailService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EmailService $emailService,
        EntityManagerInterface $entityManager
    ) {
        $this->emailService = $emailService;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/orders/{reference}/send-confirmation-email', name: 'send_order_confirmation_email', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sendOrderConfirmationEmail(string $reference): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur est connecté
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Non authentifié'], 401);
            }

            $order = $this->entityManager->getRepository(Order::class)
                ->findOneBy(['reference' => $reference]);

            if (!$order) {
                return new JsonResponse(['error' => 'Commande non trouvée'], 404);
            }

            // Vérifier que l'utilisateur est bien le propriétaire de la commande
            if ($order->getUser() !== $user) {
                return new JsonResponse(['error' => 'Accès non autorisé à cette commande'], 403);
            }

            $result = $this->emailService->sendOrderConfirmation($order);

            if ($result) {
                return new JsonResponse(['success' => true, 'message' => 'Email envoyé avec succès']);
            } else {
                return new JsonResponse(['error' => 'Échec de l\'envoi de l\'email'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/carts/{id}/clear', name: 'clear_cart', methods: ['POST'])]
    public function clearCart(int $id): JsonResponse
    {
        try {
            $cart = $this->entityManager->getRepository(Cart::class)->find($id);

            if (!$cart) {
                return new JsonResponse(['error' => 'Panier non trouvé'], 404);
            }

            // Supprimer tous les items du panier en une seule requête
            $connection = $this->entityManager->getConnection();
            $sql = 'DELETE FROM cart_item WHERE cart_id = :cartId';
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeStatement(['cartId' => $id]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Panier vidé avec succès',
                'deletedItems' => $result
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}
