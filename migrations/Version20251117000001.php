<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product_review table and rating fields to product table';
    }

    public function up(Schema $schema): void
    {
        // Créer la table product_review
        $this->addSql('CREATE TABLE product_review (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            rating SMALLINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_product_review_product (product_id),
            INDEX idx_product_review_user (user_id),
            UNIQUE INDEX unique_user_product_review (user_id, product_id),
            CONSTRAINT FK_PRODUCT_REVIEW_PRODUCT FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE,
            CONSTRAINT FK_PRODUCT_REVIEW_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
            CHECK (rating >= 1 AND rating <= 5)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Ajouter les colonnes rating_average et rating_count à la table product
        $this->addSql('ALTER TABLE product ADD rating_average NUMERIC(3, 2) DEFAULT NULL, ADD rating_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les colonnes rating de product
        $this->addSql('ALTER TABLE product DROP rating_average, DROP rating_count');
        
        // Supprimer la table product_review
        $this->addSql('DROP TABLE product_review');
    }
}

