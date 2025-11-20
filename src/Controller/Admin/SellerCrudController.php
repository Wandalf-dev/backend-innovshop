<?php

namespace App\Controller\Admin;

use App\Entity\Seller;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class SellerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Seller::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Vendeur')
            ->setEntityLabelInPlural('Vendeurs')
            ->setPageTitle('index', 'Gestion des Vendeurs')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['shopName', 'slug', 'city', 'country', 'user.email'])
            ->addFormTheme('@EasyAdmin/crud/form_theme.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Ajouter un vendeur');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setLabel('Modifier');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setLabel('Supprimer');
            })
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setLabel('Voir');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        
        yield AssociationField::new('user', 'Utilisateur')
            ->formatValue(function ($value, $entity) {
                return $entity->getUser() ? $entity->getUser()->getEmail() : '';
            });
        
        yield TextField::new('shopName', 'Nom de la boutique');
        yield TextField::new('slug', 'Slug')->onlyOnIndex();
        
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'En attente' => 'pending',
                'Approuvé' => 'approved',
                'Suspendu' => 'suspended',
                'Rejeté' => 'rejected'
            ])
            ->renderAsBadges([
                'pending' => 'warning',
                'approved' => 'success',
                'suspended' => 'danger',
                'rejected' => 'danger'
            ]);
        
        yield TextEditorField::new('description', 'Description')
            ->hideOnIndex();
        
        yield TextField::new('city', 'Ville')->hideOnIndex();
        yield TextField::new('country', 'Pays');
        
        yield TextField::new('iban', 'IBAN')
            ->hideOnIndex()
            ->setHelp('Pour les paiements');
        
        yield NumberField::new('ratingAverage', 'Note moyenne')
            ->setNumDecimals(2)
            ->hideOnForm();
        
        yield NumberField::new('ratingCount', 'Nombre d\'avis')
            ->hideOnForm();
        
        yield DateTimeField::new('createdAt', 'Date de création')
            ->hideOnForm();
        
        yield DateTimeField::new('updatedAt', 'Dernière modification')
            ->onlyOnDetail();
    }
}
