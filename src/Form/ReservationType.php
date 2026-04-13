<?php

namespace App\Form;

use App\Entity\Reservations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;


class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom du Client',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du client est obligatoire.'),
                    new Assert\Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('dateReservation', DateType::class, [
                'label'       => 'Date de Réservation',
                'widget'      => 'single_text',
                'invalid_message' => 'La date est invalide.',
                'empty_data'     => null,
                'constraints' => [
                    new Assert\NotNull(message: 'La date de réservation est obligatoire.'),
                    new Assert\NotBlank(message: 'La date de réservation est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de réservation ne peut pas être dans le passé.'
                    ),
                ],
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label'   => 'Mode de Paiement',
                'choices' => [
                    'Espèces (Cash)' => 'cash',
                    'Carte Bancaire (Stripe)' => 'stripe',
                    'PayPal' => 'paypal',
                ],
                'placeholder' => 'Choisir un mode...',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mode de paiement est obligatoire.'),
                ],
            ])
        ;

        if ($options['service_type'] === 'vol') {
            $builder->add('siege', HiddenType::class, [
                'mapped'      => false,   
                'required'    => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un siège.'),
                    new Assert\NotNull(message: 'Veuillez sélectionner un siège.'),

                    
                ],
            ]);
        }
    
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => Reservations::class,
            'service_type' => 'hotel',   
        ]);

        $resolver->setAllowedValues('service_type', ['hotel', 'vol']);
    }
}
