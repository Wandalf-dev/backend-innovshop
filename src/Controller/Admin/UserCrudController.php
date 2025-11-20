<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('edit', function (User $user) {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User && $user->getId() === $currentUser->getUserIdentifier()) {
                    return 'Modifier mon profil';
                }
                return 'Modifier l\'utilisateur';
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            EmailField::new('email', 'Email')
                ->setHelp('⚠️ Si vous modifiez votre propre email, vous serez déconnecté après l\'enregistrement.'),
            TextField::new('firstName', 'Prénom'),
            TextField::new('lastName', 'Nom'),
            ArrayField::new('roles', 'Rôles'),
        ];

        // Ajout du champ mot de passe avec cache oeil
        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            $fields[] = TextField::new('password', 'Mot de passe')
                ->setFormTypeOption('attr', ['autocomplete' => 'new-password', 'class' => 'ea-password-field'])
                ->setFormTypeOption('mapped', true)
                ->setFormTypeOption('required', $pageName === Crud::PAGE_NEW)
                ->setHelp('Le mot de passe peut être affiché ou masqué avec l\'icône oeil.');
        }

        return $fields;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        $currentUser = $this->getUser();
        
        // Récupérer l'email original avant la modification
        $unitOfWork = $entityManager->getUnitOfWork();
        $unitOfWork->computeChangeSet($entityManager->getClassMetadata(User::class), $entityInstance);
        $changeSet = $unitOfWork->getEntityChangeSet($entityInstance);
        
        $emailChanged = isset($changeSet['email']);
        $isCurrentUser = $currentUser instanceof User && 
                        (($changeSet['email'][0] ?? null) === $currentUser->getUserIdentifier() || 
                         $entityInstance->getEmail() === $currentUser->getUserIdentifier());

        parent::updateEntity($entityManager, $entityInstance);

        // Si l'utilisateur modifie son propre email, marquer pour déconnexion
        if ($isCurrentUser && $emailChanged) {
            $this->addFlash('success', '✅ Votre email a été modifié avec succès.');
            $this->addFlash('warning', '⚠️ Vous allez être déconnecté dans quelques instants pour des raisons de sécurité. Reconnectez-vous avec votre nouvel email.');
            
            // Stocker l'information dans la session
            $session = $this->container->get('request_stack')->getSession();
            $session->set('logout_after_email_change', true);
        }
    }

    public function edit(AdminContext $context): Response
    {
        $response = parent::edit($context);
        
        // Vérifier si on doit déconnecter l'utilisateur
        $session = $this->container->get('request_stack')->getSession();
        if ($session->has('logout_after_email_change')) {
            $session->remove('logout_after_email_change');
            
            // Rediriger vers la déconnexion après 2 secondes
            $logoutUrl = $this->generateUrl('app_logout');
            $html = $response->getContent();
            $script = "<script>setTimeout(function(){ window.location.href = '{$logoutUrl}'; }, 2000);</script>";
            $response->setContent(str_replace('</body>', $script . '</body>', $html));
        }
        
        return $response;
    }

    public function configureActions(Actions $actions): Actions
    {
        $logoutAfterSave = Action::new('logoutAfterSave')
            ->linkToUrl(function () {
                return $this->generateUrl('app_logout');
            });

        return $actions
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, function (Action $action) {
                return $action
                    ->setLabel('Enregistrer')
                    ->displayAsButton();
            });
    }
}
