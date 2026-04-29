<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['id' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield TextField::new('username');
        yield TextField::new('name', 'First Name');
        yield TextField::new('lastName', 'Last Name');
        yield TextField::new('role');
        yield TextField::new('status')->hideOnForm();
        yield TextField::new('dateDisplay', 'Birth Date')->hideOnForm();
        yield TextField::new('blockedDisplay', 'Blocked')->hideOnForm();
        yield TextField::new('twoFactorEnabledDisplay', '2FA Enabled')->hideOnForm();
        yield TextField::new('twoFactorExpiryDisplay', '2FA Expiry')->hideOnForm();
    }
}
