<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration marketplace - seller, product.seller_id, order_item.seller_id
 * Cette migration a déjà été exécutée manuellement via marketplace_migration.sql
 */
final class Version20251115145600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la gestion des vendeurs (marketplace) - déjà appliqué manuellement';
    }

    public function up(Schema $schema): void
    {
        // Cette migration a déjà été exécutée manuellement via marketplace_migration.sql
        // On ne fait rien ici pour éviter les erreurs
        $this->addSql('-- Migration déjà appliquée manuellement');
    }

    public function down(Schema $schema): void
    {
        // Rollback non recommandé car cela supprimerait des données vendeur
        $this->addSql('-- Rollback non supporté pour cette migration');
    }
}
