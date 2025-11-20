<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117215728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert product color field from string to JSON array (colors)';
    }

    public function up(Schema $schema): void
    {
        // Ajouter la nouvelle colonne colors (JSON)
        $this->addSql('ALTER TABLE product ADD colors JSON DEFAULT NULL');
        
        // Migrer les données : convertir les valeurs string de color en array JSON dans colors
        // Si color n'est pas NULL, créer un array avec cette valeur
        $this->addSql("UPDATE product SET colors = JSON_ARRAY(color) WHERE color IS NOT NULL AND color != ''");
        
        // Supprimer l'ancienne colonne color
        $this->addSql('ALTER TABLE product DROP COLUMN color');
    }

    public function down(Schema $schema): void
    {
        // Ajouter la colonne color
        $this->addSql('ALTER TABLE product ADD color VARCHAR(50) DEFAULT NULL');
        
        // Migrer les données : prendre la première couleur de l'array JSON
        $this->addSql("UPDATE product SET color = JSON_UNQUOTE(JSON_EXTRACT(colors, '$[0]')) WHERE colors IS NOT NULL AND JSON_LENGTH(colors) > 0");
        
        // Supprimer la colonne colors
        $this->addSql('ALTER TABLE product DROP COLUMN colors');
    }
}
