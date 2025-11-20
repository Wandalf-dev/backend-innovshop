<?php

namespace App\Serializer;

use App\Entity\Seller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SellerNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ObjectNormalizer $normalizer,
        private EntityManagerInterface $em
    ) {
    }

    public function normalize($object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        
        if ($object instanceof Seller && is_array($data)) {
            // Calculer le nombre de produits publiés via une requête SQL
            $conn = $this->em->getConnection();
            $sql = 'SELECT COUNT(*) as count FROM product WHERE seller_id = :seller_id AND is_published = 1';
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery(['seller_id' => $object->getId()]);
            $row = $result->fetchAssociative();
            $data['productCount'] = (int) ($row['count'] ?? 0);
        }
        
        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Seller;
    }

    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
        return $this->normalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return $type === Seller::class;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Seller::class => true];
    }
}

