<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251117221153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert product size field from string to JSON array (sizes)';
    }

    public function up(Schema $schema): void
    {
        // Ajouter la nouvelle colonne sizes (JSON)
        $this->addSql('ALTER TABLE product ADD sizes JSON DEFAULT NULL');
        
        // Migrer les données : convertir les valeurs string de size en array JSON dans sizes
        // Si size n'est pas NULL, créer un array avec cette valeur
        $this->addSql("UPDATE product SET sizes = JSON_ARRAY(size) WHERE size IS NOT NULL AND size != ''");
        
        // Supprimer l'ancienne colonne size
        $this->addSql('ALTER TABLE product DROP COLUMN size');
    }

    public function down(Schema $schema): void
    {
        // Ajouter la colonne size
        $this->addSql('ALTER TABLE product ADD size VARCHAR(50) DEFAULT NULL');
        
        // Migrer les données : prendre la première taille de l'array JSON
        $this->addSql("UPDATE product SET size = JSON_UNQUOTE(JSON_EXTRACT(sizes, '$[0]')) WHERE sizes IS NOT NULL AND JSON_LENGTH(sizes) > 0");
        
        // Supprimer la colonne sizes
        $this->addSql('ALTER TABLE product DROP COLUMN sizes');
    }
}
