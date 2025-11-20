<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251116113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar_path column to seller';
    }

    public function up(Schema $schema): void
    {
        // Vérifier si la colonne existe déjà avant de l'ajouter
        $this->addSql("ALTER TABLE seller ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE seller DROP COLUMN avatar_path");
    }
}
