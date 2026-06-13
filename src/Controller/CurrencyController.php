<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CurrencyController extends AbstractController
{
    #[Route('/set-currency/{currency}', name: 'app_set_currency')]
    public function setCurrency(string $currency, Request $request): Response
    {
        $currency = strtoupper($currency);
        $allowedCurrencies = ['TND', 'USD', 'EUR', 'GBP'];

        if (in_array($currency, $allowedCurrencies)) {
            $request->getSession()->set('_currency', $currency);
        }

        $referer = $request->headers->get('referer');
        if (!$referer) {
            return $this->redirectToRoute('ourservices_index');
        }

        return $this->redirect($referer);
    }
}
