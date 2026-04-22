<?php

namespace App\Twig;

use App\Service\CurrencyConverterService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CurrencyExtension extends AbstractExtension
{
    private $converter;
    private $requestStack;

    public function __construct(CurrencyConverterService $converter, RequestStack $requestStack)
    {
        $this->converter = $converter;
        $this->requestStack = $requestStack;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_currency', [$this, 'formatCurrency'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_currency', [$this, 'getCurrentCurrency']),
        ];
    }

    public function getCurrentCurrency(): string
    {
        // Inside twig, sometimes there is no request when rendering fragments
        try {
            $session = $this->requestStack->getSession();
            return $session->get('_currency', 'TND');
        } catch (\Exception $e) {
            return 'TND';
        }
    }

    public function formatCurrency($amount): string
    {
        if ($amount === null) return '';
        
        $currency = $this->getCurrentCurrency();
        $convertedAmount = $this->converter->convert((float)$amount, $currency);
        $symbol = $this->converter->getSymbol($currency);

        if ($currency === 'TND') {
            return number_format($convertedAmount, 3, '.', ' ') . ' <small>TND</small>';
        }

        // For USD, EUR, GBP
        if ($currency === 'USD' || $currency === 'GBP') {
            return $symbol . number_format($convertedAmount, 2, '.', ' ');
        }

        return number_format($convertedAmount, 2, '.', ' ') . ' ' . $symbol;
    }
}
