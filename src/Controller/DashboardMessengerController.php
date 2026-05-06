<?php 
namespace App\Controller;

use App\Repository\MessagesRepository;
use App\Repository\UserRepository;
use App\Repository\ConversationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use OpenAI\Client;

class DashboardMessengerController extends AbstractController
{
    #[Route('/dashboard-messenger', name: 'app_messenger_dashboard')]
    public function index(
        MessagesRepository $msgRepo,
        UserRepository $userRepo,
        ConversationRepository $convRepo,
        ChartBuilderInterface $chartBuilder,
        Client $aiClient
    ): Response {
        // 1. Chiffres clés
        $totalMessages = $msgRepo->count([]);
        $totalUsers = $userRepo->count([]);
        $totalGroups = $convRepo->count(['type' => 'GROUPE']);

        // 2. Création du Graphique (Répartition des types de messages)
        $mediaData = $msgRepo->countByMessageType();
        $chart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chart->setData([
            'labels' => array_keys($mediaData),
            'datasets' => [[
                'backgroundColor' => ['#1eb2a6', '#ff4757', '#ffa502', '#2f3542', '#747d8c'],
                'data' => array_values($mediaData),
            ]],
        ]);

        // 3. Intelligence Artificielle : Analyse de l'activité
        $aiAnalysis = "Analyse indisponible";
        try {
            $lastMsgs = $msgRepo->findBy([], ['dateEnvoi' => 'DESC'], 10);
            $text = "";
            foreach($lastMsgs as $m) $text .= $m->getContenu() . " | ";

            $prompt = "En tant qu'administrateur système, analyse ces 10 derniers messages et donne un résumé très court (20 mots max) de l'ambiance globale des utilisateurs : " . $text;
            
            $result = $aiClient->chat()->create([
                'model' => 'llama-3.1-8b-instant',
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
            $aiAnalysis = $result->choices[0]->message->content;
        } catch (\Exception $e) {
            $aiAnalysis = "L'IA se repose actuellement...";
        }

        return $this->render('messenger/dashboardMessenger.html.twig', [
            'totalMessages' => $totalMessages,
            'totalUsers' => $totalUsers,
            'totalGroups' => $totalGroups,
            'chart' => $chart,
            'aiAnalysis' => $aiAnalysis
        ]);
    }
}