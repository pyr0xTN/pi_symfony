<?php

namespace App\Form;

use App\Entity\Reservations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form for creating a reservation.
 * Maps to: ReservationForm.fxml fields.
 *
 * The 'service_type' option controls whether the seat field is included
 * (vols only — the seat map in the Twig template is built separately).
 */
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
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de réservation est obligatoire.'),
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de réservation ne peut pas être dans le passé.'
                    ),
                ],
            ])
            ->add('modePaiement', TextType::class, [
                'label'       => 'Mode de Paiement',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mode de paiement est obligatoire.'),
                    new Assert\Choice(
                        choices: ['Carte bancaire', 'Virement', 'Espèces', 'PayPal'],
                        message: 'Veuillez choisir un mode de paiement valide.'
                    ),
                ],
            ])
        ;

        // Seat selection is only relevant for vols
        if ($options['service_type'] === 'vol') {
            $builder->add('siege', HiddenType::class, [
                'mapped'      => false,   // Stored manually via seatNb in the controller
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un siège.'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => Reservations::class,
            'service_type' => 'hotel',   // 'hotel' or 'vol'
        ]);

        $resolver->setAllowedValues('service_type', ['hotel', 'vol']);
    }
}
