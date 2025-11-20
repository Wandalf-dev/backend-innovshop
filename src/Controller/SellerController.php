<?php

namespace App\Controller;

use App\Entity\Seller;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/seller', name: 'api_seller_')]
class SellerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function register(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Vérifier si l'utilisateur a déjà un compte vendeur
        if ($user->getSeller()) {
            return $this->json([
                'error' => 'Vous avez déjà un compte vendeur'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);

        // Validation des données
        if (!isset($data['shopName']) || empty(trim($data['shopName']))) {
            return $this->json(['error' => 'Le nom de la boutique est requis'], 400);
        }

        $shopName = trim($data['shopName']);
        $description = $data['description'] ?? null;
        $city = $data['city'] ?? null;
        $country = $data['country'] ?? 'France';
        $iban = $data['iban'] ?? null;

        // Générer le slug
        $baseSlug = $this->slugger->slug($shopName)->lower();
        $slug = $baseSlug;
        $counter = 1;

        // S'assurer que le slug est unique
        while ($this->em->getRepository(Seller::class)->findOneBy(['slug' => $slug])) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Créer le compte vendeur
        $seller = new Seller();
        $seller->setUser($user);
        $seller->setShopName($shopName);
        $seller->setSlug($slug);
        $seller->setDescription($description);
        $seller->setCity($city);
        $seller->setCountry($country);
        $seller->setIban($iban);
        $seller->setStatus('pending'); // En attente de validation
        $seller->setRatingAverage('0.00');
        $seller->setRatingCount(0);

        $this->em->persist($seller);
        $this->em->flush();
        
        // Le SellerCreationListener (postPersist) va mettre à jour les rôles via SQL direct
        // Rafraîchir l'utilisateur depuis la base de données pour obtenir les rôles mis à jour
        // Utiliser refresh() au lieu de clear() + find() pour éviter des requêtes supplémentaires
        $this->em->refresh($user);
        $this->em->refresh($seller);

        return $this->json([
            'message' => 'Votre demande de compte vendeur a été envoyée. Elle sera examinée par notre équipe.',
            'seller' => [
                'id' => $seller->getId(),
                'shopName' => $seller->getShopName(),
                'slug' => $seller->getSlug(),
                'status' => $seller->getStatus()
            ],
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles()
            ]
        ], 201);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_SELLER') or is_granted('ROLE_USER')"))]
    public function getCurrentUserSeller(): JsonResponse
    {
        $startTime = microtime(true);
        
        /** @var User $user */
        $user = $this->getUser();
        $seller = $user->getSeller();

        if (!$seller) {
            return $this->json(['seller' => null]);
        }

        $response = $this->json([
            'seller' => [
                'id' => $seller->getId(),
                'shopName' => $seller->getShopName(),
                'slug' => $seller->getSlug(),
                'status' => $seller->getStatus(),
                'description' => $seller->getDescription(),
                'city' => $seller->getCity(),
                'country' => $seller->getCountry(),
                'avatarPath' => $seller->getAvatarPath(),
                'ratingAverage' => $seller->getRatingAverage(),
                'ratingCount' => $seller->getRatingCount(),
                'createdAt' => $seller->getCreatedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        error_log(sprintf('[PERF] /seller/me took %.2f ms', $executionTime));
        
        return $response;
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_SELLER') or is_granted('ROLE_USER')"))]
    public function getDashboard(): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            /** @var User $user */
            $user = $this->getUser();
            $seller = $user->getSeller();

            if (!$seller) {
                return $this->json(['seller' => null], 200);
            }

            $sellerData = [
                'id' => $seller->getId(),
                'shopName' => $seller->getShopName(),
                'slug' => $seller->getSlug(),
                'status' => $seller->getStatus(),
                'description' => $seller->getDescription(),
                'city' => $seller->getCity(),
                'country' => $seller->getCountry(),
                'avatarPath' => $seller->getAvatarPath(),
                'ratingAverage' => $seller->getRatingAverage(),
                'ratingCount' => $seller->getRatingCount(),
                'createdAt' => $seller->getCreatedAt()?->format('Y-m-d H:i:s')
            ];

            // Si le vendeur n'est pas approuvé, retourner seulement ses infos
            if ($seller->getStatus() !== 'approved') {
                return $this->json([
                    'seller' => $sellerData,
                    'stats' => ['totalProducts' => 0, 'totalRevenue' => 0, 'totalOrders' => 0],
                    'products' => [],
                    'orders' => []
                ]);
            }

            // Une seule requête SQL pour tout récupérer
            // Pour InnovShop (id === 4), inclure les produits et commandes avec seller_id = NULL
            $conn = $this->em->getConnection();
            $sellerId = $seller->getId();
            $isInnovShop = ($sellerId === 4);
            
            if ($isInnovShop) {
                $sql = '
                    SELECT 
                        (SELECT COUNT(*) FROM product WHERE seller_id IS NULL) as product_count,
                        (SELECT COALESCE(SUM(unit_price * quantity), 0) FROM order_item WHERE seller_id IS NULL) as total_revenue,
                        (SELECT COUNT(DISTINCT order_id) FROM order_item WHERE seller_id IS NULL) as order_count
                ';
            } else {
                $sql = '
                    SELECT 
                        (SELECT COUNT(*) FROM product WHERE seller_id = :seller_id) as product_count,
                        (SELECT COALESCE(SUM(unit_price * quantity), 0) FROM order_item WHERE seller_id = :seller_id) as total_revenue,
                        (SELECT COUNT(DISTINCT order_id) FROM order_item WHERE seller_id = :seller_id) as order_count
                ';
            }
            
            $stmt = $conn->prepare($sql);
            $params = $isInnovShop ? [] : ['seller_id' => $sellerId];
            $result = $stmt->executeQuery($params);
            $stats = $result->fetchAssociative();

            // Récupérer les produits avec images
            // Pour InnovShop (id === 4), inclure les produits avec seller = NULL
            $productsQuery = $this->em->createQueryBuilder()
                ->select('p', 'pi')
                ->from('App\Entity\Product', 'p')
                ->leftJoin('p.images', 'pi');
            
            if ($isInnovShop) {
                $productsQuery->where('p.seller IS NULL');
            } else {
                $productsQuery->where('p.seller = :seller')
                    ->setParameter('seller', $seller);
            }
            
            $products = $productsQuery
                ->orderBy('p.createdAt', 'DESC')
                ->addOrderBy('pi.position', 'ASC')
                ->setMaxResults(20) // Limiter au départ
                ->getQuery()
                ->getResult();

            $productsData = [];
            foreach ($products as $product) {
                $images = [];
                foreach ($product->getImages() as $image) {
                    $images[] = [
                        'filePath' => $image->getFilePath(),
                        'altText' => $image->getAltText(),
                        'position' => $image->getPosition()
                    ];
                }

                $productsData[] = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'isPublished' => $product->isPublished(),
                    'isFeatured' => $product->isFeatured(),
                    'images' => $images,
                    'createdAt' => $product->getCreatedAt()?->format('Y-m-d H:i:s')
                ];
            }

            // Récupérer les commandes récentes avec les lots de vendeurs
            // Pour InnovShop (id === 4), inclure les orderItems avec seller = NULL
            $orderItemsQuery = $this->em->createQueryBuilder()
                ->select('oi', 'o', 'p', 'u')
                ->from('App\Entity\OrderItem', 'oi')
                ->join('oi.order', 'o')
                ->join('oi.product', 'p')
                ->join('o.user', 'u')
                ->leftJoin('o.sellerLots', 'lot')
                ->leftJoin('lot.seller', 'lotSeller')
                ->addSelect('lot')
                ->addSelect('lotSeller');
            
            if ($isInnovShop) {
                $orderItemsQuery->where('oi.seller IS NULL');
            } else {
                $orderItemsQuery->where('oi.seller = :seller')
                    ->setParameter('seller', $seller);
            }
            
            $orderItems = $orderItemsQuery
                ->orderBy('o.createdAt', 'DESC')
                ->setMaxResults(20) // Limiter au départ
                ->getQuery()
                ->getResult();

            // Grouper les orderItems par commande pour éviter les doublons
            $ordersMap = [];
            foreach ($orderItems as $orderItem) {
                $order = $orderItem->getOrder();
                if (!$order) {
                    continue;
                }
                
                $orderId = $order->getId();
                if (!$orderId) {
                    continue;
                }
                
                if (!isset($ordersMap[$orderId])) {
                    $user = $order->getUser();
                    
                    // Trouver le lot correspondant au vendeur
                    // Pour InnovShop (id === 4), le lot peut avoir seller = NULL
                    $sellerLotStatus = $order->getStatus() ?? 'confirmed'; // Par défaut, utiliser le statut de la commande
                    foreach ($order->getSellerLots() as $lot) {
                        $lotSeller = $lot->getSeller();
                        if ($isInnovShop) {
                            // Pour InnovShop, le lot peut avoir seller = NULL
                            if ($lotSeller === null) {
                                $sellerLotStatus = $lot->getStatus();
                                break;
                            }
                        } else {
                            // Pour les autres vendeurs, vérifier que le lot correspond au vendeur
                            if ($lotSeller && $lotSeller->getId() === $seller->getId()) {
                                $sellerLotStatus = $lot->getStatus();
                                break;
                            }
                        }
                    }
                    
                    $ordersMap[$orderId] = [
                        'id' => $order->getId(),
                        'reference' => $order->getReference() ?? '',
                        'status' => $sellerLotStatus, // Utiliser le statut du lot au lieu du statut de la commande
                        'createdAt' => $order->getCreatedAt() ? $order->getCreatedAt()->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
                        'total' => '0.00',
                        'user' => [
                            'firstName' => $user ? ($user->getFirstName() ?? '') : '',
                            'lastName' => $user ? ($user->getLastName() ?? '') : '',
                            'email' => $user ? ($user->getEmail() ?? '') : ''
                        ],
                        'items' => []
                    ];
                }
                
                $product = $orderItem->getProduct();
                $unitPrice = $orderItem->getUnitPrice() ?? '0.00';
                $quantity = $orderItem->getQuantity() ?? 1;
                
                // Récupérer les images du produit
                $productImages = [];
                if ($product && $product->getImages()) {
                    foreach ($product->getImages() as $image) {
                        $productImages[] = [
                            'id' => $image->getId(),
                            'filePath' => $image->getFilePath(),
                            'altText' => $image->getAltText(),
                            'position' => $image->getPosition()
                        ];
                    }
                }
                
                $itemTotal = (float) $unitPrice * $quantity;
                $ordersMap[$orderId]['total'] = (string) ((float) $ordersMap[$orderId]['total'] + $itemTotal);
                $ordersMap[$orderId]['items'][] = [
                    'id' => $orderItem->getId(),
                    'product' => [
                        'id' => $product ? $product->getId() : null,
                        'name' => $product ? ($product->getName() ?? $orderItem->getProductName() ?? '') : ($orderItem->getProductName() ?? ''),
                        'price' => $product ? $product->getPrice() : null,
                        'images' => $productImages
                    ],
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                    'selectedColor' => $orderItem->getSelectedColor(),
                    'selectedSize' => $orderItem->getSelectedSize(),
                ];
            }
            
            $ordersData = array_values($ordersMap);

            $executionTime = (microtime(true) - $startTime) * 1000;
            error_log(sprintf('[PERF] /seller/dashboard took %.2f ms', $executionTime));

            return $this->json([
                'seller' => $sellerData,
                'stats' => [
                    'totalProducts' => (int) ($stats['product_count'] ?? 0),
                    'totalRevenue' => (float) ($stats['total_revenue'] ?? 0),
                    'totalOrders' => (int) ($stats['order_count'] ?? 0)
                ],
                'products' => $productsData,
                'orders' => $ordersData
            ]);
        } catch (\Exception $e) {
            error_log('[ERROR] Dashboard error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return $this->json([
                'error' => 'Erreur serveur',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    #[IsGranted('ROLE_SELLER')]
    public function getStats(): JsonResponse
    {
        $startTime = microtime(true);
        
        /** @var User $user */
        $user = $this->getUser();
        $seller = $user->getSeller();

        if (!$seller || $seller->getStatus() !== 'approved') {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            // Une seule requête SQL pour tout récupérer
            $conn = $this->em->getConnection();
            $sql = '
                SELECT 
                    (SELECT COUNT(*) FROM product WHERE seller_id = :seller_id) as product_count,
                    (SELECT COALESCE(SUM(price * quantity), 0) FROM order_item WHERE seller_id = :seller_id) as total_revenue,
                    (SELECT COUNT(DISTINCT order_id) FROM order_item WHERE seller_id = :seller_id) as order_count
            ';
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery(['seller_id' => $seller->getId()]);
            $stats = $result->fetchAssociative();
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            error_log(sprintf('[PERF] /seller/stats took %.2f ms', $executionTime));

            return $this->json([
                'totalProducts' => (int) ($stats['product_count'] ?? 0),
                'totalRevenue' => (float) ($stats['total_revenue'] ?? 0),
                'totalOrders' => (int) ($stats['order_count'] ?? 0)
            ]);
        } catch (\Exception $e) {
            error_log('[ERROR] /seller/stats failed: ' . $e->getMessage());
            // En cas d'erreur, retourner des stats vides
            return $this->json([
                'totalProducts' => 0,
                'totalRevenue' => 0,
                'totalOrders' => 0
            ]);
        }
    }

    #[Route('/products', name: 'products', methods: ['GET'])]
    #[IsGranted('ROLE_SELLER')]
    public function getProducts(): JsonResponse
    {
        $startTime = microtime(true);
        
        /** @var User $user */
        $user = $this->getUser();
        $seller = $user->getSeller();

        if (!$seller) {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        // Récupérer tous les produits du vendeur avec images en une seule requête
        $products = $this->em->createQueryBuilder()
            ->select('p', 'pi')
            ->from('App\Entity\Product', 'p')
            ->leftJoin('p.images', 'pi')
            ->where('p.seller = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('pi.position', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($products as $product) {
            $images = [];
            foreach ($product->getImages() as $image) {
                $images[] = [
                    'filePath' => $image->getFilePath(),
                    'altText' => $image->getAltText(),
                    'position' => $image->getPosition()
                ];
            }

            $result[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'isPublished' => $product->isPublished(),
                'isFeatured' => $product->isFeatured(),
                'images' => $images,
                'createdAt' => $product->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        error_log(sprintf('[PERF] /seller/products took %.2f ms (count: %d)', $executionTime, count($result)));

        return $this->json($result);
    }

    #[Route('/orders', name: 'orders', methods: ['GET'])]
    #[IsGranted('ROLE_SELLER')]
    public function getOrders(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $seller = $user->getSeller();

        if (!$seller || $seller->getStatus() !== 'approved') {
            return $this->json(['error' => 'Accès non autorisé'], 403);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Récupérer les order_items du vendeur avec les détails de la commande
        $qb = $this->em->createQueryBuilder()
            ->select('oi', 'o', 'p', 'u')
            ->from('App\Entity\OrderItem', 'oi')
            ->join('oi.order', 'o')
            ->join('oi.product', 'p')
            ->join('o.user', 'u')
            ->where('oi.seller = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('o.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $orderItems = $qb->getQuery()->getResult();

        $result = [];
        foreach ($orderItems as $orderItem) {
            $order = $orderItem->getOrder();
            $result[] = [
                'id' => $orderItem->getId(),
                'orderId' => $order->getId(),
                'orderNumber' => $order->getReference(),
                'orderStatus' => $order->getStatus(),
                'orderDate' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'product' => [
                    'id' => $orderItem->getProduct()->getId(),
                    'name' => $orderItem->getProduct()->getName(),
                ],
                'quantity' => $orderItem->getQuantity(),
                'price' => $orderItem->getUnitPrice(),
                'total' => (float) $orderItem->getUnitPrice() * $orderItem->getQuantity(),
                'customer' => [
                    'name' => $order->getUser()->getFirstName() . ' ' . $order->getUser()->getLastName(),
                    'email' => $order->getUser()->getEmail()
                ]
            ];
        }

        return $this->json([
            'orders' => $result,
            'page' => $page,
            'total' => count($result)
        ]);
    }
}
