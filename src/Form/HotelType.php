<?php

namespace App\Form;

use App\Entity\Services;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for Hotel creation and editing.
 * Maps to: AddHotel.fxml / updateHotel.fxml fields.
 */
class HotelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom Hôtel',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom de l\'hôtel est obligatoire.'),
                    new Assert\Length(min: 2, max: 50, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('description', TextType::class, [
                'label'       => 'Description',
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                    new Assert\Length(min: 5, minMessage: 'La description doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('prix', NumberType::class, [
                'label'       => 'Prix (TND)',
                'scale'       => 2,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prix est obligatoire.'),
                    new Assert\Positive(message: 'Le prix doit être positif.'),
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'label'       => 'Capacité',
                'constraints' => [
                    new Assert\NotBlank(message: 'La capacité est obligatoire.'),
                    new Assert\Positive(message: 'La capacité doit être un nombre positif.'),
                ],
            ])
            ->add('nombreEtoiles', IntegerType::class, [
                'label'       => 'Nb Étoiles',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nombre d\'étoiles est obligatoire.'),
                    new Assert\Range(min: 1, max: 5, notInRangeMessage: 'Les étoiles doivent être entre {{ min }} et {{ max }}.'),
                ],
            ])
            ->add('localisation', TextType::class, [
                'label'       => 'Localisation',
                'constraints' => [
                    new Assert\NotBlank(message: 'La localisation est obligatoire.'),
                ],
            ])
            ->add('typeChambre', TextType::class, [
                'label'       => 'Type Chambre',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de chambre est obligatoire.'),
                ],
            ])
            ->add('disponibilite', CheckboxType::class, [
                'label'    => 'Disponible',
                'required' => false,
            ])
            ->add('photo', FileType::class, [
                'label'       => 'Photo',
                'mapped'      => false,   // Handled manually in the controller
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
