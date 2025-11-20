<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\Seller;
use App\Entity\ProductReview;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recalculate-ratings',
    description: 'Recalculates ratings for all products and sellers',
)]
class RecalculateRatingsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Recalculating Ratings (SQL)');

        $conn = $this->em->getConnection();

        // 1. Update Product Ratings
        $io->section('Updating Products');
        
        // Reset all to 0 first to handle those with no reviews
        $conn->executeStatement("UPDATE product SET rating_average = 0, rating_count = 0");
        
        $sqlProduct = "
            UPDATE product p
            JOIN (
                SELECT product_id, AVG(rating) as avg_rating, COUNT(id) as count_rating
                FROM product_review
                GROUP BY product_id
            ) r ON p.id = r.product_id
            SET p.rating_average = r.avg_rating, p.rating_count = r.count_rating
        ";
        $countProduct = $conn->executeStatement($sqlProduct);
        $io->success("$countProduct products updated with reviews.");

        // 2. Update Seller Ratings
        $io->section('Updating Sellers');
        
        // Reset all to 0 first
        $conn->executeStatement("UPDATE seller SET rating_average = 0, rating_count = 0");

        $sqlSeller = "
            UPDATE seller s
            JOIN (
                SELECT p.seller_id, AVG(r.rating) as avg_rating, COUNT(r.id) as count_rating
                FROM product_review r
                JOIN product p ON r.product_id = p.id
                WHERE p.seller_id IS NOT NULL
                GROUP BY p.seller_id
            ) stats ON s.id = stats.seller_id
            SET s.rating_average = stats.avg_rating, s.rating_count = stats.count_rating
        ";
        $countSeller = $conn->executeStatement($sqlSeller);
        $io->success("$countSeller sellers updated with reviews.");

        return Command::SUCCESS;
    }
}
