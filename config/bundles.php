<?php

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    App\BirthdayRewardBundle\BirthdayRewardBundle::class => ['all' => true],
];

if (class_exists(Symfony\UX\StimulusBundle\StimulusBundle::class)) {
    $bundles[Symfony\UX\StimulusBundle\StimulusBundle::class] = ['all' => true];
}

if (class_exists(Symfony\UX\Turbo\TurboBundle::class)) {
    $bundles[Symfony\UX\Turbo\TurboBundle::class] = ['all' => true];
}

if (class_exists(Symfony\Bundle\MonologBundle\MonologBundle::class)) {
    $bundles[Symfony\Bundle\MonologBundle\MonologBundle::class] = ['all' => true];
}

if (class_exists(Symfony\Bundle\DebugBundle\DebugBundle::class)) {
    $bundles[Symfony\Bundle\DebugBundle\DebugBundle::class] = ['dev' => true];
}

if (class_exists(Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class)) {
    $bundles[Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true];
}

if (class_exists(Symfony\Bundle\MakerBundle\MakerBundle::class)) {
    $bundles[Symfony\Bundle\MakerBundle\MakerBundle::class] = ['dev' => true];
}

return $bundles;
