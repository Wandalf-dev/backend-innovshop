<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('orderNumber', 'Numéro de commande'),
            AssociationField::new('user', 'Client'),
            MoneyField::new('totalAmount', 'Montant total')->setCurrency('EUR'),
            TextField::new('status', 'Statut'),
            DateTimeField::new('createdAt', 'Date de création')->hideOnForm(),
            CollectionField::new('orderItems', 'Articles')->onlyOnDetail(),
        ];
    }
}
