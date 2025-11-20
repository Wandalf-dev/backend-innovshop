# Scripts utiles InnovShop

# Validation du schéma (ignore les faux positifs MariaDB)
function Validate-Schema {
    php bin/console app:schema:validate
}

# Alias court
Set-Alias -Name schema -Value Validate-Schema

# Migration Doctrine
function Make-Migration {
    php bin/console make:migration
}

function Run-Migrations {
    php bin/console doctrine:migrations:migrate
}

# Nettoyer le cache
function Clear-Cache {
    php bin/console cache:clear
}

# Démarrer le serveur
function Start-Server {
    php bin/console server:start
}

Write-Host "✅ Commandes InnovShop chargées !" -ForegroundColor Green
Write-Host "  • Validate-Schema  : Valider le schéma (sans faux positifs)" -ForegroundColor Cyan
Write-Host "  • schema           : Alias de Validate-Schema" -ForegroundColor Cyan
Write-Host "  • Make-Migration   : Créer une migration" -ForegroundColor Cyan
Write-Host "  • Run-Migrations   : Exécuter les migrations" -ForegroundColor Cyan
Write-Host "  • Clear-Cache      : Nettoyer le cache" -ForegroundColor Cyan
Write-Host "  • Start-Server     : Démarrer le serveur" -ForegroundColor Cyan
