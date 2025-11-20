<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112141817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mise à jour du champ status de Order en ENUM et migration des anciennes valeurs';
    }

    public function up(Schema $schema): void
    {
        // Mettre à jour les anciennes valeurs vers les nouvelles
        $this->addSql("UPDATE `order` SET status = 'confirmed' WHERE status IN ('pending', 'paid')");
        
        // Modifier la colonne en ENUM
        $this->addSql("ALTER TABLE `order` MODIFY status ENUM('confirmed', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'confirmed'");
    }

    public function down(Schema $schema): void
    {
        // Retour en VARCHAR
        $this->addSql("ALTER TABLE `order` MODIFY status VARCHAR(50) NOT NULL");
    }
}
