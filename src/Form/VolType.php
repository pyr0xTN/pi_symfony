<?php

namespace App\Form;

use App\Entity\Services;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form for Vol (flight) creation and editing.
 * Maps to: AddVol.fxml / updateVol.fxml fields.
 */
class VolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du vol est obligatoire.'),
                    new Assert\Length(min: 2, max: 50),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'Description',
                'attr'        => ['rows' => 3],
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                ],
            ])
            ->add('prix', NumberType::class, [
                'label'       => 'Prix',
                'scale'       => 2,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prix est obligatoire.'),
                    new Assert\Positive(message: 'Le prix doit être positif.'),
                ],
            ])
            ->add('disponibilite', CheckboxType::class, [
                'label'    => 'Disponible',
                'required' => false,
                
            ])
            ->add('capacite', IntegerType::class, [
                'label'       => 'Capacité',
                'constraints' => [
                    new Assert\NotBlank(message: 'La capacité est obligatoire.'),
                    new Assert\Positive(message: 'La capacité doit être positive.'),
                ],
            ])
            ->add('numeroVol', TextType::class, [
                'label'       => 'Numéro Vol',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le numéro de vol est obligatoire.'),
                    new Assert\Length(max: 25),
                ],
            ])
            ->add('villeDepart', TextType::class, [
                'label'       => 'Ville Départ',
                'constraints' => [
                    new Assert\NotBlank(message: 'La ville de départ est obligatoire.'),
                ],
            ])
            ->add('villeArrivee', TextType::class, [
                'label'       => 'Ville Arrivée',
                'constraints' => [
                    new Assert\NotBlank(message: 'La ville d\'arrivée est obligatoire.'),
                ],
            ])
            ->add('dateDepart', DateType::class, [
                'label'       => 'Date Départ',
                'widget'      => 'single_text',
                'required'    => true,
                'invalid_message' => 'La date de départ est invalide.',
                'empty_data'     => null,
                'constraints' => [
                    new Assert\NotNull(message: 'La date de départ est obligatoire.'), 
                    new Assert\NotBlank(message: 'La date de départ est obligatoire.'), 
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de départ doit être aujourd\'hui ou dans le futur.'
                    ),
                ],
            ])
            ->add('dateArrive', DateType::class, [
                'label'       => 'Date Arrivée',
                'widget'      => 'single_text',
                'required'    => true,
                'invalid_message' => 'La date de Arrivée est invalide.',
                'empty_data'     => null,
                'constraints' => [
                    new Assert\NotNull(message: 'La date d\'arrivée est obligatoire.'),  
                    new Assert\NotBlank(message: 'La date d\'arrivée est obligatoire.'),  
                    new Assert\GreaterThan(
                        value: 'today',
                        message: 'La date d\'arrivée doit être après la date de départ.'
                    ),
                ],
            ])
            ->add('photo', FileType::class, [
                'label'       => 'Photo',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize'          => '5M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP).',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Services::class,
        ]);
    }
}
